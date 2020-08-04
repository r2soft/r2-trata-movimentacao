<?php
require_once "src/resumoFinanceiroContrato.php";
$resumoFinanceiro = new \R2Soft\VMovimentacao\resumoFinanceiroContrato(11);

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