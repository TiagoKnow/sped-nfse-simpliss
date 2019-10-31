<?php

namespace NFePHP\NFSeSimpliss\Common;

/**
 * Auxiar Tools Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeEGoverne
 * @copyright NFePHP Copyright (c) 2008-2019
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>, Sidnei L. Baumgartenn <sidnei at sbaum dot com dot br>
 * @link      https://github.com/prsidnei/sped-nfse-simpliss for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\NFSeSimpliss\RpsInterface;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\NFSeSimpliss\Common\Signer;
use NFePHP\NFSeSimpliss\Common\Soap\SoapInterface;
use NFePHP\NFSeSimpliss\Common\Soap\SoapCurl;

class Tools
{
    public $lastRequest;

    protected $config;
    protected $prestador;
    protected $certificate;
    protected $wsobj;
    protected $soap;
    protected $environment;

    protected $urls = [
        '3306305' => [
            'municipio' => 'Volta Redonda',
            'uf' => 'RJ',
            'homologacao' => '',
            'producao' => 'http://wsvoltaredonda.simplissweb.com.br/nfseservice.svc',
            'version' => '1.26',
            'msgns' => 'http://www.sistema.com.br/Nfse/arquivos/nfse_3.xsd',
            'soapns' => 'http://www.sistema.com.br/Sistema.Ws.Nfse'
        ]
    ];

    /**
     * Constructor
     * @param string $config
     * @param Certificate $cert
     */
    public function __construct($config, Certificate $cert)
    {
        $this->config = json_decode($config);
        $this->certificate = $cert;
        $this->buildPrestadorTag();
        $wsobj = $this->urls;
        $this->wsobj = json_decode(json_encode($this->urls[$this->config->cmun]));
        $this->environment = 'producao';
        if ($this->config->tpamb === 2) {
            $this->environment = 'homologacao';
        }
    }

    /**
     * SOAP communication dependency injection
     * @param SoapInterface $soap
     */
    public function loadSoapClass(SoapInterface $soap)
    {
        $this->soap = $soap;
    }

    /**
     * Build tag Prestador
     */
    protected function buildPrestadorTag()
    {
        $this->prestador = "<Prestador>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "</Prestador>";
    }

    /**
     * Sign XML passing in content
     * @param string $content
     * @param string $tagname
     * @param string $mark
     * @return string XML signed
     */
    public function sign($content, $tagname, $mark)
    {
        $xml = Signer::sign(
            $this->certificate,
            $content,
            $tagname,
            $mark
        );
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($xml);
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Send message to webservice
     * @param string $message
     * @param string $operation
     * @return string XML response from webservice
     */
    public function send($message, $operation)
    {
        $action = "{$this->wsobj->soapns}/$operation";
        $url = $this->wsobj->producao;
        if ($this->environment === 'homologacao') {
            $url = $this->wsobj->homologacao;
        }
        $request = $this->createSoapRequest($message, $operation);
        $this->lastRequest = $request;

        if (empty($this->soap)) {
            $this->soap = new SoapCurl($this->certificate);
        }
        $msgSize = strlen($request);
        $parameters = [
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: \"$action\"",
            "Content-length: $msgSize"
        ];
        $response = (string) $this->soap->send(
            $operation,
            $url,
            $action,
            $request,
            $parameters
        );
        return $response;
    }

    /**
     * Build SOAP request
     * @param string $message
     * @param string $operation
     * @return string XML SOAP request
     */
    protected function createSoapRequest($message, $operation)
    {
        $env = "<soap:Envelope "
            . 'xmlns:sis1="http://www.sistema.com.br/Sistema.Ws.Nfse.Cn" '
            . 'xmlns:xd="http://www.w3.org/2000/09/xmldsig#" '
            . 'xmlns:nfse="http://www.sistema.com.br/Nfse/arquivos/nfse_3.xsd" '
            . 'xmlns:sis="http://www.sistema.com.br/Sistema.Ws.Nfse" '
            . 'xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . "<soap:Body>"
            . "<{$operation}>"
            . $message
            . "</{$operation}>"
            . "</soap:Body>"
            . "</soap:Envelope>";

        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($env);

        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Create tag Prestador and insert into RPS xml
     * @param RpsInterface $rps
     * @return string RPS XML (not signed)
     */
    protected function putPrestadorInRps1(RpsInterface $rps)
    {
        $dom = new Dom('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($rps->render());
        $referenceNode = $dom->getElementsByTagName('Servico')->item(0);
        $node = $dom->createElement('Prestador');
        $dom->addChild(
            $node,
            "Cnpj",
            $this->config->cnpj,
            true
        );
        $dom->addChild(
            $node,
            "InscricaoMunicipal",
            $this->config->im,
            true
        );
        $dom->insertAfter($node, $referenceNode);
        return $dom->saveXML($dom->documentElement);
    }
}
