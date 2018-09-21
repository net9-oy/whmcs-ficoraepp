<?php
require __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

class FicoraEPPTest extends TestCase
{
    // Randomly generated EU VAT numbers
    public static $foreignCompanyId = [
        'MT10126313',
        'MT10271622',
        'MT10365719',
        'MT10414318',
        'MT10601519',
    ];

    // Randomly generated company IDs
    public static $companyId = [
        '2231508-9',
        '2423241-2',
        '4134373-9',
        '8811421-2',
        '2073316-4',
        '7624385-0',
    ];

    // Randomly generated social security IDs
    public static $personId = [
        '260997-9955',
        '260997-941D',
        '260997-9036',
        '260997-9590',
        '260997-923U',
    ];

    public static function providerCoreFunctionNames()
    {
        return [
            ['RegisterDomain'],
            ['TransferDomain'],
            ['RenewDomain'],
            ['GetNameservers'],
            ['SaveNameservers'],
            ['GetContactDetails'],
            ['SaveContactDetails'],
        ];
    }

    public static function randomPerson()
    {
        $personIdField = rand(1, 9);

        return array_merge(
            static::sharedParameters(),
            [
                'domainname' => 'testdomain' . rand(10, 100000) . '.fi',
                'firstname' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10),
                'lastname' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10),
                'address1' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10) . ' ' .
                    rand(1, 40),
                'city' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10),
                'postcode' => rand(10000, 40000),
                'email' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10) . '@' .
                    'google.com',
                'country' => 'Finland',
                'countrycode' => 'FI',
                'phonenumber' => '+358.' . rand(100000000, 999999999),
                'companyname' => '',
                'state' => substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10),
                'regperiod' => 1,
                'ns1' => 'ns1.' . substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10) .
                    '.info',
                'ns2' => 'ns2.' . substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10) .
                    '.info',
                'userid' => 1,
                "customfields{$personIdField}" => static::$personId[rand(0, count(static::$personId) - 1)],
                'ficora_personid_field' => $personIdField,
                'additionalfields' =>  [
                    'idNumber' => static::$personId[rand(0, count(static::$personId) - 1)],
                    'registrant_type' => 0,
                ],
                'ficora_companyid_field' => 0,
            ]
        );
    }

    public static function randomForeigner()
    {
        $params = static::randomPerson();
        $params['country'] = 'Malta';
        $params['countrycode'] = 'MT';
        $params['additionalfields']['idNumber'] = null;
        $params['additionalfields']['birthdate'] = '1990-01-01';

        return $params;
    }

    public static function randomCompany()
    {
        $companyIdField = rand(1, 9);
        $params = static::randomPerson();
        $params['companyname'] = substr(str_shuffle('abcdefghijklmopqrstuvwxyz'), 0, 10);
        $params["customfields{$companyIdField}"] =  static::$companyId[rand(0, count(static::$companyId) - 1)];
        $params['additionalfields']['idNumber'] = null;
        $params['additionalfields']['registrant_type'] = 1;
        $params['additionalfields']['registerNumber'] = static::$companyId[rand(0, count(static::$companyId) - 1)];
        $params['ficora_companyid_field'] = $companyIdField;

        return $params;
    }

    public static function randomForeignCompany()
    {
        $params = static::randomCompany();
        $params['country'] = 'Malta';
        $params['countrycode'] = 'MT';
        $params['additionalfields']['registerNumber'] =
            static::$foreignCompanyId[rand(0, count(static::$foreignCompanyId) - 1)];
        $params["customfields{$params['ficora_companyid_field']}"] =
            static::$foreignCompanyId[rand(0, count(static::$foreignCompanyId) - 1)];

        return $params;
    }

    public static function providerTestWHMCSParametersNewDomains()
    {
        return [
            [
                array_merge(
                    static::randomPerson(),
                    ['ficora_custom_fields_strategy' => 0]
                ),
            ],
            [
                array_merge(
                    static::randomPerson(),
                    ['ficora_custom_fields_strategy' => 1]
                ),
            ],
            [
                array_merge(
                    static::randomCompany(),
                    ['ficora_custom_fields_strategy' => 0]
                ),
            ],
            [
                array_merge(
                    static::randomCompany(),
                    ['ficora_custom_fields_strategy' => 1]
                ),
            ],
            [
                array_merge(
                    static::randomForeigner(),
                    ['ficora_custom_fields_strategy' => 0]
                ),
            ],
            [
                array_merge(
                    static::randomForeigner(),
                    ['ficora_custom_fields_strategy' => 1]
                ),
            ],
            [
                array_merge(
                    static::randomForeignCompany(),
                    ['ficora_custom_fields_strategy' => 0]
                ),
            ],
            [
                array_merge(
                    static::randomForeignCompany(),
                    ['ficora_custom_fields_strategy' => 1]
                )
            ],
        ];
    }

    public function providerTestWHMCSDomainRenew()
    {
        return [current(self::providerTestWHMCSParametersNewDomains())];
    }

    public static function providerTestWHMCSParametersExistingDomains()
    {
        return [
            [
                array_merge(
                    static::sharedParameters(),
                    [
                        'domainname' => 'testdomain2325.fi',
                        'regperiod' => 1,
                    ]
                )
            ],
        ];
    }

    public static function sharedParameters()
    {
        return [
            'ficora_port' => 700,
            'ficora_hostname' => 'epptest.ficora.fi',
            'ficora_username' => getenv('EPP_USERNAME'),
            'ficora_password' => getenv('EPP_PASSWORD'),
            'ficora_certpath' => getenv('EPP_CERTPATH'),
            'ficora_certpass' => getenv('EPP_CERTPASSWORD'),
            'ficora_timeout' => 5,
            'ficora_retry' => 1,
            //'debug' => true,
        ];
    }

    /**
     * Test Core Module Functions Exist
     *
     * This test confirms that the functions we recommend for all registrar
     * modules are defined for the sample module
     *
     * @param $moduleName
     *
     * @dataProvider providerCoreFunctionNames
     */
    public function testCoreModuleFunctionsExist($moduleName)
    {
        $this->assertTrue(function_exists('ficoraepp_' . $moduleName));
    }

    /**
     * Test Ficora connection
     *
     * This test confirms that Ficora EPP is able to establish a connection
     * successfully with the registar using the parameters provided.
     *
     * @param array $params
     *
     * @dataProvider providerTestWHMCSParametersExistingDomains
     */
    public function testConnection(array $params)
    {
        $this->assertTrue((new FicoraModule($params)) instanceof FicoraModule);
    }

    /**
     * Test nameserver fetching
     *
     * This test confirms that Ficora EPP is able to fetch nameservers
     * for an already existing domain.
     *
     * @param array $params
     *
     * @dataProvider providerTestWHMCSParametersExistingDomains
     *
     * @depends      testConnection
     */
    public function testFetchNameservers(array $params)
    {
        $nameservers = (new FicoraModule($params))->getNameservers();

        $this->assertArrayHasKey('ns1', $nameservers);
        $this->assertArrayHasKey('ns2', $nameservers);
    }


    /**
     * Test EPP password generation
     *
     * This test confirms that Ficora EPP is able to generate a
     * transfer password for the domain.
     *
     * @param array $params
     *
     * @dataProvider providerTestWHMCSParametersExistingDomains
     *
     * @depends      testConnection
     */
    public function testTransferKeyGeneration(array $params)
    {
        $this->assertGreaterThan(4, strlen((new FicoraModule($params))->epp()));
    }

    /**
     * Test domain creation
     *
     * This test will assert that a domain can be registered
     * successfully.
     *
     * @param array @params
     *
     * @dataProvider providerTestWHMCSParametersNewDomains
     *
     * @depends      testConnection
     */
    public function testDomainRegistration(array $params)
    {
        $module = new FicoraModule($params);
        $module->register();
        $createDate = new DateTime($module->info()->getDomainCreateDate());
        $this->assertTrue($createDate->format('Y') == (new DateTime())->format('Y')
            && $createDate->format('m') == (new DateTime())->format('m')
            && $createDate->format('d') == (new DateTime())->format('d'));
    }

    /**
     * Test domain renew
     *
     * This test will assert that a domain can be renewed
     * successfully.
     *
     * @param array @params
     *
     * @dataProvider providerTestWHMCSDomainRenew
     *
     * @depends      testDomainRegistration
     */
    public function testDomainRenew(array $params)
    {
        $module = new FicoraModule($params);
        $module->register();
        $oldExpDate = new DateTime($module->info()->getDomainExpirationDate());
        $module->renew();
        $expDate = new DateTime($module->info()->getDomainExpirationDate());
        $this->assertTrue($oldExpDate->format('Y') < $expDate->format('Y'));
    }
}