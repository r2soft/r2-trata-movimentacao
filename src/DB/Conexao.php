<?php



namespace R2Soft\VMovimentacao\DB;

/*
  * Constantes de parâmetros para configuração da conexão
  */


class Conexao {

    /*
     * Atributo estático para instância do PDO
     */
    private static $pdo;

    public static $host = '';
    public static $dbName = '';
    public static $user = '';
    public static $password = '';

    public static $port = '5432';
    /*
     * Escondendo o construtor da classe
     */
    private function __construct() {
        //
    }

    /**
     * Método estático para retornar uma conexão válida
     * Verifica se já existe uma instância da conexão, caso não, configura uma nova conexão
     * @var $pdo \PDO
     */
    public static function getInstance() {
        if (!isset(self::$pdo)) {
            try {

                self::$pdo = new \PDO("pgsql:dbname=" . self::$dbName . "; user=".self::$user."; password=".self::$password."; host=" . self::$host . "; port=" . self::$port . "");

            } catch (\PDOException $e) {
                print "Erro: " . $e->getMessage();
            }
        }
        return self::$pdo;
    }
}