<?php
namespace GusApi\Adapter\Soap;

use GusApi\Adapter\AdapterInterface;
use GusApi\Adapter\Soap\Exception\NoDataException;
use GusApi\Client\SoapClient;
use GusApi\RegonConstantsInterface;

/**
 * Class SoapAdapter SoapAdapter for
 * @package GusApi\Adapter\Soap
 */
class SoapAdapter implements AdapterInterface
{
    /**
     * @var SoapClient gus soap client
     */
    protected $client;

    /**
     * @var string base url address
     */
    protected $baseUrl;

    /**
     * @var string base address to http header
     */
    protected $address;

    /**
     * Create Gus soap adapter
     *
     * @param string $baseUrl
     * @param string $address
     */
    public function __construct($baseUrl, $address)
    {
        $this->baseUrl = $baseUrl;
        $this->address = $address;

        $this->client = new SoapClient($this->baseUrl, $address, array(
            'soap_version' => SOAP_1_2,
            'trace' => true,
            'style' => SOAP_DOCUMENT
        ));
    }

    /**
     * @inheritdoc
     */
    public function login($userKey)
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Zaloguj', $this->address);
        $result = $this->client->Zaloguj(array(
            RegonConstantsInterface::PARAM_USER_KEY => $userKey
        ));

        $sid = $result->ZalogujResult;
        return $sid;
    }

    /**
     * @inheritdoc
     */
    public function logout($sid)
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/Wyloguj', $this->address);
        $result = $this->client->Wyloguj(array(
            RegonConstantsInterface::PARAM_SESSION_ID => $sid
        ));

        return $result->WylogujResult;
    }

    /**
     * @inheritdoc
     */
    public function getCaptcha($sid)
    {
        $this->prepareSoapHeader('http://CIS/BIR/2014/07/IUslugaBIR/PobierzCaptcha', $this->address, $sid);
        $result = $this->client->PobierzCaptcha();

        return $result->PobierzCaptchaResult;
    }

    /**
     * @inheritdoc
     */
    public function checkCaptcha($sid, $captcha)
    {
        $this->prepareSoapHeader('http://CIS/BIR/2014/07/IUslugaBIR/SprawdzCaptcha', $this->address, $sid);
        $result = $this->client->SprawdzCaptcha(array(
            RegonConstantsInterface::PARAM_CAPTCHA => $captcha
        ));

        return $result->SprawdzCaptchaResult;
    }

    /**
     * @inheritdoc
     */
    public function search($sid, array $parameters)
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DaneSzukaj', $this->address, $sid);
        $result = $this->client->DaneSzukaj(array(
            RegonConstantsInterface::PARAM_SEARCH => $parameters
        ));

        try {
            $result = $this->decodeResponse($result->DaneSzukajResult);
        } catch (\Exception $e) {
            throw new NoDataException("No data found for");
        }

        return $result->dane;
    }

    /**
     * @inheritdoc
     */
    public function getFullData($sid, $regon, $reportType)
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DanePobierzPelnyRaport', $this->address, $sid);
        $result = $this->client->DanePobierzPelnyRaport(array(
            RegonConstantsInterface::PARAM_REGOM => $regon,
            RegonConstantsInterface::PARAM_REPORT_NAME => $reportType
        ));

        try {
            $result = $this->decodeResponse($result->DanePobierzPelnyRaportResult);
        } catch (\Exception $e) {
            throw new NoDataException("No data found");
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getValue($sid, $param)
    {
        $this->prepareSoapHeader('http://CIS/BIR/2014/07/IUslugaBIR/GetValue', $this->address, $sid);
        $result = $this->client->GetValue(array(
            RegonConstantsInterface::PARAM_PARAM_NAME => $param
        ));

        return $result->GetValueResult;
    }

    /**
     * @inheritdoc
     */
    public function getMessage()
    {
        $this->prepareSoapHeader('http://CIS/BIR/PUBL/2014/07/IUslugaBIRzewnPubl/DaneKomunikat', $this->address);
        $result = $this->client->DaneKomunikat();

        return $result->DaneKomunikatResult;
    }

    /**
     * Prepare soap necessary header
     *
     * @param string $action
     * @param string $to
     * @param null|string $sid session id
     */
    protected function prepareSoapHeader($action, $to, $sid = null)
    {
        $this->clearHeader();
        $header[] = $this->setHeader('http://www.w3.org/2005/08/addressing', 'Action', $action);
        $header[] = $this->setHeader('http://www.w3.org/2005/08/addressing', 'To', $to);
        $this->client->__setSoapHeaders($header);

        if ($sid !== null) {
            $this->client->__setHttpHeader(array(
                'header' => 'sid: '.$sid
            ));
        }
    }

    /**
     * Clear soap header
     */
    protected function clearHeader()
    {
        $this->client->__setSoapHeaders(null);
    }

    /**
     * Set soap header
     *
     * @param $namespace
     * @param $name
     * @param null $data
     * @param bool|false $mustUnderstand
     * @return \SoapHeader
     */
    protected function setHeader($namespace, $name, $data = null, $mustUnderstand = false)
    {
        return new \SoapHeader($namespace, $name, $data, $mustUnderstand);
    }

    /**
     * @param string $response xml string
     * @return \SimpleXMLElement
     */
    protected function decodeResponse($response)
    {
        return new \SimpleXMLElement($response);
    }
}