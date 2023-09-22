<?php

namespace R2Soft\VMovimentacao;
use PDO;
use R2Soft\VMovimentacao\DB\Conexao;

class ResumoFinanceiroContrato
{
    private $movimentacoes;
    private $trataVMovimentacaoR=null;
    private $tipoParcelasId=null;

    public function __construct($vendaId, $diversas=false)
    {
        $conn = Conexao::getInstance();
        if(empty($this->tipoParcelasId)){
            if($diversas)
                $stmt = $conn->prepare('select id from nucleo.tipo_parcelas where identificacao in (1,2,3,4,5,6,10)');
            else
                $stmt = $conn->prepare('select id from nucleo.tipo_parcelas where identificacao in (1,2,3,4,6,10)');
            $stmt->execute();
            $tipoParcelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->tipoParcelasId = join (',', $tipoParcelas);
        }
        if(empty($this->trataVMovimentacaoR))
            $this->trataVMovimentacaoR = new TrataVMovimentacao($vendaId);
        $stmt = $conn->prepare("select * from nucleo.v_movimentacoes_resumo_financiero WHERE venda_id = $vendaId and  tipo_movimentacao != 'movsaida' and tipo_parcela_id in($this->tipoParcelasId) order by data_vencimento");
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

    public function getInformacoesAtrasadas($diversas=false)
    {
        return $this->trataVMovimentacaoR->getValorAtrasadas($diversas);
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
