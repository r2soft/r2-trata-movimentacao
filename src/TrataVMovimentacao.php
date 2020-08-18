<?php

namespace R2Soft\VMovimentacao;

use R2Soft\VMovimentacao\DB\Conexao;

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
    private $valorAtrasadas = array('quantidade' => 0, 'valor_sem_juro' => 0, 'juro' => 0, 'valor_com_juro' => 0, 'desconto' => 0, 'multa' => 0, 'juro_sem_multa' => 0);
    private $valorQuitadas = array('quantidade' => 0, 'valor_sem_juro' => 0, 'juro' => 0, 'valor_com_juro' => 0, 'desconto' => 0);
    private $valorNoPrazo = array('quantidade' => 0, 'valor_sem_juro' => 0, 'juro' => 0, 'valor_com_juro' => 0, 'desconto' => 0);
    private $valorParaQuitacao = array('valor_sem_juro' => 0, 'valor_com_juro' => 0);
    private $JuroMultaAtrasadas = 0;
    private $vendaIdAtual = array();
    private $jsonAgrupadas = array();
    private $ordemMovimentacoesAgrupadas = array();
    private $movimentacoesPaiAgupradas = array();

    private function theMovementWasPassed()
    {
        if (empty($this->movimentacao))
            throw new \Exception("Você deve passar uma movimentacao! exemplo: trataMovimentacao::setMovimentacao()");
        return true;
    }

    public function setMovimentacao($movimentacao)
    {
        if (isset($movimentacao['VMovimentacao']))
            $this->movimentacao = $movimentacao['VMovimentacao'];
        else
            throw new \Exception("Dados incorretos! a Movimentacao deve ter a chave 'VMovimentacao'");

        if (!$this->r2data instanceof r2data)
            $this->r2data = new r2data();
        if (!$this->price instanceof price)
            $this->price = new price();
        $this->setDadosDaVenda($this->movimentacao['venda_id']);
        $this->valorAtualizadoTotal += $this->getValorDaMovimentacaoAtualizado();
        $this->valorDescontoTotal += $this->getDesconto();
        $this->valorJuroAtualizadoTotal += $this->getJuroAtualizado();
        $this->multaTotal += $this->getValorMulta();
        $this->valorTotal += $this->getValor();
    }

    private function getAgrupadas($vendaId)
    {
        $agrupadasJson = $this->getJsonDosIdMovimentacoesAgupradas($vendaId);

        $agrupadas = array();

        if (!empty($agrupadasJson)) {
            foreach ($agrupadasJson as $agrupadaJson) {
                $aux = json_decode($agrupadaJson['movimentacao_agrupada'], true);
                $agrupadas = array_merge($agrupadas, $aux);
            }
        }
        return $agrupadas;
    }

    private function getAgrupadasId($vendaId)
    {
        $agrupadasJson = $this->getJsonDosIdMovimentacoesAgupradas($vendaId);
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
                if (empty($this->movimentacao['data_credito'])) {
                    $juro = 0;
                    if ($this->getJuroRemanescente() > 0) {
                        $juro = $this->getJuroRemanescente() + $this->getJuroDaParcelaVencida() + $this->movimentacao['desconto'];
                    } elseif ($this->movimentacao['juro'] <= 0) {
                        $juro = $this->movimentacao['juro'] + $this->getJuroDaParcelaVencida() + $this->movimentacao['multa_calculada'] + $this->movimentacao['desconto'];
                    } else
                        $juro = $this->movimentacao['juro'] + $this->getJuroDaParcelaVencida() + $this->movimentacao['desconto'];
                    $this->setValorAtrasadas($juro);
                    return $this->movimentacao['valor'] + $juro;
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
        if ($this->movimentacao['identificacao'] == 1 || $this->movimentacao['identificacao'] == 4) {
            if (empty($this->movimentacao['data_credito'])) {
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
                        $periodos = $this->r2data->getPeriodosTermosVencidos($this->movimentacao['data_vencimento']);
                        $valor = $this->price->getValorPresenteDaParcela($this->movimentacao['valor'], $this->dadosDaVenda['juro_aplicado'], $periodos);
                        $this->valorParaQuitacao['valor_sem_juro'] += $valor+ $this->movimentacao['desconto'];
                        $valor += $this->movimentacao['juro'] + $this->movimentacao['desconto'];
                    }
                }
                $this->setValorNoPrazo();
                $this->valorParaQuitacao['valor_com_juro'] += $valor;

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

    private function getJuroDaParcelaVencida()
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
    private final function setValorAtrasadas($juro)
    {
        $this->valorAtrasadas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
        $this->valorAtrasadas['juro'] += $juro;
        $this->valorAtrasadas['multa'] += $this->movimentacao['multa_calculada'];
        $this->valorAtrasadas['juro_sem_multa'] += (float)$juro - (float)$this->movimentacao['multa_calculada'];
        $this->valorAtrasadas['valor_com_juro'] += $this->movimentacao['valor'] + $juro;
        $this->valorAtrasadas['quantidade'] += 1;
        $this->JuroMultaAtrasadas += $juro;
        $this->valorParaQuitacao['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
        $this->valorParaQuitacao['valor_com_juro'] += $this->movimentacao['valor'] + $juro;
    }

    /**
     * @param float[] $juro
     */
    private function setValorQuitadas()
    {
        $this->valorQuitadas['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
        $this->valorQuitadas['juro'] += $this->movimentacao['juro'];
        $this->valorQuitadas['desconto'] += $this->movimentacao['desconto'];
        $this->valorQuitadas['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['juro'] + $this->movimentacao['desconto'];
        $this->valorQuitadas['quantidade'] += 1;
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
        $this->valorNoPrazo['valor_sem_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'];
        $this->valorNoPrazo['juro'] += $this->movimentacao['juro'];
        $this->valorNoPrazo['desconto'] += $this->movimentacao['desconto'];
        $this->valorNoPrazo['valor_com_juro'] += $this->movimentacao['valor'] + $this->movimentacao['desconto'] + $this->movimentacao['juro'];
        $this->valorNoPrazo['quantidade'] += 1;
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
            $stmt = $conn->prepare('select termos_antecipados, juro_aplicado from nucleo.vendasloteamentos where venda_id = :vendaId');
            $stmt->execute(array('vendaId' => $vendaId));
            $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->vendaIdAtual[$vendaId] = $dados;
        }
        if (empty($this->vendaIdAtual[$vendaId])) {
            $this->dadosDaVenda['juro_aplicado'] = 0;
            $this->dadosDaVenda['termos_antecipados'] = false;
        } else {
            $this->dadosDaVenda['juro_aplicado'] = ($this->vendaIdAtual[$vendaId][0]['juro_aplicado'] / 100);
            $this->dadosDaVenda['termos_antecipados'] = $this->vendaIdAtual[$vendaId][0]['termos_antecipados'];
        }
        $this->agrupadas = $this->getAgrupadas($vendaId);
        $this->agrupadasId = $this->getAgrupadasId($vendaId);
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
        return $this->movimentacao['mora_dia_calculada'];
    }

    public function getMultaTotal()
    {
        return $this->multaTotal;
    }
}
