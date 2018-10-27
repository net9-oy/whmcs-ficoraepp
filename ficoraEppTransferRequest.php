<?php
namespace FicoraEpp;

use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppRequest;
use Metaregistrar\EPP\eppTransferRequest;

class ficoraEppTransferRequest extends eppRequest
{
    /**
     * @param eppDomain $domain
     * @throws eppException
     */
    public function __construct($domain)
    {
        parent::__construct();
        $transfer = $this->createElement('transfer');
        $transfer->setAttribute('op', eppTransferRequest::OPERATION_REQUEST);
        $this->domainobject = $this->createElement('domain:transfer');
        $this->domainobject->setAttribute('xmlns:domain','urn:ietf:params:xml:ns:domain-1.0');
        $this->domainobject->appendChild($this->createElement('domain:name', $domain->getDomainname()));
        if (strlen($domain->getAuthorisationCode())) {
            $authinfo = $this->createElement('domain:authInfo');
            $authinfo->appendChild($this->createElement('domain:pw', $domain->getAuthorisationCode()));
            $this->domainobject->appendChild($authinfo);
        }
        $transfer->appendChild($this->domainobject);

        $ns = $this->createElement('domain:ns');
        $nameservers = $domain->getHosts();
        foreach ($nameservers as $nsRecord) {
            /**
             * @var eppHost $nsRecord
             */
            if ($nsRecord->getHostname()) {
                $hostObj = $this->createElement('domain:hostObj', $nsRecord->getHostname());
                $ns->appendChild($hostObj);
            } else {
                throw new eppException("nsRecord has no hostname on metaregEppTransferExtendedRequest");
            }
        }
        $this->domainobject->appendChild($ns);

        $this->getCommand()->appendChild($transfer);
        $this->addSessionId();
    }
}