<?php

require_once  'vendor/autoload.php';

use \R2Soft\VMovimentacao\ResumoFinanceiroContrato;

$resumoFinanceiro = new ResumoFinanceiroContrato(11);


$valorAReceber = $resumoFinanceiro->getValorAReceber();
$valorParaQuitacao = $resumoFinanceiro->getValorParaQuitacao();
$informacoesQuitadas = $resumoFinanceiro->getInformacoesQuitadas();
$informacoesAtrasada = $resumoFinanceiro->getInformacoesAtrasadas();
echo "<pre>";
print_r($valorAReceber);
echo PHP_EOL;
echo PHP_EOL;
print_r($valorParaQuitacao);
echo PHP_EOL;
echo PHP_EOL;
print_r($informacoesQuitadas);
echo PHP_EOL;
echo PHP_EOL;
print_r($informacoesAtrasada);