<?php

namespace CodeGreenCreative\SamlIdp\Jobs;

use LightSaml\Helper;
use LightSaml\SamlConstants;
use LightSaml\Model\Protocol\Status;
use LightSaml\Model\Assertion\Issuer;
use LightSaml\Model\Assertion\NameID;
use LightSaml\Model\Protocol\StatusCode;
use Illuminate\Foundation\Bus\Dispatchable;
use LightSaml\Model\Protocol\LogoutRequest;
use LightSaml\Model\Protocol\LogoutResponse;
use LightSaml\Model\XmlDSig\SignatureWriter;
use LightSaml\Model\Context\DeserializationContext;
use CodeGreenCreative\SamlIdp\Traits\PerformsSingleSignOn;

class SamlSlo
{
    use Dispatchable;
    use PerformsSingleSignOn;

    private $spId;

    private $destination;

    private $logout_request;

    /**
     * Create a new SamlSlo instance.
     *
     * @param string $spId The base64 encoded ACS URL identifying the service provider
     */
    public function __construct(string $spId)
    {
        $this->spId = $spId;
        $this->init();
    }

    /**
     * [handle description]
     *
     * @param  [type] $sp [description]
     * @return [type]     [description]
     */
    public function handle()
    {
        $this->setDestination();
        // We are receiving a Logout Request
        if (request()->filled('SAMLRequest')) {
            $xml = gzinflate(base64_decode(request('SAMLRequest')));
            $deserializationContext = new DeserializationContext;
            $deserializationContext->getDocument()->loadXML($xml);
            // Get the final destination
            session()->put('RelayState', request('RelayState'));
        } elseif (request()->filled('SAMLResponse')) {
            $xml = gzinflate(base64_decode(request('SAMLResponse')));
            $deserializationContext = new DeserializationContext;
            $deserializationContext->getDocument()->loadXML($xml);
        }

        // Send the request to log out
        return $this->request();
    }

    /**
     * [response description]
     *
     * @return [type] [description]
     */
    public function response()
    {
        $this->response = (new LogoutResponse)
            ->setIssuer(new Issuer($this->issuer))
            ->setID(Helper::generateID())
            ->setIssueInstant(new \DateTime)
            ->setDestination($this->destination)
            ->setInResponseTo($this->logout_request->getId())
            ->setStatus(new Status(new StatusCode('urn:oasis:names:tc:SAML:2.0:status:Success')));

        if (config('samlidp.messages_signed')) {
            $this->response->setSignature(
                new SignatureWriter($this->certificate, $this->private_key, $this->digest_algorithm)
            );
        }

        return $this->send(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);
    }

    /**
     * [request description]
     *
     * @return [type] [description]
     */
    public function request()
    {
        $this->response = (new LogoutRequest)
            ->setIssuer(new Issuer($this->issuer))
            ->setNameID(new NameID(Helper::generateID(), SamlConstants::NAME_ID_FORMAT_TRANSIENT))
            ->setID(Helper::generateID())
            ->setIssueInstant(new \DateTime)
            ->setDestination($this->destination);

        if (config('samlidp.messages_signed')) {
            $this->response->setSignature(
                new SignatureWriter($this->certificate, $this->private_key, $this->digest_algorithm)
            );
        }

        return $this->send(SamlConstants::BINDING_SAML2_HTTP_REDIRECT);
    }

    private function setDestination()
    {
        $destination = $this->samlServiceProviderConfig->getLogoutUrl($this->spId);

        if (empty($destination)) {
            throw new \RuntimeException(
                "Service provider {$this->spId} does not have a logout URL configured."
            );
        }

        $this->destination = $destination;
    }
}
