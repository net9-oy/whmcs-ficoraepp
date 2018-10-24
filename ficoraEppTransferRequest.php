<?php
namespace FicoraEpp;

use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppHost;

class ficoraEppTransferRequest extends \Metaregistrar\EPP\eppTransferRequest
{
    /**
     * @param string $operation
     * @param eppDomain $object
     * @throws eppException
     */
    public function __construct($operation, $object)
    {
        parent::__construct($operation, $object);
        $ns = $this->createElement('domain:ns');
        $nameservers = $object->getHosts();
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
        $this->domainObject->appendChild($ns);
        $this->addSessionId();
    }
}