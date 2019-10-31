<?php

namespace NFePHP\NFSeSimpliss;

/**
 * Class for comunications with NFSe webserver in Nacional Standard
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

use NFePHP\NFSeSimpliss\Common\Tools as BaseTools;
use NFePHP\NFSeSimpliss\RpsInterface;
use NFePHP\Common\DOMImproved as Dom;
use NFePHP\Common\Certificate;
use NFePHP\Common\Validator;

class Tools extends BaseTools
{
    const ERRO_EMISSAO = 1;
    const SERVICO_NAO_CONCLUIDO = 2;

    protected $xsdpath;

    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
        $path = realpath(__DIR__ . '/../storage/schemes');

        if (file_exists($this->xsdpath = $path . '/'.$this->config->cmun.'.xsd')) {
            $this->xsdpath = $path . '/'.$this->config->cmun.'.xsd';
        } else {
            $this->xsdpath = $path . '/nfse_v20_08_2015.xsd';
        }
    }

    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * @param array $arps Array contendo de 1 a 50 RPS::class
     * @param string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps, $lote)
    {
        $content = $listaRpsContent = '';
        $operation = 'RecepcionarLoteRps';

        $countRps = count($arps);
        if ($countRps > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }

        foreach ($arps as $rps) {
            $xml = $rps->render();
            $xmlsigned = $this->sign($xml, 'InfRps', '');
            $listaRpsContent .= $xmlsigned;
        }

        $content .= "<EnviarLoteRpsEnvio xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">";
        $content .=     "<LoteRps xmlns=\"{$this->wsobj->msgns}\">";
        $content .=         "<NumeroLote>{$lote}</NumeroLote>";
        $content .=         "<Cnpj>{$this->config->cnpj}</Cnpj>";
        $content .=         "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>";
        $content .=         "<QuantidadeRps>{$countRps}</QuantidadeRps>";
        $content .=         "<ListaRps>";
        $content .=             $listaRpsContent;
        $content .=         "</ListaRps>";
        $content .=     "</LoteRps>";
        $content .= "</EnviarLoteRpsEnvio>";

        $content = $this->sign($content, 'LoteRps', '');
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
}