<?php



namespace R2Soft\VMovimentacao;
/*
  * Constantes de parâmetros para configuração da conexão
  */
define('HOST', 'localhost');
define('DBNAME', 'r2app_lusah');
define('USER', 'postgres');
define('PASSWORD', 'root');

class Conexao {

    /*
     * Atributo estático para instância do PDO
     */
    private static $pdo;

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
                self::$pdo = new \PDO("pgsql:host=" . HOST . "; dbname=" . DBNAME . "; ",USER, PASSWORD);

            } catch (\PDOException $e) {
                print "Erro: " . $e->getMessage();
            }
        }
        return self::$pdo;
    }
}