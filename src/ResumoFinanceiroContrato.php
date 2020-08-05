<?php

namespace R2Soft\VMovimentacao;
use PDO;
use R2Soft\VMovimentacao\DB\Conexao;

class ResumoFinanceiroContrato
{
    private $movimentacoes;
    private $trataVMovimentacaoR=null;
    private $tipoParcelasId=null;


    public function __construct($vendaId)
    {
        $conn = Conexao::getInstance();
        if(empty($this->tipoParcelasId)){
            $stmt = $conn->prepare('select id from nucleo.tipo_parcelas where identificacao in (1,2,3,4,6,10)');
            $stmt->execute();
            $tipoParcelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->tipoParcelasId = join (',', $tipoParcelas);
        }
        if(empty($this->trataVMovimentacaoR))
            $this->trataVMovimentacaoR = new TrataVMovimentacao();
        $stmt = $conn->prepare("select * from nucleo.v_movimentacoes WHERE venda_id = $vendaId and  tipo_movimentacao != 'movsaida' and tipo_parcela_id in($this->tipoParcelasId) ");
        $stmt->execute();
        $this->movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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