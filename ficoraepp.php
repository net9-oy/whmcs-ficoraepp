<?php
if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Illuminate\Database\Capsule\Manager as Capsule;
use Metaregistrar\EPP\eppContact;
use Metaregistrar\EPP\eppContactHandle;
use Metaregistrar\EPP\eppCreateContactResponse;
use Metaregistrar\EPP\eppCreateDomainRequest;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppTransferRequest;
use Metaregistrar\EPP\ficoraEppConnection;
use Metaregistrar\EPP\ficoraEppContactPostalInfo;
use Metaregistrar\EPP\ficoraEppCreateContactRequest;
use Metaregistrar\EPP\ficoraEppDomain;
use Metaregistrar\EPP\ficoraEppInfoContactRequest;
use Metaregistrar\EPP\ficoraEppInfoContactResponse;
use Metaregistrar\EPP\ficoraEppInfoDomainRequest;
use Metaregistrar\EPP\ficoraEppInfoDomainResponse;
use Metaregistrar\EPP\ficoraEppRenewRequest;
use Metaregistrar\EPP\ficoraEppUpdateContactRequest;
use Metaregistrar\EPP\ficoraEppUpdateDomainRequest;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/cache.php';
require __DIR__ . '/ficoraEppTransferRequest.php';

class FicoraModule
{
    protected $params;
    /* @var ficoraEppConnection */
    protected $connection;
    protected $contactId;

    public function __construct(array $params)
    {
        $this->params = $params;
        $connection = new ficoraEppConnection($params['ficora_debug'], '/tmp/none');
        $connection->setPort($params['ficora_port']);
        $connection->setUsername($params['ficora_username']);
        $connection->setPassword($params['ficora_password']);
        $connection->enableCertification($params['ficora_certpath'], $params['ficora_certpass'], true);
        $connection->setHostname($params['ficora_hostname']);
        $connection->setTimeout($this->params['ficora_timeout']);
        $connection->setRetry($this->params['ficora_retry']);
        $connection->setLogFile('debug.txt');
        $connection->addCommandResponse('FicoraEpp\\ficoraEppTransferRequest',
            'Metaregistrar\\EPP\\eppTransferResponse');
        $this->connection = $connection;
        $this->connection->login();
    }

    /**
     * @throws \Metaregistrar\EPP\eppException
     */
    public function register()
    {
        $this->connection->request(
            new eppCreateDomainRequest(
                $this->eppDomain($this->getNameserversForRequest()),
                false,
                false
            )
        );
    }

    /**
     * @throws \Metaregistrar\EPP\eppException
     */
    public function updateNameservers()
    {
        $domain = new ficoraEppDomain($this->params['domainname']);
        $del = new ficoraEppDomain($this->params['domainname']);
        $add = new ficoraEppDomain($this->params['domainname']);
        foreach ($this->getNameservers() as $ns) {
            $del->addHost(new eppHost($ns));
        }
        if (count($nameservers = $this->parseNameservers()) > 2) {
            foreach ($nameservers as $ns) {
                if (empty($ns)) {
                    continue;
                }

                $add->addHost(new eppHost($ns));
            }
        } else {
            throw new \RuntimeException('Not enough nameservers provided');
        }
        $this->connection->request(new ficoraEppUpdateDomainRequest($domain, $add, $del));
    }

    /**
     * @throws \Metaregistrar\EPP\eppException
     */
    public function renew()
    {
        if ($this->params['regperiod'] < 1) {
            throw new \RuntimeException("Regperiod {$this->params['regperiod']} out of range");
        }

        $this->connection->request(
            new ficoraEppRenewRequest(
                new ficoraEppDomain(
                    $this->params['domainname'],
                    null,
                    null,
                    null,
                    $this->params['regperiod']
                ),
                (new DateTime($this->info()->getDomainExpirationDate()))->format('Y-m-d')
            )
        );
    }

    /**
     * @return array
     * @throws \Metaregistrar\EPP\eppException
     */
    public function getContacts(): array
    {
        $info = $this->info();
        return array_reduce(
            array_merge(
                $info->getDomainContacts() ?? [],
                [
                    new eppContactHandle($info->getDomainRegistrant(),
                        eppContactHandle::CONTACT_TYPE_REGISTRANT)
                ]
            ),
            function ($carry, eppContactHandle $item) {
                switch ($item->getContactType()) {
                    case eppContactHandle::CONTACT_TYPE_REGISTRANT:
                        $key = 'Registrant';
                        break;
                    case eppContactHandle::CONTACT_TYPE_TECH:
                        $key = 'Technical';
                        break;
                    case eppContactHandle::CONTACT_TYPE_ADMIN:
                    case eppContactHandle::CONTACT_TYPE_BILLING:
                        $key = ucfirst($item->getContactType());
                        break;
                    default:
                        return $carry;
                }

                $contact = $this->contactInfo($item);
                /* @var $postal ficoraEppContactPostalInfo */
                $postal = current($contact->getContactPostalInfo());

                $carry[$key] = [
                    'First Name' => $postal->getFirstName(),
                    'Last Name' => $postal->getLastName(),
                    'Company Name' => $postal->getOrganisationName(),
                    'Email Address' => $contact->getContactEmail(),
                    'Address 1' => $postal->getStreet(0),
                    'Address 2' => $postal->getStreet(1),
                    'City' => $postal->getCity(),
                    'State' => $postal->getProvince(),
                    'Postcode' => $postal->getZipcode(),
                    'Country' => $postal->getCountrycode(),
                    'Phone Number' => $contact->getContactVoice(),
                ];

                return $carry;
            },
            []
        );
    }

    /**
     * @return ficoraEppInfoDomainResponse
     * @throws \Metaregistrar\EPP\eppException
     */
    public function info(): ficoraEppInfoDomainResponse
    {
        /* @var $response ficoraEppInfoDomainResponse */
        $response = $this->connection->request(new ficoraEppInfoDomainRequest(new ficoraEppDomain(
            $this->params['domainname'])));
        return $response;
    }

    /**
     * @throws \Metaregistrar\EPP\eppException
     */
    public function transfer()
    {
        $domain = $this->eppDomain($this->getNameserversForRequest());
        $domain->setAuthorisationCode($this->params['eppcode']);
        $domain->setPeriod(0);
        $this->connection->request(
            new \FicoraEPP\ficoraEppTransferRequest(
                $domain
            )
        );
    }

    /**
     * @return string
     * @throws \Metaregistrar\EPP\eppException
     */
    public function epp(): string
    {
        $password = str_shuffle("aB1#" . eppContact::generateRandomString());
        $this->connection->request(
            new ficoraEppUpdateDomainRequest(
                $this->params['domainname'],
                null,
                null,
                new ficoraEppDomain(
                    $this->params['domainname'],
                    null,
                    null,
                    null,
                    null,
                    $password
                )
            )
        );
        return $password;
    }

    /**
     * @return array
     * @throws \Metaregistrar\EPP\eppException
     */
    public function getNameservers(): array
    {
        /** @var eppHost[] $nameservers */
        $nameservers = $this->info()->getDomainNameservers();

        return array_reduce(array_keys($nameservers) ?? [],
            function ($carry, $item) use ($nameservers) {
                $carry['ns' . ($item + 1)] = $nameservers[$item]->getHostname();
                return $carry;
            },
            []
        );
    }

    /**
     * @throws \Metaregistrar\EPP\eppException
     */
    public function saveContact()
    {
        foreach ($this->params['contactdetails'] as $type => $details) {
            if($type !== 'Registrant') {
                continue;
            }

            $postal = new ficoraEppContactPostalInfo(
                null,
                $details['City'],
                $details['Country'],
                null,
                $details['Address 1'],
                null,
                $details['Postcode'],
                eppContact::TYPE_LOC
            );
            $postal->setIsFinnish($details['Country'] === 'FI');
            $contact = new eppContact(
                $postal,
                $details['Email Address'],
                $details['Phone Number']
            );
            $contact->setPassword(null);
            $contact->setType(eppContact::TYPE_LOC);
            $this->connection->request(
                new ficoraEppUpdateContactRequest(
                    $this->getContact(),
                    null,
                    null,
                    $contact
                )
            );
        }
    }

    protected function getNameserversForRequest(): array
    {
        if (count($nameservers = $this->parseNameservers()) < 2) {
            throw new \RuntimeException('Not enough nameservers provided');
        }

        foreach ($nameservers as $k => $v) {
            if (empty($v)) {
                continue;
            }

            $ns[$k] = new eppHost($v);
        }

        if (!$ns) {
            throw new \RuntimeException('Not enough nameservers provided');
        }

        return $ns;
    }

    protected function getContact()
    {
        if (!array_key_exists('userid', $this->params)) {
            $this->params['userid'] = Capsule::table('tbldomains')
                ->where('id', '=', $this->params['domainid'])
                ->value('userid');
        }

        if (!$this->params['userid']) {
            throw new \RuntimeException('No client id defined when calling getContact()');
        }

        if (!class_exists(Capsule::class)) {
            return null;
        }

        return $this->contactId = Capsule::table('mod_ficora')
            ->where('client_id', '=', $this->params['userid'])->value('contact_id');
    }


    /**
     * @return string
     * @throws \Metaregistrar\EPP\eppException
     */
    protected function getOrCreateContact(): string
    {
        return $this->contactId = $this->getContact() ?? $this->createContact();
    }

    /**
     * @param eppContactHandle $handle
     * @return ficoraEppInfoContactResponse
     * @throws \Metaregistrar\EPP\eppException
     */
    protected function contactInfo(eppContactHandle $handle): ficoraEppInfoContactResponse
    {
        /* @var $response ficoraEppInfoContactResponse */
        $response = $this->connection->request(
            new ficoraEppInfoContactRequest(
                $handle
            )
        );

        return $response;
    }

    protected function newRegistrantHandle(): eppContact
    {
        $extraInformation = $this->gatherExtraInformation($this->params['ficora_custom_fields_strategy']);

        return new eppContact(
            new ficoraEppContactPostalInfo(
                $extraInformation->registrantType > 0
                    ? $this->params['companyname']
                    : "{$this->params['firstname']} {$this->params['lastname']}",
                $this->params['city'],
                $this->params['countrycode'],
                $extraInformation->registrantType  > 0
                    ? $this->params['companyname']
                    : null,
                $this->params['address1'],
                null,
                $this->params['postcode'],
                eppContact::TYPE_LOC,
                $this->params['firstname'],
                $this->params['lastname'],
                $this->params['country'] === 'Finland',
                $extraInformation->registrantType  > 0
                    ? null
                    : $extraInformation->idNumber,
                null,
                $extraInformation->registrantType  > 0
                    ? $extraInformation->registerNumber
                    : null
            ),
            $this->params['email'],
            $this->params['fullphonenumber']
        );
    }

    /**
     * @return string
     * @throws \Metaregistrar\EPP\eppException
     */
    protected function createContact(): string
    {
        $contact = new ficoraEppCreateContactRequest($this->newRegistrantHandle());
        $extraInformation = $this->gatherExtraInformation($this->params['ficora_custom_fields_strategy']);

        $contact->setRole(ficoraEppCreateContactRequest::FI_CONTACT_ROLE_REGISTRANT);
        $contact->setType($extraInformation->registrantType);
        $contact->setLegalemail($this->params['email']);
        $contact->setIsfinnish($this->params['countryname'] === 'Finland');

        if($extraInformation->registrantType > 0) {
            $contact->setRegisternumber($extraInformation->registerNumber);
            $contact->setLastname($this->params['companyname']);
        } else {
            $contact->setFirstname($this->params['firstname']);
            $contact->setLastname($this->params['lastname']);
            if ($this->params['countryname'] === 'Finland') {
                $contact->setIdentity($extraInformation->idNumber);
            } else {
                $contact->setBirthdate($extraInformation->birthdate);
            }
        }

        /* @var $response eppCreateContactResponse */
        $response = $this->connection->request($contact);

        if (class_exists('\Illuminate\Database\Capsule\Manager')) {
            Capsule::table('mod_ficora')->insert([
                'contact_id' => $response->getContactId(),
                'client_id' => $this->params['userid']
            ]);
        }

        return $this->contactId = $response->getContactId();
    }

    /**
     * @param array $ns
     * @return ficoraEppDomain
     * @throws \Metaregistrar\EPP\eppException
     */
    protected function eppDomain(array $ns): ficoraEppDomain
    {
        return new ficoraEppDomain(
            $this->params['domainname'], $this->getOrCreateContact(),
            ($this->params['ficora_contact'] ?? null)
                ? [new eppContactHandle($this->params['ficora_contact'], eppContactHandle::CONTACT_TYPE_TECH)]
                : null,
            $ns,
            $this->params['regperiod'],
            $this->generateStrongPassword()
        );
    }

    protected function parseNameservers(): array
    {
        return array_values(
            array_filter($this->params,
                function ($key) {
                    return in_array($key, ['ns1', 'ns2', 'ns3', 'ns4', 'ns5']);
                }, ARRAY_FILTER_USE_KEY
            )
        );
    }

    protected function generateStrongPassword($length = 16): string
    {
        $sets = [
            'abcdefghjkmnpqrstuvwxyz',
            'ABCDEFGHJKMNPQRSTUVWXYZ',
            '1234567890',
            '.',
        ];

        $all = '';
        $password = '';

        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);

        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        return $password;
    }

    protected function gatherExtraInformation($strategy)
    {
        switch($strategy) {
            case 0:
                return (object) [
                    'registrantType' => (int) ($this->params['additionalfields']['registrant_type'] ?? 0),
                    'idNumber' => $this->params['additionalfields']['idNumber'] ?? null,
                    'registerNumber' => $this->params['additionalfields']['registerNumber'] ?? null,
                    'birthdate' => $this->params['additionalfields']['birthdate'] ?? null,
                ];
            case 1:
                return (object) [
                    'registrantType' => $this->params['companyname'] ? 1 : 0,
                    'idNumber' => $this->params['customfields' . $this->params['ficora_personid_field']] ?? null,
                    'registerNumber' => $this->params['customfields' . $this->params['ficora_companyid_field']] ?? null,
                    'birthdate' => '1990-01-01',
                ];
            default:
                throw new \RuntimeException(
                    "Custom field strategy {$this->params['ficora_custom_fields_strategy']} not recognized.");
        }
    }
}

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function ficoraepp_MetaData()
{
    return [
        'DisplayName' => 'Ficora EPP',
        'APIVersion' => '1.1',
    ];
}

/**
 * Define registrar configuration options.
 *
 * The values you return here define what configuration options
 * we store for the module. These values are made available to
 * each module function.
 *
 * You can store an unlimited number of configuration settings.
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * @return array
 */
function ficoraepp_getConfigArray()
{
    if (!Capsule::schema()->hasTable('mod_ficora')) {
        Capsule::schema()->create('mod_ficora', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('contact_id');
            $table->integer('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('tblclients')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    $customFields = collect(Capsule::table('tblcustomfields')
        ->where('type', '=', 'client')
        ->where('fieldtype', '=', 'text')
        ->select('id', 'fieldname')->get())->pluck('fieldname', 'id')->toArray();

    return [
        // Friendly display name for the module
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Ficora EPP',
        ],
        'Description' => [
            'Type' => 'System',
            'Value' => 'Registrar module for Finnish Communications Regulatory Authority\'s EPP API',
        ],
        'ficora_username' => [
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '',
            'Description' => 'Username for EPP API',
        ],
        'ficora_password' => [
            'FriendlyName' => 'API Password',
            'Type' => 'password',
            'Size' => '256',
            'Default' => '',
            'Description' => 'Password for EPP API',
        ],
        'ficora_contact' => [
            'FriendlyName' => 'Tech Contact',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '',
            'Description' => 'Tech Contact handle to include with domain',
        ],
        'ficora_hostname' => [
            'FriendlyName' => 'API Hostname',
            'Type' => 'text',
            'Size' => '256',
            'Default' => 'epp.domain.fi',
            'Description' => 'The address used for API connections',
        ],
        'ficora_port' => [
            'FriendlyName' => 'API port',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '700',
            'Description' => 'The port used for API connections',
        ],
        'ficora_certpath' => [
            'FriendlyName' => 'Certificate path',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '',
            'Description' => 'Path for authentication certificate',
        ],
        'ficora_certpass' => [
            'FriendlyName' => 'Certificate password',
            'Type' => 'password',
            'Size' => '256',
            'Default' => '',
            'Description' => 'Password for the authentication certificate',
        ],
        'ficora_companyid_field' => [
            'FriendlyName' => 'Company ID field',
            'Type' => 'dropdown',
            'Options' => $customFields,
            'Description' => 'Custom field for company ID',
        ],
        'ficora_personid_field' => [
            'FriendlyName' => 'Person ID field',
            'Type' => 'dropdown',
            'Options' => $customFields,
            'Description' => 'Custom field for person id',
        ],
        'ficora_timeout' => [
            'FriendlyName' => 'Timeout',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '60',
            'Description' => 'Timeout for connection and read operation from API',
        ],
        'ficora_retry' => [
            'FriendlyName' => 'Retry amount',
            'Type' => 'text',
            'Size' => '256',
            'Default' => '1',
            'Description' => 'The amount to retry timed out read opreations',
        ],
        'ficora_cache_ttl' => [
            'FriendlyName' => 'Cache TTL',
            'Type' => 'text',
            'Size' => '256',
            'Default' => 60 * 60 * 24, // 24 hours
            'Description' => 'Time To Live for caching of contact, nameserver etc. data',
        ],
        'ficora_custom_fields_strategy' => [
            'FriendlyName' => 'Additional fields strategy',
            'Type' => 'dropdown',
            'Options' => [
                'Additional fields',
                'Profile fields ',
            ],
            'Default' => 0,
            'Description' =>
                'The strategy used for gathering the required extra information from user for .fi registration',
        ],
        'ficora_debug' => [
            'FriendlyName' => 'Enable debug mode',
            'Type' => 'yesno',
            'Description' => 'Module will write debug info the the webroot in debug.txt file',
        ],
    ];
}

/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function ficoraepp_RegisterDomain($params): array
{
    try {
        (new FicoraModule($params))->register();

        return [
            'success' => true,
        ];
    } catch (\Exception $e) {

        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );

        return [
            'error' => $e->getMessage(),
        ];
    }


}

/**
 * Initiate domain transfer.
 *
 * Attempt to create a domain transfer request for a given domain.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain transfer order
 * * When a pending domain transfer order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function ficoraepp_TransferDomain($params)
{
    try {
        (new FicoraModule($params))->transfer();

        return [
            'success' => true,
        ];
    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Renew a domain.
 *
 * Attempt to renew/extend a domain for a given number of years.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain renewal order
 * * When a pending domain renewal order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function ficoraepp_RenewDomain($params)
{
    try {
        (new FicoraModule($params))->renew();

        return [
            'success' => true,
        ];

    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 * @throws \Psr\SimpleCache\InvalidArgumentException
 */
function ficoraepp_GetNameservers($params)
{
    try {
        if (!$item = FicoraEppCache::get()->get("{$params['domainname']}_nameservers")) {
            $item = (new FicoraModule($params))->getNameservers();
            FicoraEppCache::get()->set("{$params['domainname']}_nameservers", $item, (int) $params['ficora_cache_ttl']);
        }

        return $item;
    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 * @throws \Psr\SimpleCache\InvalidArgumentException
 */
function ficoraepp_SaveNameservers($params)
{
    try {
        (new FicoraModule($params))->updateNameservers();

        FicoraEppCache::get()->set("{$params['domainname']}_nameservers", array_filter($params,
            function ($key) {
                return in_array($key, ['ns1', 'ns2', 'ns3', 'ns4', 'ns5']);
            }, ARRAY_FILTER_USE_KEY
        ), (int) $params['ficora_apcu_ttl']);

        return [
            'success' => true,
        ];

    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Get the current WHOIS Contact Information.
 *
 * Should return a multi-level array of the contacts and name/address
 * fields that be modified.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 * @throws \Psr\SimpleCache\InvalidArgumentException
 */
function ficoraepp_GetContactDetails($params)
{
    try {
        if (!$item = FicoraEppCache::get()->get("{$params['domainname']}_contacts")) {
            $item = (new FicoraModule($params))->getContacts();
            FicoraEppCache::get()->set("{$params['domainname']}_contacts", $item, (int) $params['ficora_cache_ttl']);
        }

        return $item;
    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Update the WHOIS Contact Information for a given domain.
 *
 * Called when a change of WHOIS Information is requested within WHMCS.
 * Receives an array matching the format provided via the `GetContactDetails`
 * method with the values from the users input.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 * @throws \Psr\SimpleCache\InvalidArgumentException
 */
function ficoraepp_SaveContactDetails($params)
{
    try {
        (new FicoraModule($params))->saveContact();
        FicoraEppCache::get()->delete("{$params['domainname']}_contacts");

        return [
            'success' => true,
        ];

    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Request EEP Code.
 *
 * Supports both displaying the EPP Code directly to a user or indicating
 * that the EPP Code will be emailed to the registrant.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 *
 * @throws \Psr\SimpleCache\InvalidArgumentException
 */
function ficoraepp_GetEPPCode($params)
{
    try {
        if (!$item = FicoraEppCache::get()->get("{$params['domainname']}_epp")) {
            $item = (new FicoraModule($params))->epp();
            FicoraEppCache::get()->set("{$params['domainname']}_epp", $item, (int) $params['ficora_cache_ttl']);
        }

        return ['eppcode' => $item];
    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Sync Domain Status & Expiration Date.
 *
 * Domain syncing is intended to ensure domain status and expiry date
 * changes made directly at the domain registrar are synced to WHMCS.
 * It is called periodically for a domain.
 *
 * @param array $params common module parameters
 *
 * @see http://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function ficoraepp_Sync($params)
{
    try {
        $info = (new FicoraModule($params))->info();

        return [
            'expirydate' => (new DateTime($info->getDomainExpirationDate()))->format('Y-m-d'), // Format: YYYY-MM-DD
        ];

    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Complete a domain transfer
 *
 * Domains transfered via Ficora will be transferred immediately. This is why normal WHMCS Transfer Sync functionality
 * will not suffice.
 *
 * This function will activate the domain from Pending Transfer state correctly
 *
 * @param array $params common module parameters
 * @return array
 */
function ficoraepp_CompleteTransfer($params)
{
    try {
        $date = (new DateTime((new FicoraModule($params))->info()->getDomainExpirationDate()))->format('Y-m-d');

        update_query('tbldomains', [
            'nextduedate' => $date,
            'expirydate' => $date,
            'status' => 'Active',
        ], ['id' => $params['domainid']]);

        /** @noinspection UnusedFunctionResultInspection */
        sendMessage('Domain Transfer Completed', $params['domainid']);

        /** @noinspection UnusedFunctionResultInspection */
        run_hook('DomainTransferCompleted', [
            'domainId' => $params['domainid'],
            'domain' => $params['domainname'],
            'registrationPeriod' => $params['regperiod'],
            'expiryDate' => $date,
            'registrar' => 'ficoraepp'
        ]);

        return [
            'success' => true
        ];

    } catch (\Exception $e) {
        logModuleCall(
            'ficoraepp',
            __FUNCTION__,
            $e instanceof \Metaregistrar\EPP\eppException ? $e->getLastCommand() . print_r($params, true) : $params,
            $e->getMessage(),
            $e->getMessage() . "\n" . $e->getTraceAsString()
        );
        return [
            'error' => $e->getMessage(),
        ];
    }
}