<?php

namespace R2Soft\VMovimentacao;

use Exception;
use R2Soft\VMovimentacao\DB\Conexao;
use PDO;


class TrataVMovimentacao
{

    private $movimentacao;
    /**
     * @var $r2data r2data
     */
    private $r2data;
    private $price;
    private $dadosDaVenda;
    public $agrupadas;
    public $agrupadasId;
    public $ordemAgrupamentos;
    public $arrayIdMovPaiAgrupadas;
    private $valorTotal = 0;
    private $valorAtualizadoTotal = 0;
    private $valorDescontoTotal = 0;
    private $valorJuroAtualizadoTotal = 0;
    private $multaTotal = 0;
    private $atualizacaoTotal =0;
    private $valorAtrasadas = array(
        'quantidade' => 0,
        'valor_sem_juro' => 0,
        'juro' => 0,
        'valor_com_juro' => 0,
        'desconto' => 0,
        'multa' => 0,
        'juro_sem_multa' => 0,
        'entrada' => [
            'quantidade' => 0,
            'valor_com_juro' => 0
        ]

    );
    private $valorQuitadas = array('quantidade' => 0, 'valor_sem_juro' => 0, 'juro' => 0, 'valor_com_juro' => 0, 'desconto' => 0);
    private $valorEntradas = array(
        'quantidade' => 0,
        'quantidade_total'=>0,
        'valor_sem_juro' => 0,
        'juro' => 0,
        'valor_com_juro' => 0,
        'desconto' => 0,
        'multa' => 0,
        'juro_sem_multa' => 0,
        'atrasadas' => [
            'quantidade' => 0,
            'valor_com_juro' => 0
        ],
        'pagas' => [
            'quantidade' => 0,
            'valor_com_juro' => 0
        ]
    );
    private $valorNoPrazo = array('quantidade' => 0, 'valor_sem_juro' => 0, 'juro' => 0, 'valor_com_juro' => 0, 'desconto' => 0);
    private $valorParaQuitacao = array('valor_sem_juro' => 0, 'valor_com_juro' => 0);
    private $JuroMultaAtrasadas = 0;
    private $vendaIdAtual = array();
    private $jsonAgrupadas = array();
    private $ordemMovimentacoesAgrupadas = array();
    private $movimentacoesPaiAgupradas = array();
    private $empresaId = null;
    private $jurosMulta=0;
    private $dataBaseValorPresente=null;
    private $identificacaoEntrada = [2,3];
    private $inclirEntrada=true;

    public function setIncluirEntrada($incluirEntrada){
        $this->inclirEntrada = $incluirEntrada;
    }

    public function __construct($vendaId=null)
    {
        $conn = Conexao::getInstance();
        $stmt = $conn->prepare('SELECT id FROM nucleo.empresas');
        $stmt->execute();
        $empresa = $stmt->fetch(\PDO::FETCH_ASSOC );
        $this->empresaId = $empresa['id'];

        if($vendaId && $this->empresaId == 943){
            $stmt = $conn->prepare("select min(data_vencimento) from nucleo.v_movimentacoes_resumo_financiero WHERE venda_id = :vendaId and  tipo_movimentacao != 'movsaida' and identificacao = 1 group by venda_id");
            $stmt->execute(array('vendaId' => $vendaId));
            $this->minDataSaldoDevedor = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->minDataSaldoDevedor = $this->minDataSaldoDevedor[0]['min'] ?? null;
            $this->setDataBaseValorPresente($this->minDataSaldoDevedor);
        }
    }


    public function getDataBaseValorPresente()
    {
        return $this->dataBaseValorPresente;
    }

    public function setDataBaseValorPresente($dataBaseValorPresente)
    {
        $this->dataBaseValorPresente = $dataBaseValorPresente;
    }
    /**
     * @return int
     */
    public function getJurosMulta()
    {
        return $this->jurosMulta;
    }

    /**
     * @param int $jurosMulta
     */
    public function setJurosMulta($jurosMulta)
    {
        $this->jurosMulta = $jurosMulta;
    }

    /**
     * @throws Exception
     */
    private function theMovementWasPassed()
    {
        if (empty($this->movimentacao)) {
            throw new Exception('Você deve passar uma movimentacao! exemplo: trataMovimentacao::setMovimentacao()');
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function setMovimentacao($movimentacao)
    {


        if (isset($movimentacao['VMovimentacao']))
            $this->movimentacao = $movimentacao['VMovimentacao'];
        else
            throw new Exception("Dados incorretos! a Movimentacao deve ter a chave 'VMovimentacao'");


        if (!$this->r2data instanceof r2data)
            $this->r2data = new r2data();
        if (!$this->price instanceof price)
            $this->price = new price();

        $this->setDadosDaVenda($this->movimentacao['venda_id']);
        $atualizacao = $this->getAtualizacao();

        $this->valorAtualizadoTotal += $this->getValorDaMovimentacaoAtualizado();
        $this->valorDescontoTotal += $this->getDesconto();
        $this->valorJuroAtualizadoTotal += $atualizacao['juros'] ?: $this->movimentacao['juro_total'];
        if($atualizacao['multa'] == 0 and $this->movimentacao['dias_atraso'] > 0) {
            $this->multaTotal += $atualizacao['multa'] ?: $this->movimentacao['multa_calculada'];
        }
        $this->atualizacaoTotal += $atualizacao['valor_atualizado'];
        $this->valorTotal += $this->getValor();
    }

    private function getAgrupadas($agrupadasJson)
    {
        $agrupadas = array();
        if (!empty($agrupadasJson)) {
            foreach ($agrupadasJson as $agrupadaJson) {
                $aux = json_decode($agrupadaJson['movimentacao_agrupada'], true);
                $agrupadas = array_merge($agrupadas, $aux);
            }
        }
        return $agrupadas;
    }

    private function getAgrupadasId($agrupadasJson)
    {
        $agrupadasId = array();
        if (!empty($agrupadasJson)) {
            foreach ($agrupadasJson as $agrupadaJson) {
                $agrupadas = json_decode($agrupadaJson['movimentacao_agrupada'], true);
                foreach ($agrupadas as $index => $agrupada) {
                    $agrupadasId[$index] = $index;
                }
            }
        }
        return $agrupadasId;
    }

    private function getOrdemAgrupamentos()
    {
        if(empty($this->movimentacao['venda_id']))
            $this->movimentacao['venda_id'] = key($this->vendaIdAtual);
        $ordemAgrupamento = $this->getOrdemMovimentacoesAgupradas($this->movimentacao['venda_id']);
        unset($objAgrupadas);
        return $ordemAgrupamento;
    }

    private function getIdMovimentacoesPaiAgupradas($vendaId = null)
    {
        if ($vendaId == null) {
            return 'Venda id não informada';
        }
        if (empty($this->movimentacoesPaiAgupradas[$vendaId])) {
            $conn = Conexao::getInstance();
            $stmt = $conn->prepare('select movimentacao_gerada_id from nucleo.movimentacaoagrupadas where movimentacaoagrupadas.venda_id = :vendaId and ativo = true');
            $stmt->execute(array('vendaId' => $vendaId));
            $resultado = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($resultado[0])) {
                $this->movimentacoesPaiAgupradas[$vendaId] = 1;
                return null;
            }
            $tratados = null;
            foreach ($resultado as $key => $item) {
                $tratados[$key] = $item['movimentacao_gerada_id'];
            }
            $this->movimentacoesPaiAgupradas[$vendaId] = $tratados;
        }
        return $this->movimentacoesPaiAgupradas[$vendaId];
    }

    public function getDiasAtraso()
    {
        try {
            if ($this->theMovementWasPassed())
                return $this->movimentacao['dias_atraso'];
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function getJuroRemanescente()
    {
        try {
            if ($this->theMovementWasPassed())
                if (empty($this->movimentacao['data_prorogacao_antecipacao']) && !empty($this->movimentacao['juro'])) {
                    return $this->movimentacao['juro'];
                }
            return 0;
        } catch (\Exception $e) {
            return $e;
        }
    }

    private function getValorSeAtrasado()
    {
        try {
            if ($this->theMovementWasPassed()) {

                $atualizacao = $this->getAtualizacao();
//                debug($atualizacao);
                if (empty($this->movimentacao['data_credito'])) {

                    $juro = 0;
                    if ($this->getJuroRemanescente() > 0) {
                        if($this->getJuroRemanescente() >= $this->movimentacao['multa_calculada']){
                            $juro = $this->getJuroRemanescente() + $this->getJuroDaParcelaVencida() + $this->movimentacao['desconto'];
                        }else
                            $juro = $this->getJuroRemanescente() + $this->getJuroDaParcelaVencida() + $this->movimentacao['multa_calculada'] + $this->movimentacao['desconto'];
                    } elseif ($this->movimentacao['juro'] <= 0) {
                        $juro = $this->movimentacao['juro'] + $this->getJuroDaParcelaVencida() + $this->movimentacao['multa_calculada'] + $this->movimentacao['desconto'];
                    } else
                        $juro = $this->movimentacao['juro'] + $this->getJuroDaParcelaVencida() + $this->movimentacao['desconto'];
                    $this->setValorEntradas();
                    if($atualizacao['valor_atualizado']>0){
                        $this->setValorAtrasadas($this->movimentacao['valor']-$atualizacao['valor_atualizado']);
                        return $atualizacao['valor_atualizado'];
                    }else{
                        $this->setValorAtrasadas($juro);
                        return $this->movimentacao['valor'] + $juro;
                    }

                }

                return $this->movimentacao['valor_pago'];
            }
        } catch (\Exception $e) {
            return $e;
        }
    }

    private function getValorSeMovimentacaoDeSaida()
    {
        if ($this->movimentacao['tipo_movimentacao'] == 'movsaida') {
            if (empty($this->movimentacao['data_credito']))
                return $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
            return $this->movimentacao['valor_pago'];
        }
    }

    private function getValorSeMovimentacaoDeReceita()
    {
        $identificacoesPermitidas = array(1, 2, 3, 4,6);

        if (in_array($this->movimentacao['identificacao'], $identificacoesPermitidas)) {
            $this->setValorEntradas();
            if (empty($this->movimentacao['data_credito'])) {

                if(!empty($this->movimentacao['juro_financiamento']) and $this->movimentacao['juro_financiamento'] > 0){
                    $this->dadosDaVenda['juro_aplicado'] = ($this->movimentacao['juro_financiamento']);
                }
                if ($this->dadosDaVenda['termos_antecipados']) {
                    $periodos = $this->r2data->getPeriodosTermosAntecipados($this->movimentacao['data_vencimento']);
                    $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], $this->dadosDaVenda['juro_aplicado'], $periodos);
                    $this->valorParaQuitacao['valor_sem_juro'] += $valor;
                    $valor += $this->movimentacao['juro'] + $this->movimentacao['desconto'];
                } else {
                    if ($this->getDiasAtraso() > -31) {
                        $valor = $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
                        $this->valorParaQuitacao['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
                    } else {

                        $periodos = $this->r2data->getPeriodosTermosVencidos($this->movimentacao['data_vencimento'], $this->getDataBaseValorPresente());
                        switch ($this->empresaId){
                            case 211:
                                $mip = $this->movimentacao['valor']*0.025052;
                                $adm = $this->movimentacao['valor']*0.00926;
                                $this->movimentacao['valor'] = $this->movimentacao['valor']-$mip-$adm;
                                $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], $this->dadosDaVenda['juro_aplicado'], $periodos);
                                $this->movimentacao['valor'] = $this->movimentacao['valor']+$mip+$adm;
                                break;
                            case 573:
                                $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor_inicial'], $this->dadosDaVenda['juro_aplicado'], $periodos);
                                break;
                            case 943:
                                if($this->movimentacao['identificacao']!=1){
                                    $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], 0, $periodos);
                                }else{
                                    $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], $this->dadosDaVenda['juro_aplicado'], $periodos);
                                }
                                break;
                            default:
                                $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], $this->dadosDaVenda['juro_aplicado'], $periodos);

                        }
                        $this->valorParaQuitacao['valor_sem_juro'] += $valor+ $this->movimentacao['desconto'];
                        $valor += $this->movimentacao['juro'] + $this->movimentacao['desconto'];
                    }
                }
                $this->setValorNoPrazo();

                $this->valorParaQuitacao['valor_com_juro'] += $valor;
//                debug($this->valorParaQuitacao['valor_com_juro']);

            } else {
                $this->setValorQuitadas();
                $valor = $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
            }
            return $valor;
        } else {
            if (empty($this->movimentacao['data_credito'])) {
                $this->setValorNoPrazo();
                return $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
            } else {
                $this->setValorQuitadas();
                return $this->movimentacao['valor_pago'];
            }
        }
    }

    public function getJuroDaParcelaVencida()
    {
        return $this->movimentacao['juro_total'];
    }

    public function getValorDaMovimentacaoAtualizado()
    {

        if ($this->getDiasAtraso() > 0) {
            return $this->getValorSeAtrasado();
        } else {
            if ($this->movimentacao['tipo_movimentacao'] == 'movsaida') {
                return $this->getValorSeMovimentacaoDeSaida();
            } else {
                return $this->getValorSeMovimentacaoDeReceita();
            }
        }
    }

    public function getCategoria()
    {
        if ($this->movimentacao['renegociado']) {
            return $this->movimentacao['categoria'] . ' <br><b>RENEGOCIADO</b>';
        } else
            if (!empty($this->agrupadas)) {
                if (in_array($this->movimentacao['id'], $this->agrupadasId)) {
                    return $this->movimentacao['categoria'] . ' <br><b>AGRUPADAS - ' . $this->ordemAgrupamentos[$this->movimentacao['id']] . '</b><br>';
                } else {
                    return $this->movimentacao['categoria'];
                }
            } else {
                return $this->movimentacao['categoria'];
            }
    }

    public function getNovoVencimento($ptBr = true)
    {
        if ($this->movimentacao['data_vencimento'] != $this->movimentacao['vencimento_original']) {
            return $ptBr ? $this->r2data->converteData($this->movimentacao['data_vencimento']) : $this->movimentacao['data_vencimento'];
        }
        return $ptBr ? $this->r2data->converteData($this->movimentacao['vencimento_original']) : $this->movimentacao['vencimento_original'];
    }

    public function getVencimentoOriginal($ptBr = true)
    {
        return $ptBr ? $this->r2data->converteData($this->movimentacao['vencimento_original']) : $this->movimentacao['vencimento_original'];
    }

    public function getValor()
    {
        return $this->movimentacao['valor'];
    }

    public function getDesconto()
    {
        return $this->movimentacao['desconto'];
    }

    public function getJuroAtualizado()
    {
        $atualizacao = $this->getAtualizacao();
        if($atualizacao['indice']>0){
            return $atualizacao['juros']+$atualizacao['indice'];
        }

        if ($this->movimentacao['juro'] <= 0) {
            return $this->movimentacao['juro'] + $this->movimentacao['juro_total'];
        }
        return $this->movimentacao['juro'] + $this->movimentacao['juro_total'] - $this->movimentacao['multa_calculada'];
    }

    public function getValorAtualizadoTotal()
    {
        return $this->valorAtualizadoTotal;
    }

    public function getValorJuroAtualizadoTotal()
    {
        return $this->valorJuroAtualizadoTotal;
    }

    public function getValorDescontoTotal()
    {
        return $this->valorDescontoTotal;
    }

    public function getValorTotal()
    {
        return $this->valorTotal;
    }

    public function getTipoDeParcela()
    {
        return $this->movimentacao['tipo'];
    }

    public function getDescricao()
    {
        return $this->movimentacao['name'];
    }

    public function getJuroFinanciamento()
    {
        $this->dadosDaVenda['juro_aplicado'];
    }

    public function getValorJuro()
    {
        return $this->movimentacao['juro'];
    }

    public function getValorMulta()
    {
        if ($this->getDiasAtraso() > 0)
            return $this->movimentacao['multa_calculada'];
        return 0;
    }

    /**
     * @return array
     */
    public function getValorAtrasadas()
    {
        return $this->valorAtrasadas;
    }

    /**
     * @param float[] $juro
     */
    protected final function setValorAtrasadas($juro)
    {
        $atualizacao = $this->getAtualizacao();

        $this->valorAtrasadas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
        $this->valorAtrasadas['multa'] += $this->movimentacao['multa_calculada'];
        if($atualizacao['valor_atualizado']>0){
            $this->valorAtrasadas['juro_sem_multa'] += $atualizacao['valor_atualizado'] -$atualizacao['valor'] - $atualizacao['multa'];
            $this->valorAtrasadas['juro'] += $atualizacao['juros']+$atualizacao['multa']+$atualizacao['indice'];
            $this->valorAtrasadas['valor_com_juro'] +=  $atualizacao['valor_atualizado'];
            $this->valorParaQuitacao['valor_com_juro'] += $atualizacao['valor_atualizado'];
            if (in_array($this->movimentacao['identificacao'], $this->identificacaoEntrada)) {
                $this->valorAtrasadas['entrada']['valor_sem_juro']=$this->movimentacao['valor'] + $this->movimentacao['desconto'];
                $this->valorAtrasadas['entrada']['valor_com_juro']=$atualizacao['valor_atualizado'];
                $this->valorAtrasadas['entrada']['quantidade'] += 1;
            }
        }
        else{
            $this->valorAtrasadas['juro_sem_multa'] += (float)$juro - (float)$this->movimentacao['multa_calculada'];
            $this->valorAtrasadas['valor_com_juro'] += $this->movimentacao['valor'] + $juro + $atualizacao['valor_atualizado'];
            $this->valorParaQuitacao['valor_com_juro'] += $this->movimentacao['valor'] + $juro + $atualizacao['valor_atualizado'];
            $this->valorAtrasadas['juro'] += $juro;
            if (in_array($this->movimentacao['identificacao'], $this->identificacaoEntrada)) {
                $this->valorAtrasadas['entrada']['valor_sem_juro']= $this->movimentacao['valor'] + $this->movimentacao['desconto'];
                $this->valorAtrasadas['entrada']['valor_com_juro']= $this->movimentacao['valor'] + $juro + $atualizacao['valor_atualizado'] +$this->movimentacao['desconto'];
                $this->valorAtrasadas['entrada']['quantidade'] += 1;
            }
        }
        $this->valorAtrasadas['quantidade'] += 1;
        $this->JuroMultaAtrasadas += $juro;
        $this->valorParaQuitacao['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
    }

    /**
     * @param float[] $juro
     */
    private function setValorQuitadas()
    {
        if(!$this->inclirEntrada){
            if( !in_array($this->movimentacao['identificacao'],$this->identificacaoEntrada)) {
                $this->valorQuitadas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
                $this->valorQuitadas['juro'] += $this->movimentacao['juro'];
                $this->valorQuitadas['desconto'] += $this->movimentacao['desconto'];
                $this->valorQuitadas['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
                $this->valorQuitadas['quantidade'] += 1;
            }
        }else{
            $this->valorQuitadas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
            $this->valorQuitadas['juro'] += $this->movimentacao['juro'];
            $this->valorQuitadas['desconto'] += $this->movimentacao['desconto'];
            $this->valorQuitadas['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
            $this->valorQuitadas['quantidade'] += 1;
        }

    }

    /**
     * @return array
     */
    public function getValorQuitadas()
    {
        return $this->valorQuitadas;
    }

    /**
     * @param float[] $juro
     */
    private function setValorNoPrazo()
    {
        if(!$this->inclirEntrada){
            if( !in_array($this->movimentacao['identificacao'],$this->identificacaoEntrada)) {
                $this->valorNoPrazo['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
                $this->valorNoPrazo['juro'] += $this->movimentacao['juro'];
                $this->valorNoPrazo['desconto'] += $this->movimentacao['desconto'];
                $this->valorNoPrazo['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'] + $this->movimentacao['juro'];
                $this->valorNoPrazo['quantidade'] += 1;
            }
        }else{
            $this->valorNoPrazo['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
            $this->valorNoPrazo['juro'] += $this->movimentacao['juro'];
            $this->valorNoPrazo['desconto'] += $this->movimentacao['desconto'];
            $this->valorNoPrazo['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'] + $this->movimentacao['juro'];
            $this->valorNoPrazo['quantidade'] += 1;
        }

    }

    /**
     * @return array
     */
    public function getValorNoPrazo()
    {
        return $this->valorNoPrazo;
    }

    /**
     * @return float
     */
    public function getValorParaQuitacao()
    {
        return $this->valorParaQuitacao;
    }

    /**
     * @return float
     */
    public function getJuroMultaAtrasadas()
    {
        return $this->JuroMultaAtrasadas;
    }

    public function getValorAReceber()
    {
        $atrasadas = $this->getValorAtrasadas();
        $noPrazo = $this->getValorNoPrazo();
        if(!$this->inclirEntrada){
            return array(
                'quantidade' => $atrasadas['quantidade'] + $noPrazo['quantidade'] - $atrasadas['entrada']['quantidade'],
                'juro' => $atrasadas['juro'] + $noPrazo['juro'],
                'valor_sem_juro' => $atrasadas['valor_sem_juro'] + $noPrazo['valor_sem_juro'] - ($atrasadas['entrada']['valor_sem_juro'] ?? 0),
                'valor_com_juro' => $atrasadas['valor_com_juro'] + $noPrazo['valor_com_juro'] - $atrasadas['entrada']['valor_com_juro'],
                'desconto' => $atrasadas['desconto'] + $noPrazo['desconto']
            );
        }
        return array(
            'quantidade' => $atrasadas['quantidade'] + $noPrazo['quantidade'],
            'valor_sem_juro' => $atrasadas['valor_sem_juro'] + $noPrazo['valor_sem_juro'],
            'juro' => $atrasadas['juro'] + $noPrazo['juro'],
            'valor_com_juro' => $atrasadas['valor_com_juro'] + $noPrazo['valor_com_juro'],
            'desconto' => $atrasadas['desconto'] + $noPrazo['desconto']
        );

    }

    public function isProrrogado()
    {
        if (!empty($this->movimentacao['data_prorogacao_antecipacao']))
            return true;
        return false;
    }

    public function setDadosDaVenda($vendaId)
    {
        if (empty($this->vendaIdAtual[$vendaId])) {
            $conn = Conexao::getInstance();
            $stmt = $conn->prepare('select termos_antecipados, juro_aplicado, valor_venda_sem_juro, empresa_id from nucleo.vendasloteamentos INNER JOIN nucleo.vendas ON (nucleo.vendasloteamentos.venda_id = nucleo.vendas.id) where venda_id = :vendaId');
            $stmt->execute(array('vendaId' => $vendaId));
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->vendaIdAtual[$vendaId] = $dados;

            if(!empty($this->vendaIdAtual[$vendaId])){
                $this->dadosDaVenda['valor_venda_sem_juro'] = $this->vendaIdAtual[$vendaId][0]['valor_venda_sem_juro'];
            }else{
                $this->dadosDaVenda['valor_venda_sem_juro'] = 0;
            }
            if(!empty($this->movimentacao['valor'])){
                $this->dadosDaVenda['mip'] = $this->movimentacao['valor'] * 0.025052;
                $this->dadosDaVenda['adm'] = $this->movimentacao['valor'] * 0.00926;
            }


        }
        if (empty($this->vendaIdAtual[$vendaId])) {
            $this->dadosDaVenda['juro_aplicado'] = 0;
            $this->dadosDaVenda['termos_antecipados'] = false;
        } else {
            $this->dadosDaVenda['juro_aplicado'] = ($this->vendaIdAtual[$vendaId][0]['juro_aplicado'] / 100);
            $this->dadosDaVenda['termos_antecipados'] = $this->vendaIdAtual[$vendaId][0]['termos_antecipados'];
        }
        $agrupadasJson = $this->getJsonDosIdMovimentacoesAgupradas($vendaId);
        $this->agrupadas = $this->getAgrupadas($agrupadasJson);
        $this->agrupadasId = $this->getAgrupadasId($agrupadasJson);
        $this->ordemAgrupamentos = $this->getOrdemAgrupamentos();
        $this->arrayIdMovPaiAgrupadas = $this->getIdMovimentacoesPaiAgupradas($vendaId);
    }

    private function getJsonDosIdMovimentacoesAgupradas($vendaId = null)
    {
        if ($vendaId == null) {
            return 'Venda id não informada';
        }
        if (empty($this->jsonAgrupadas[$vendaId])) {
            $conn = Conexao::getInstance();
            $stmt = $conn->prepare('select * from nucleo.movimentacaoagrupadas where venda_id = :vendaId and ativo = true');
            $stmt->execute(array('vendaId' => $vendaId));
            $this->jsonAgrupadas[$vendaId] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        if (empty($this->jsonAgrupadas[$vendaId][0])) {
            $this->jsonAgrupadas[$vendaId] = 1;
            return null;
        } else {
            return $this->jsonAgrupadas[$vendaId];
        }
    }

    private function getOrdemMovimentacoesAgupradas($vendaId = null)
    {
        if ($vendaId == null) {
            return 'Venda id não informada';
        }
        if (empty($this->ordemMovimentacoesAgrupadas[$vendaId])) {
            $conn = Conexao::getInstance();
            $stmt = $conn->prepare('select movimentacao_agrupada from nucleo.movimentacaoagrupadas where movimentacaoagrupadas.venda_id = :vendaId');
            $stmt->execute(array('vendaId' => $vendaId));
            $resultado = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($resultado[0])) {
                $this->ordemMovimentacoesAgrupadas[$vendaId] = 1;
                return null;
            } else {
                $tratados = null;
                foreach ($resultado as $key => $dados) {
                    foreach (json_decode($dados['movimentacao_agrupada'], true) as $value => $item) {
                        $tratados[$value] = ($key + 1);
                    }
                }
                $this->ordemMovimentacoesAgrupadas[$vendaId] = $tratados;
            }
        }
        return $this->ordemMovimentacoesAgrupadas[$vendaId];
    }

    public function setNullMovimentacao()
    {
        $this->movimentacao = null;
    }

    public function getMoraDiaCalculada()
    {
        $atualizacao = $this->getAtualizacao();
        if($atualizacao['indice']>0){
            return ($atualizacao['juros']+$atualizacao['indice']) / $atualizacao['atraso'];
        }
        return $this->movimentacao['mora_dia_calculada'];
    }
    public function getMultaTotal()
    {
        return $this->multaTotal;
    }
    public function getAtualizacao(){
        $result = array(
            'vencimento'=>null,
            'atraso'=>0,
            'multa'=>0,
            'valor'=>0,
            'indice'=>0,
            'juros'=>0,
            'valor_corrigido'=>0,
            'valor_atualizado'=>0,
        );
        if ($this->getDiasAtraso() <= 0)
            return $result;
        switch ($this->empresaId){
            case 556:
                $meses = $this->getPeriodos($this->movimentacao['data_vencimento']);
//                debug($meses);exit;
                $controle = 0;
                $qtd = count($meses);
//                debug($qtd);exit;
                $valor = $this->movimentacao['valor'];
                $juros_d_total =0;
                $indice_d_total=0;
//                $debug=array();
//                echo "<pre>";
                foreach ($meses as $index => $mes) {

//                    if($this->movimentacao['data_vencimento']!='2022-09-25')
//                        continue;

                    $_mes = str_pad($mes['mes'] , 2 , '0' , STR_PAD_LEFT);
                    $controle++;
                    $now = new \DateTime('now');
                    $nowDay = $now->format('d');

                    if($controle==1){
                        if($qtd==1){
                            $dateVencimento=$this->movimentacao['data_vencimento'];
                            if(strstr($dateVencimento, '/'))
                                $dateVencimento = $this->r2data->converteData($dateVencimento);

                            $dados1venc = $this->getDadosVencimento($dateVencimento, true, true);
//                            if($dados1venc['dias_no_mes']>=1)
//                                $dados1venc['dias_no_mes']=$dados1venc['dias_no_mes']-1;

                        }else
                            $dados1venc = $this->getDadosVencimento($this->movimentacao['data_vencimento'], true);
                        if($nowDay=='01' && $qtd==2){
                            $dados1venc['dias_no_mes']=$dados1venc['dias_no_mes']+1;
                        }
                        $moraD = ($this->movimentacao['mora_dia']/100)/$dados1venc['ultimo_dia_mes'];
                        $indice = $this->getIndice($_mes, $mes['ano']);

                        $indice_d = (($indice*$this->movimentacao['valor'])/$dados1venc['ultimo_dia_mes'])*$dados1venc['dias_no_mes'];
                        $juros_d = ($moraD*$this->movimentacao['valor'])*$dados1venc['dias_no_mes'];

                        $valor += $indice_d+$juros_d+$this->movimentacao['multa_calculada'];
                        $result['multa']=$this->movimentacao['multa_calculada'];
                        $result['valor']=$this->movimentacao['valor'];
                        $result['vencimento']=$this->movimentacao['data_vencimento'];
                        $result['atraso']=$this->movimentacao['dias_atraso'];

                        $primeiro =
                            array(
                                'PRIMEIRO'=>'PRIMEIRO',
                                "Vencimento"=>$this->movimentacao['data_vencimento'],
                                "VALOR"=>$this->movimentacao['valor'],
                                "Ult. Dia Mês:"=>$dados1venc['ultimo_dia_mes'],
                                "Dias Calc:"=>$dados1venc['dias_no_mes'],
                                "IGPM:"=> $indice,"Mes:"=>$_mes,
                                "Indice:"=>round((($indice*$valor)/$dados1venc['ultimo_dia_mes'])*$dados1venc['dias_no_mes'],2),
                                'Juros dia'=>$moraD*$this->movimentacao['valor'],
                                'Juros total'=>$juros_d,
                                "MULTA"=>$this->movimentacao['multa_calculada'],
                                'VALOR ATUALIZADO'=>round($valor, 2));

//                        debug($primeiro);
                        $juros_d_total += $juros_d;
                        $indice_d_total += $indice_d;

                    }else if($controle!=$qtd){
                        $dados1venc = $this->getDadosVencimento("01/{$_mes}/{$mes['ano']}");
                        $moraD = ($this->movimentacao['mora_dia']/100)/$dados1venc['ultimo_dia_mes'];
                        $indice = $this->getIndice($_mes, $mes['ano']);
                        $juros_d = ($moraD*$valor)*$dados1venc['dias_no_mes'];
                        $indice_d = (($indice*$valor)/$dados1venc['ultimo_dia_mes'])*$dados1venc['dias_no_mes'];
                        $valor_at = $valor;
                        $valor+=$indice_d+$juros_d;

                        $meio = array(
                            'MEIO'=>'MEIO',
                            "VALOR"=>round($valor_at,2),
                            "Ult. Dia Mês:"=>$dados1venc['ultimo_dia_mes'],
                            "Dias Calc:"=>$dados1venc['dias_no_mes'],
                            "IGPM:"=> $indice,
                            "Mes:"=>$_mes,
                            "Indice:"=>round($indice_d,2),
                            'Juros dia'=>$moraD*$valor,
                            'Juros total'=>$juros_d,
                            'VALOR ATUALIZADO'=>round($valor, 2));

//                        debug($meio);
                        $juros_d_total += $juros_d;
                        $indice_d_total += $indice_d;

                    }


                    if($qtd>1 && $controle==$qtd){
                        $dateVencimento = $this->movimentacao['data_vencimento'];
                        if(strstr($dateVencimento, '/'))
                            $dateVencimento = $this->r2data->converteData($dateVencimento);
                        $dados1venc = $this->getDadosVencimento("01/{$_mes}/{$mes['ano']}", false, true);

                        $moraD = ($this->movimentacao['mora_dia']/100)/$dados1venc['ultimo_dia_mes'];
                        $dateVencimento = new \DateTime($dateVencimento);
                        $dias_no_mes = $dados1venc['dias_no_mes'];
                        $indice = $this->getIndice($_mes, $mes['ano']);
                        $juros_d = ($moraD*$valor)*$dias_no_mes;
//                        debug("MoraD: ". $moraD ." Valor: " .$valor." Dias No Mês: ". $dias_no_mes);
                        $juros_d = ($moraD*$this->movimentacao['valor'])*$dados1venc['dias_no_mes'];
                        $dateVencimento->modify('last day of this month');
                        $ultimo_dia_mes = $dateVencimento->format('d');
                        $indice_d = (($indice*$valor)/$dados1venc['ultimo_dia_mes'])*$dias_no_mes;
                        $valor_at=$valor;
                        $valor+=$indice_d+$juros_d;

                        $ultimo = array(
                            'ULTIMO'=>'ULTIMO',
                            "VALOR"=>round($valor_at,2),
                            "Ult. Dia Mês:"=>$dados1venc['ultimo_dia_mes'],
                            "Dias Calc:"=>$dados1venc['dias_no_mes'],
                            "IGPM:"=> $indice,
                            "Mes:"=>$_mes,
                            "Indice:"=>round($indice_d,2),
                            'Juros dia'=>$moraD*$valor,
                            'Juros total'=>$juros_d,
                            'VALOR ATUALIZADO'=>round($valor, 2));

//                        debug($ultimo);
                        $juros_d_total += $juros_d;
                        $indice_d_total += $indice_d;
                    }


//                    debug($juros_d_total);

                }

                $result['indice']=round($indice_d_total,2);
                $result['juros']=round($juros_d_total,2);
                $result['valor_atualizado']=round($valor,2);

                $result['valor_corrigido']=round($result['valor']+$indice_d_total,2);
//                debug($result);
//                exit;
                return $result;
            default:
                return $result;
        }
    }
    public function getAtualizacaoTotal(){
        return $this->atualizacaoTotal;
    }

    private function getDadosVencimento($dateVencimento, $subtraiData=false, $now=false){
        $dados=array();
        if(strstr($dateVencimento, '/'))
            $dateVencimento = $this->r2data->converteData($dateVencimento);

        $dateVencimento = new \DateTime($dateVencimento);
//        debug($dateVencimento->format('d/m/Y'));
        $diaV = $dateVencimento->format('d');
        $dateVencimento->modify('last day of this month');
        $ultimo = $dateVencimento->format('d');
//        debug($dateVencimento->format('d/m/Y'));
        $ultimoDiaDoMes = $ultimo;
        if($now){
            $now = new \DateTime('now');
            $ultimo = $now->format('d');
            if($ultimo=='01'){
                $now->sub(new \DateInterval('P1D'));
                $ultimo=$now->format('d');
            }
            $now->modify('last day of this month');
            $ultimoDiaDoMes = $now->format('d');
        }

        $dados['dias_no_mes'] = $ultimo;
//        debug($ultimo." - ".$diaV);
//        debug($dados);exit;
        if($subtraiData)
            $dados['dias_no_mes'] = ($ultimo-$diaV);
        $dados['ano_primeiro_vencimento'] = $dateVencimento->format('Y');
        $dados['mes_primeiro_vencimento'] = $dateVencimento->format('m');
        $dados['dia_vencimento'] = $diaV;
        $dados['ultimo_dia_mes'] = $ultimoDiaDoMes;
        return $dados;
    }

    private function getIndice($mes, $ano, $codigo=null){
        $conn = Conexao::getInstance();
        $data = str_pad($mes, 4, '0', STR_PAD_LEFT);
        $data = "01/{$data}/{$ano}";
        $stmt = $conn->prepare("SELECT valor FROM nucleo.item_correcoes where data = :data");
        $stmt->execute(array('data' => $data));
        $resultado = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if($resultado){
            if($resultado['0']['valor']>0)
                return $resultado['0']['valor']/100;
            return 0;
        }

        $stmt = $conn->prepare("SELECT valor FROM nucleo.item_correcoes order by data desc limit 1");
        $stmt->execute();
        $resultado = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if($resultado){
            if($resultado['0']['valor']>0)
                return $resultado['0']['valor']/100;
            return 0;
        }
        return $resultado['0']['valor']/100;
    }

    private function getPeriodos($dataVencimento){
        // getPeriodoAteHoje() so conta o mes corrente quando recebe d/m/Y (troca o dia por 01).
        // Com Y-m-d (leitura via PDO, ex.: ResumoFinanceiroContrato) ela mantem o dia do vencimento e,
        // se hoje for antes desse dia, perde o mes corrente: o quadro de atrasadas ficava menor que a
        // lista de parcelas (Cake entrega d/m/Y). Normaliza para os dois caminhos contarem igual.
        if (!strstr($dataVencimento, '/'))
            $dataVencimento = (new \DateTime($dataVencimento))->format('d/m/Y');
        $x = $this->r2data->getPeriodoAteHoje($dataVencimento);

        $now = new \DateTime('now');
        $nowM = $now->format('m');
        $dados1venc = $this->getDadosVencimento($dataVencimento);

//        if($x==-1 and $nowM!=$dados1venc['mes_primeiro_vencimento']){
//            $x+=-1;
//        }elseif ($dados1venc['mes_primeiro_vencimento']=='02')
//            $x+=1;

        if ($dados1venc['mes_primeiro_vencimento']=='02')
            $x+=1;

        $meses=range(1, ($x*-1));
        if($dados1venc['mes_primeiro_vencimento']=='02')
            $meses=range(1, ($x*-1)+1);

        $dateVencimento = $this->movimentacao['data_vencimento'];
        if(strstr($dateVencimento, '/'))
            $dateVencimento = $this->r2data->converteData($dateVencimento);
        $dateVencimento = new \DateTime($dateVencimento);
        foreach ($meses as $index => $mes) {
            if($index>0)
                $dateVencimento->modify("+1 month");
            $meses[$index]=array(
                'mes'=>$dateVencimento->format('m'),
                'ano'=>$dateVencimento->format('Y')
            );
        }
        return $meses;
    }
    public function getValorEntrada()
    {
        return $this->valorEntradas;

    }

    /**
     * Soma os valores de entrada (identificacao 2 e 3)
     * em $this->valorEntradas
     *
     * @return void
     */
    private function setValorEntradas()
    {
        if( in_array($this->movimentacao['identificacao'],$this->identificacaoEntrada)){
            $juro=0;
            if ($this->movimentacao['dias_atraso']>'0' and $this->movimentacao['data_credito']=='') {
                $this->valorEntradas['atrasadas']['valor_com_juro'] += ($this->movimentacao['valor']
                    + $this->movimentacao['juro']
                    + $this->movimentacao['multa_calculada']
                    + $this->movimentacao['juro_total']
                    + $this->movimentacao['desconto']);
                $this->valorEntradas['atrasadas']['quantidade'] += 1;
            }elseif(!empty($this->movimentacao['data_credito'])){
                $this->valorEntradas['pagas']['valor_com_juro'] += ($this->movimentacao['valor']
                    + $this->movimentacao['juro']
                    + $this->movimentacao['desconto']);
                $this->valorEntradas['pagas']['quantidade'] += 1;
            }else{
                $this->valorEntradas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
                $this->valorEntradas['juro'] += $this->movimentacao['juro'];
                $this->valorEntradas['desconto'] += $this->movimentacao['desconto'];
                $this->valorEntradas['valor_com_juro'] += ($this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['juro_total'] + $this->movimentacao['desconto']);
                $this->valorEntradas['quantidade'] += 1;
            }
            if(!$this->inclirEntrada)
                $this->valorEntradas['quantidade_total'] += 1;
        }
    }
}
