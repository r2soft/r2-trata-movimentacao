<?php
namespace R2Soft\VMovimentacao;
class price {

    public function getValorDaParcela($valorPresente, $juro, $quantidadeDeParcelas) {
        return round(($valorPresente * $juro) / (1 - (1 / pow(1 + $juro, $quantidadeDeParcelas))), 2);
    }

    public function getSaldoDevedor2($valorDaParcela, $juro, $quantidadeDeParcelasRestantes) {
        if ($juro == 0)
            return round($valorDaParcela * $quantidadeDeParcelasRestantes, 2);
        return round($valorDaParcela * ((pow(1 + $juro, $quantidadeDeParcelasRestantes)) - 1) / ((pow(1 + $juro, $quantidadeDeParcelasRestantes)) * $juro), 2);
    }

    public function getValorPresenteDaParcela($valorDaParcela, $juro, $quantidadeDeParcelasRestantes) {
        return round($valorDaParcela / (pow((1 + $juro), $quantidadeDeParcelasRestantes)), 2);
    }

    public function getTabelaPrice($valorPresente, $juro, $quantidadeDeParcelas) {

        $valorDaParcela = $this->getValorDaParcelaPrice($valorPresente, $juro, $quantidadeDeParcelas);
        $valorTotalParcelas = $valorDaParcela * $quantidadeDeParcelas;
        $juroTotal = 0;
        $amortizacaoTotal = 0;
        $linha = '';
        $tabela = '<table border = "1">';
        $tabela .='<thead>';
        $tabela .='<th>N.</th>';
        $tabela .='<th>Prestação</th>';
        $tabela .='<th>Juro</th>';
        $tabela .='<th>Amortização</th>';
        $tabela .='<th>Saldo Devedor</th>';
        $tabela .='</thead>';
        $linha .= '<tr>';
        $linha .= "<td>0</td>";
        $linha .= "<td>-</td>";
        $linha .= "<td>-</td>";
        $linha .= "<td>-</td>";
        $linha .= "<td>{$valorPresente}</td>";
        $linha .= '</tr>';

        for ($index = $quantidadeDeParcelas - 1; $index >= 0; $index--) {
            $meses = $quantidadeDeParcelas - $index;
            $juroCalculado = $valorPresente*($juro/100);
            $valorPresente = ($valorPresente+$juroCalculado-$valorDaParcela);
            $amortizacao = round($valorDaParcela-$juroCalculado, 2);
            $valorDoJuro = round($juroCalculado, 3);
            $juroTotal += $valorDoJuro;
            $amortizacaoTotal+=$amortizacao;

            $linha .= '<tr>';
            $linha .= "<td>{$meses}</td>";
            $linha .= "<td>{$valorDaParcela}</td>";
            $linha .= "<td>". round($valorDoJuro,2)."</td>";
            $linha .= "<td>{$amortizacao}</td>";
            $linha .= "<td>".round($valorPresente,2)."</td>";
            $linha .= '</tr>';
        }

        $linha .= '<tr>';
        $linha .= "<td>Total</td>";
        $linha .= "<td>{$valorTotalParcelas}</td>";
        $linha .= "<td>{$juroTotal}</td>";
        $linha .= "<td>{$amortizacaoTotal}</td>";
        $linha .= "<td>-</td>";
        $linha .= '</tr>';
        $tabela .= $linha;
        $tabela .= '</table>';
        return $tabela;
    }

    public function getValorDaParcelaPrice($Valor, $Juros, $Parcelas) {
        if ($Juros == 0)
            return round(($Valor / $Parcelas), 2);
        $Juros = bcdiv($Juros, 100, 15);
        $E = 1.0;
        $cont = 1.0;
        for ($k = 1; $k <= $Parcelas; $k++) {
            $cont = bcmul($cont, bcadd($Juros, 1, 15), 15);
            $E = bcadd($E, $cont, 15);
        }
        $E = bcsub($E, $cont, 15);
        $Valor = bcmul($Valor, $cont, 15);
        return round(bcdiv($Valor, $E, 15), 2);
    }

    public function getValorDeEntrada($valorDoLote, $valorEntrada = 0) {
        if ($valorEntrada > 100)
            return $valorEntrada;
        return $this->getConvertePorcentagemParaMoeda($valorDoLote, $valorEntrada);
    }

    public function getConverteValorEntradaParaPorcentual($valorDoLote, $valorEntrada) {
        if ($valorEntrada < 100)
            return number_format($valorEntrada, 2, ',', '');
        return number_format(($valorEntrada * 100) / $valorDoLote, 2, ',', '');
    }

    public function getConvertePorcentagemParaMoeda($valorDoLote, $valorEntradaPorcentagem) {
        $valorEntradaMoeda = ($valorDoLote * $valorEntradaPorcentagem) / 100;
        return $valorEntradaMoeda;
    }

    public function getAmortizacao($valorPresente, $juro, $quantidadeDeParcelas) {

        $valorDaParcela = $this->getValorDaParcelaPrice($valorPresente, $juro, $quantidadeDeParcelas);
        $valorTotalParcelas = $valorDaParcela * $quantidadeDeParcelas;
        $juroTotal = 0;
        $amortizacaoTotal = 0;
        $linha = '';
        $x = array();

        for ($index = $quantidadeDeParcelas - 1; $index >= 0; $index--) {
            $meses = $quantidadeDeParcelas - $index;
            $juroCalculado = $valorPresente*($juro/100);
            $valorPresente = ($valorPresente+$juroCalculado-$valorDaParcela);
            $amortizacao = round($valorDaParcela-$juroCalculado, 2);
            $valorDoJuro = round($juroCalculado, 3);
            $juroTotal += $valorDoJuro;
            $amortizacaoTotal+=$amortizacao;
            $x[$index]=$amortizacao;
        }


        return $x;
    }
    public function getIndiceAplicadoNaParcela($juro, $quantidadeDeParcelasRestantes) {
        return (pow((1 + $juro), $quantidadeDeParcelasRestantes));
    }
}