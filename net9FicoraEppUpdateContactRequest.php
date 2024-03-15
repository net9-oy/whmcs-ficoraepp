<?php
namespace FicoraEpp;

use Metaregistrar\EPP\eppContact;
use Metaregistrar\EPP\eppUpdateContactRequest;
use Metaregistrar\EPP\ficoraEppContactPostalInfo;

class net9FicoraEppUpdateContactRequest extends eppUpdateContactRequest
{
    private $ficoraIdentityType;

    public function __construct(
        $objectname,
        int $ficoraIdentityType,
        $addinfo = null,
        $removeinfo = null,
        $updateinfo = null,
        $namespacesinroot = true
    ) {
        $this->ficoraIdentityType = $ficoraIdentityType;
        parent::__construct($objectname, $addinfo, $removeinfo, $updateinfo, $namespacesinroot);
        $this->addFicoraExtension();
        $this->addSessionId();
    }

    private function addFicoraExtension()
    {
        $this->contactobject->setAttribute('xmlns:contact', 'urn:ietf:params:xml:ns:contact-1.0');
    }

    /**
     * @param string $contactid
     * @param eppContact $addInfo
     * @param eppContact $removeInfo
     * @param eppContact $updateInfo
     */
    public function updateContact($contactid, $addInfo, $removeInfo, $updateInfo)
    {
        parent::updateContact($contactid, $addInfo, $removeInfo, $updateInfo);

        /** @var ficoraEppContactPostalInfo $postalInfo */
        $postalInfo = $updateInfo->getPostalInfo(0);
        $contactPostalInfo = $this->getElementsByTagName('contact:postalInfo')->item(0);

        /**
         * This information as of February 2021 is required by Traficom API by update queries for no reason (these
         * fields are not allwed to be updated regardless)
         *
         * There is no Metaregistrar EPP fix yet, pull request after this one is validated
         */
        $contactPostalInfo->appendChild($this->createElement('contact:isfinnish', $postalInfo->getIsFinnish()));

        if ($postalInfo->getFirstName() && $this->ficoraIdentityType === 0) {
            $contactPostalInfo->appendChild($this->createElement('contact:firstname', $postalInfo->getFirstName()));
        }

        if ($postalInfo->getLastName() && $this->ficoraIdentityType === 0) {
            $contactPostalInfo->appendChild($this->createElement('contact:lastname', $postalInfo->getLastName()));
        }

        if ($postalInfo->getIdentity() && $this->ficoraIdentityType === 0) {
            $contactPostalInfo->appendChild($this->createElement('contact:identity', $postalInfo->getIdentity()));
        }

        if ($postalInfo->getRegisterNumber() && $this->ficoraIdentityType !== 0) {
            $contactPostalInfo->appendChild($this->createElement('contact:registernumber',
                $postalInfo->getRegisterNumber()));
        }

        $this->getElementsByTagName('contact:chg')->item(0)
            ->appendChild($this->createElement('contact:type', $this->ficoraIdentityType));

        /**
         * Quick fix: remove empty contact:org as this breaks Traficom API since February 2021
         *
         * This is fixed in newer versions of Metaregistrar EPP, but the newer versions have other issues that are hard
         * to fix at current date
         */
        if(!$postalInfo->getOrganisationName() && $org = $this->getElementsByTagName('contact:org')->item(0)) {
            $org->parentNode->removeChild($org);
        }
    }
}
