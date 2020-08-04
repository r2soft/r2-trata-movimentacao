<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class resumoFinanceiroContrato
{
    private $movimentacoes;
    private $movimentacoesDiversas;
    private $trataVMovimentacaoR;


    public function __construct($vendaId)
    {
        $tipoParcelas = DB::select ("select id from nucleo.tipo_parcelas where identificacao in (1,2,3,4,6,10)");
        $tipoParcelaArray=[];
        foreach ($tipoParcelas as $index => $tipoParcela) {
            $tipoParcelaArray[]=$tipoParcela->id;
        }

        $this->trataVMovimentacaoR = new trataVMovimentacao();


        $tipoParcelasId = join (',',$tipoParcelaArray);
        $this->movimentacoes = DB::select ("select * from nucleo.v_movimentacoes WHERE venda_id = $vendaId and  tipo_movimentacao != 'movsaida' and tipo_parcela_id in($tipoParcelasId) ");
        $this->processaDados();
    }

    private function processaDados(){
        foreach ($this->movimentacoes as $index => $movimentacao) {
            $vMovimentacao['VMovimentacao'] = (array)$movimentacao;
            $this->trataVMovimentacaoR->setMovimentacao($vMovimentacao);
            unset($this->movimentacoes[$index]);
        }
        $this->trataVMovimentacaoR->setNullMovimentacao ();
    }

    public function getInformacoesAtrasadas()
    {
        return $this->trataVMovimentacaoR->getValorAtrasadas();
    }

    public function getInformacoesQuitadas()
    {
        return $this->trataVMovimentacaoR->getValorQuitadas();
    }

    public function getValorNoPrazo(){
        return $this->trataVMovimentacaoR->getValorNoPrazo();
    }

    public function getValorParaQuitacao()
    {
        return $this->trataVMovimentacaoR->getValorParaQuitacao();
    }
    public function getJuroMultaAtrasadas()
    {
        return $this->trataVMovimentacaoR->getJuroMultaAtrasadas();
    }

    public function getValorAReceber()
    {
        return $this->trataVMovimentacaoR->getValorAReceber();
    }



}