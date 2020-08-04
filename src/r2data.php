<?php


namespace R2Soft\VMovimentacao;


class r2data {

    private $dataQueVaiSerConvertida;

    private function getDataQueVaiSerConvertida() {
        return $this->dataQueVaiSerConvertida;
    }

    private function setDataQueVaiSerConvertida($dataQueVaiSerConvertida) {
        $this->dataQueVaiSerConvertida = $dataQueVaiSerConvertida;
    }

    public function diferencaData($dataInicial, $dataFinal) {
        if (strstr($dataInicial, "/"))
            $dataInicial = $this->converteData($dataInicial);
        if (strstr($dataFinal, "/"))
            $dataFinal = $this->converteData($dataFinal);
        $dataInicial = explode('-', $dataInicial);
        $dataFinal = explode('-', $dataFinal);
        $segundos = mktime(0, 0, 0, $dataFinal[1], $dataFinal[2], $dataFinal[0]) - mktime(0, 0, 0, $dataInicial[1], $dataInicial[2], $dataInicial[0]);
        $difere = round($segundos / 86400);
        return $difere;
    }

    public function converteData($data) {
        $this->setDataQueVaiSerConvertida($data);
        if ($this->isDataBrasileira())
            return $this->getDataInternacional();
        if ($this->isDataInternacional($data))
            return $this->getDataBrasileira();
    }

    private function isDataBrasileira() {
        if (strstr($this->getDataQueVaiSerConvertida(), "/"))
            return true;
        return false;
    }

    private function isDataInternacional() {
        if (strstr($this->getDataQueVaiSerConvertida(), "-"))
            return true;
        return false;
    }

    private function getDataInternacional() {
        $d = explode("/", $this->getDataQueVaiSerConvertida());
        return $rstData = "$d[2]-$d[1]-$d[0]";
    }

    private function getDataBrasileira() {
        $d = explode("-", $this->getDataQueVaiSerConvertida());
        $rstData = "$d[2]/$d[1]/$d[0]";
        return $rstData;
    }

    public function adicionaMesesAUmaData($data, $qtdMeses = integer) {
        if ($data != null){
            $dataRef = explode("/", $data);
            if(checkdate ( $dataRef[1], $dataRef[0], $dataRef[2])){
                $dataRef = implode('-', array_reverse($dataRef));
                return $this->addMonths("+{$qtdMeses}", $dataRef);
            }else
                return '01/01/1900';
        }else{
            return '01/01/1900';
        }

    }

    public function subtraiMesesAUmaData($data, $qtdMeses = integer) {
        if ($data != null){
            $dataRef = explode("/", $data);
            if(checkdate ( $dataRef[1], $dataRef[0], $dataRef[2])){
                $dataRef = implode('-', array_reverse($dataRef));
                return $this->addMonths("-{$qtdMeses}", $dataRef);
            }else
                return '01/01/1900';
        }else{
            return '01/01/1900';
        }
    }

    public function getQuantidadeDeMesesEntreDatas($dataInicial, $dataFinal) {
        $dataInicial = strtotime($this->converteData($dataInicial));
        $dataFinal = strtotime($this->converteData($dataFinal));
        if ($dataInicial === false || $dataInicial < 0 || $dataFinal === false || $dataFinal < 0 || $dataInicial > $dataFinal)
            return false;
        $quantidadeDeAnos = date('Y', $dataFinal) - date('Y', $dataInicial);
        $quantidadeDeMeses = date('m', $dataFinal) - date('m', $dataInicial);
        $quantidadeDeMeses += ($quantidadeDeAnos * 12);
        return $quantidadeDeMeses;
    }

    public function calculaData($data, $quantidadeDeDias) {
        if (strpos($data, '-'))
            $data = $this->converteData($data);
        $data = explode('/', $data);
        $dia = $data[0];
        $mes = $data[1];
        $ano = $data[2];
        return date('d/m/Y', mktime(0, 0, 0, $mes, $dia + $quantidadeDeDias, $ano));
    }

    public function seData1MaiorOuIgualData2($data1, $data2) {        
        $this->setDataQueVaiSerConvertida($data1);
        if ($this->isDataBrasileira())
            $data1 = $this->getDataInternacional();
        $this->setDataQueVaiSerConvertida($data2);
        if ($this->isDataBrasileira())
            $data2 = $this->getDataInternacional();
        if (strtotime($data1) >= strtotime($data2))
            return true;
        return false;
    }

    /**
     * @param $ano ano em que se quer calcular os feriados
     * @return array com os feriados do ano (fixo e moveis)
     */
    function getFeriadosNacionais($ano) {
        $feriados_locais = $_SESSION['Auth']['Empresa']['Empresa']['feriados_locais'];        
        if ($feriados_locais) {
            $feriados_locais = explode(',', $feriados_locais);
            foreach ($feriados_locais as $key => $value) {
                $feriados_locais[$key] = $value . "/$ano";
            }
        }
        $dia = 86400;
        $datas = array();        
        $datas['pascoa'] = easter_date($ano);
        $datas['sexta_santa'] = $datas['pascoa'] - (2 * $dia);
        $datas['carnaval'] = $datas['pascoa'] - (47 * $dia);
        $datas['corpus_cristi'] = $datas['pascoa'] + (60 * $dia);
        $feriados = array(
            "01/01/$ano",
            date('d/m/Y', $datas['carnaval']),
            date('d/m/Y', $datas['sexta_santa']),
            date('d/m/Y', $datas['pascoa']),
            "21/04/$ano",
            "01/05/$ano",
            date('d/m/Y', $datas['corpus_cristi']),
            "07/09/$ano",
            "12/10/$ano",
            "02/11/$ano",
            "15/11/$ano",
            "25/12/$ano",
        );
        if ($feriados_locais)
            $feriados = array_merge($feriados, $feriados_locais);        
        return $feriados;
    }

    /**
     * Verifica se é fim de semana
     * @param type $data dd/mm/YY
     * @return boolean
     */
    public function isFimDeSemana($data) {
        $array_dias_proibidos = Array(6, 0); // 0 = domingo, 6= = sábado         
        if (in_array(date('w', strtotime($this->converteData($data))), $array_dias_proibidos))
            return true;
        return false;
    }

    public static function addMonths($monthToAdd, $date) {
        $d1 = new DateTime($date);
        $year = $d1->format('Y');
        $month = $d1->format('n');
        $day = $d1->format('d');
        if ($monthToAdd > 0) {
            $year += floor($monthToAdd/12);
        } else {
            $year += ceil($monthToAdd/12);
        }
        $monthToAdd = $monthToAdd%12;
        $month += $monthToAdd;
        if($month > 12) {
            $year ++;
            $month -= 12;
        } elseif ($month < 1 ) {
            $year --;
            $month += 12;
        }

        if(!checkdate($month, $day, $year)) {
            $d2 = DateTime::createFromFormat('Y-n-j', $year.'-'.$month.'-1');
            $d2->modify('last day of');
        }else {
            $d2 = DateTime::createFromFormat('Y-n-d', $year.'-'.$month.'-'.$day);
        }
        return $d2->format('d/m/Y');
    }

    public function diasAtraso($dataI, $dataII=null){
        if ($dataI){
            if($dataII){
                $contchar = strpos($dataII,'/');
                if ($contchar >= 1) {
                    $hojeI = substr($dataII,6,4).'-'.substr($dataII,3,2).'-'.substr($dataII,0,2);
                }else{
                    $hojeI = $dataII;
                }

            }else{
                $hojeI= date("Y-m-d");
            }
            $contchar = strpos($dataI,'/');
            if ($contchar >= 1) {
                $dataI = substr($dataI,6,4).'-'.substr($dataI,3,2).'-'.substr($dataI,0,2);
            }

            $dias = $this->diferencaDatas($dataI,$hojeI);
            if ($dias < -360){
                $base = ($dias / 360);
                $base = ($base * 5.1744);
                $dias = ($dias - $base);
            }
            return ceil($dias);
        } else
            return null;
    }

    Function diferencaDatas($data1, $data2=""){
        for($i=1;$i<=2;$i++){
            ${"ano".$i} = substr(${"data".$i},0,4);
            ${"mes".$i} = substr(${"data".$i},5,2);
            ${"dia".$i} = substr(${"data".$i},8,2);
        }
        $segundos = mktime(0,0,0,$mes2,$dia2,$ano2) - mktime(0,0,0,$mes1,$dia1,$ano1);
        $difere = round($segundos/86400);
        $difere--;
        return $difere;
    }

    public function calcularTermosVencidos($diasAtraso,$movimentacao, $juroAplicadoNoContrato)
    {
        App::import('Vendor', 'price');
        $price = new price();
        if ($diasAtraso > -29){
            $valor = $movimentacao['0']['valor'] + $movimentacao['0']['juro'];
            $result['valor_presente'] = $valor;
            $result['juros_descontos'] = $movimentacao['0']['juro'];
        }else{
            $periodos = $this->getPeriodosTermosVencidos($movimentacao['0']['data_vencimento']);
            $valor = $price->getValorPresenteDaParcela($movimentacao['0']['valor'], $juroAplicadoNoContrato, $periodos);
//            debug('Data V =>'.$movimentacao['0']['data_vencimento'].' V => '.$movimentacao['0']['valor'].' - P => '.$periodos.'- VA => '.$valor);
            $valor += $movimentacao['0']['juro'];
            $result['valor_presente']= $valor;
            $result['juros_descontos']= $valor - $movimentacao['0']['valor'];
        }
        return $result;
    }

    public function calcularTermosAntecipados($diasAtraso,$movimentacao, $juroAplicadoNoContrato)
    {
        App::import('Vendor', 'price');
        $price = new price();
        $periodos = $this->getPeriodosTermosAntecipados($movimentacao['0']['data_vencimento']);
//        $valor = $price->getValorPresenteDaParcela($movimentacao['0']['valor'], $juroAplicadoNoContrato, $periodos);
        $valor = $price->getValorPresenteDaParcela(($movimentacao['0']['valor'] + $movimentacao[0]['juro'] +  + $movimentacao[0]['desconto']), $juroAplicadoNoContrato, $periodos);
        $valor += $movimentacao['0']['juro'];
        $result['valor_presente']= $valor;
        $result['juros_descontos']= $valor - $movimentacao['0']['valor'];
        return $result;
    }

    public function getPeriodoApartirDeHoje($data){
        $data = explode('-',$data);
        if (empty($data[1])){
            $data = explode('/',$data[0]);
            $ano= $data[2];
            $mes= $data[1];
            $dia= $data[0];
            $data[2] = $dia;
            $data[1] = $mes;
            $data[0] = $ano;
        }
        $Periodos = 0;
        $Periodos += $this->calculaAno($data[0]);
        $Periodos += $this->calculaMes($data[1]);
        $dias = $this->calculaDias($data[2],$data[1]);
        if ($dias >= 0){
            $Periodos--;
        }else{
            $Periodos-=2;
        }
        return $Periodos;
    }

    public function calculaAno($ano)
    {
        return (($ano - date('Y')) * 12);
    }

    public function calculaMes($mes)
    {
        return (($mes - date('m'))+ 1);
    }

    public function calculaDias($dia, $mes)
    {
        $dias = 0;
        if ($dia == 31){
            $dia = 30;
        }
        switch ($mes){
            case'02':
                return $this->diffDiasMesFev($dia);
                break;
            case'01':
            case'03':
            case'05':
            case'07':
            case'08':
            case'10':
            case'12':
                return $this->diffDiasMes31($dia);
                break;
            case'04':
            case'06':
            case'09':
            case'11':
                return $this->diffDiasMes30($dia);
                break;
            default:
                echo 'Mes '.$mes.' não encontrado!';
                exit;
        }
    }

    public function diffDiasMesFev ($dia)
    {
        $diaBase = (date('d') * 1);
        if (!(date('Y') % 4)){

            if ($diaBase >= 29 || $dia >= 29){
                $dia = 0;
            }
        } else {
            if ($diaBase >= 28 || $dia >= 28 ){
                $dia = 0;
            }
        }
        if ($dia == 0){
            return $this->processaRetornoDias(0);
        }
        $diaCalc = $dia - $diaBase;
        return $this->processaRetornoDias($diaCalc);
    }

    public function diffDiasMes30 ($dia)
    {
        $diaBase = (date('d') * 1);
        if($diaBase == 31){
            $diaBase =30;
        }
        $diaCalc = $dia - $diaBase;
        return $this->processaRetornoDias($diaCalc);
    }

    public function diffDiasMes31 ($dia)
    {
        $diaBase = (date('d') * 1);
        if($diaBase == 31){
            $diaBase =30;
        }
        $diaCalc = $dia - $diaBase;
        return $this->processaRetornoDias($diaCalc);
    }

    public function processaRetornoDias($dias)
    {
        $diaBase = (date('d') * 1);
        $mesBase = (date('m') * 1);
        if ($mesBase == 2){
            if (!(date('Y') % 4)){
                if ($dias < 0 && $diaBase >= 29){
                    $dias = 0;
                }
            } else {
                if ($dias < 0 && $diaBase >= 28){
                    $dias = 0;
                }
            }
        }
        return $dias;
    }

    public function getPeriodosTermosAntecipados($data)
    {
        return $this->getPeriodoApartirDeHoje($data);
    }

    public function getPeriodosTermosVencidos($data)
    {
        return ($this->getPeriodosTermosAntecipados($data));
    }


    public function calculaJuroBoleto($valor, $valorJuro, $ValorMultaMora, $valorJuroMora, $dataVencimentoOriginal, $dataProrrogacao)
    {
        $valor = $this->trataValor($valor);
        $valorJuro = $this->trataValor($valorJuro);
        $juro = null;
        $taxaJuro = ($valorJuroMora!=0) ? $valorJuroMora / 100 : 0;
        $taxaMulta = ($ValorMultaMora!=0) ? $ValorMultaMora / 100 : 0;

        if (!empty($valor) && !empty($taxaJuro) && !empty($dataVencimentoOriginal) && !empty($dataProrrogacao)){
            $valorMulta = ($valor - $valorJuro)*$taxaMulta;
            $valor -= $valorJuro;
            $dias = $this->diasAtraso($dataVencimentoOriginal, $dataProrrogacao);
            $dias++;

            if ($dias > 0 && $taxaJuro != 0){
                $juroBase = (($taxaJuro/30)*$dias);
                $juro = number_format((($valor * $juroBase)),2,'.','');
                $total = $valorMulta + $juro;
                if ($total > $valorJuro){
                    return number_format(($valorJuro - $valorMulta),2,'.','');
                }
            }
//            return str_replace(',','.',$valor);
        }

        return $juro;
    }
    //Calculo correto Baseando no valor total contando juros anteriores
//    public function calculaJuroBoletoCorreto($valor, $ValorJuro, $ValorMultaMora, $ValorJuroMora, $dataVencimentoOriginal, $dataProrrogacao)
//    {
//        $juro = null;
//        $taxaJuro = ($ValorJuroMora!=0) ? $ValorJuroMora / 100 : 0;
//        $taxaMulta = ($ValorMultaMora!=0) ? $ValorMultaMora / 100 : 0;
//        if (!empty($valor) && !empty($taxaJuro) && !empty($dataVencimentoOriginal) && !empty($dataProrrogacao)){
//            $valorMulta = ($valor - $ValorJuro)*$taxaMulta;
//            $valor -= ($valorMulta + $ValorJuro);
//            $dias = $this->diasAtraso($dataVencimentoOriginal, $dataProrrogacao);
//            $dias++;
//            if ($dias > 0 && $taxaJuro != 0){
//                $juroBase = (($taxaJuro/30)*$dias);
//                $juroBase = ($juroBase -(($juroBase*((pow((1+$juroBase), 1)-1)))-0.000059));
//                return number_format(($valor * $juroBase),2,'.','');
//            }
//        }
//        return $juro;
//    }
    public function trataValor($valor){
        if (substr_count($valor, ',') == 0){
            return $valor;
        }else{
            if (substr_count($valor, '.') > 0){
                $valor = str_replace('.','',$valor);
            }
            return str_replace(',','.',$valor);
        }
    }
}

