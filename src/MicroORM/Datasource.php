<?php
namespace MicroORM;

use Exception;

class Datasource
{
    /***
     * @var boolean
     */
    private $status;
    private $connection;
    /**
     * @var bool
     */
    private $transaction_in_process;

    /**
     * Datasource constructor.
     * @throws Exception
     */
    public function __construct($host,$bd,$usuario,$pass)
    {
        $this->status=false;
        $this->transaction_in_process = false;
        $this->connect($host,$bd,$usuario,$pass);
    }


    /**
     * @throws Exception
     */
    private function connect($host, $bd, $usuario, $pass){

        $this->connection=mysqli_connect($host,$usuario,$pass,$bd);
        if($this->connection){
            $this->status = true;
        }else{
            throw new Exception('no conectado');
        }

    }

    /**
     * @param $sql
     * @param bool $isSelect
     */
    function &execQuery($sql, $isSelect= true){
        $summary = new QueryInfo();



        $summary->result = @mysqli_query($this->connection, $sql );

        if($isSelect){

            $summary->total  = ($summary->result)? intval(mysqli_num_rows($summary->result)) : 0;
        }else{
            $summary->total = mysqli_affected_rows($this->connection);
            $summary->new_id = mysqli_insert_id($this->connection);
        }

        $summary->errorNo = mysqli_errno($this->connection);

        $summary->error = mysqli_error($this->connection);

        //almacena en el query info el ultimo sql
        $summary->sql = $sql;


        return $summary;
    }

    function getNext(QueryInfo $summary)
    {

        if(!isset($summary->total) || $summary->total == 0){
            return null;
        }else if($summary->inArray){


            $type=MYSQLI_ASSOC;


            return @mysqli_fetch_array($summary->result, $type);
        }else{
            return @mysqli_fetch_row($summary->result);
        }
    }

    /**
     * @param QueryInfo $summary
     * @return array
     */
    public function getAll(QueryInfo $summary){
        $valores = array();

        while($row = self::getNext($summary)){
            $valores[] = $row;
        }

        return $valores;
    }

    public function escape($str){
        if(is_array($str)){
            foreach ($str as $k => $v){
                $str[$k] = $this->escape($v);
            }
        }else{
            if(is_null($str) ){
                $str = "null";
            }else{
                $str = "'" . mysqli_real_escape_string($this->connection, $str) . "'";
            }
        }

        return $str;
    }

    function StartTransaction()
    {
        $sql = "START TRANSACTION";
        $summary = $this->execQuery($sql, false);

        $this->transaction_in_process = ($summary->error == 0);

        return $this->transaction_in_process;
    }


    function CommitTransaction()
    {
        $sql = "COMMIT";
        $this->execQuery($sql, false);

        $this->transaction_in_process = false;

        return true;
    }

    function RollBackTransaction()
    {
        $sql = "ROLLBACK";
        $this->execQuery($sql, false);

        $this->transaction_in_process = false;

        return true;
    }

    function &_insert($table, $searchArray)
    {
        //Obtiene nombre de los campos
        $def=array_keys($searchArray);

        //Para cada campo
        for ($i=0; $i < count($def); $i++) {

            //Agrega comillas
            $def[$i] = "`" . $def[$i] . "`";
        }

        //genera insert
        $sql = "INSERT INTO $table(". implode(",", $def) . ") VALUES(" . implode(",", $searchArray) . ")";

        //ejecuta
        return $this->execQuery($sql, false);
    }

    function &_update($table, $searchArray, $condition)
    {
        /** @noinspection SqlWithoutWhere */
        $sql = "UPDATE $table SET ";
        $total = count($searchArray);
        $x=0;
        foreach ($searchArray as $key => $value) {
            $sql .= "`$key` = $value";

            if($x < $total-1){

                $sql .= ", ";
                $x++;
            }
        }

        $sql .= " WHERE ";

        $sql .=  $this->buildSQLFilter($condition,"AND");

        //ejecuta
        return $this->execQuery($sql, false);
    }

    function &_delete($table, $condition)
    {
        /** @noinspection SqlWithoutWhere */
        $sql = "DELETE FROM $table ";


        $sql .= " WHERE ";

        $sql .=  $this->buildSQLFilter($condition,"AND");

        //ejecuta
        return $this->execQuery($sql, false);
    }

    private function buildSQLFilter($filterArray, $join)
    {
        //inicializa el campo q sera devuelto
        $campos = array();

        if(count($filterArray)>0){
            //para cara elemento, ya escapado
            foreach ($filterArray as $key => $value) {

                //si no tiene las comillas las pone
                if (strpos($key, '.') === false && strpos($key, '`') === false) {
                    $key = "`" . $key . "`";
                }

                //Si el elemento no es nulo
                if($value != null){

                    if($value == "null"){
                        $campos[] = "$key IS NULL";
                    }else{
                        //usa igual
                        $campos[] = "$key=".$value;
                    }
                }
            }
        }

        $campos = implode(" " . $join . " ", $campos);
        return " (" . $campos . ") ";
    }

    function existBy($table, $searchArray)
    {
        $sql = "SELECT COUNT(*) FROM " .
            $table .
            " WHERE " .
            $this->buildSQLFilter($searchArray, "AND");

        $summary = $this->execQuery($sql);
        $row = $this->getNext($summary);

        if($row){
            $val = reset($row);
        }else{
            $val = 0;
        }


        return $val > 0;
    }

    public function getNumFields(QueryInfo &$sumary){
        return mysqli_num_fields($sumary->result);
    }

    public function getFieldInfo(QueryInfo &$sumary, $i){

        return mysqli_fetch_field_direct($sumary->result, $i);;
    }

    public function getFieldType(QueryInfo &$sumary, $i){

        $info_campo = mysqli_fetch_field_direct($sumary->result, $i);
        return $info_campo->type;
    }

    public function getFieldLen(QueryInfo &$sumary, $i){
        $info_campo = mysqli_fetch_field_direct($sumary->result, $i);;
        return $info_campo->max_length;
    }

    public function getFieldFlagsBin(QueryInfo &$sumary, $i){
        $info_campo = mysqli_fetch_field_direct($sumary->result, $i);;

        //convierte a binario, invierte y divide de uno en uno
        $bin_flags = str_split(strrev(decbin($info_campo->flags)),1);




        return $bin_flags;
    }

    public function getFieldFlags(QueryInfo &$sumary, $i){


        //convierte a binario, invierte y divide de uno en uno
        $bin_flags = $this->getFieldFlagsBin($sumary, $i);

        $flags = array();

        if($bin_flags[0] == 1){
            $flags[] = "not_null";
        }


        return implode(" ", $flags);
    }

    public function setCollation($collation){
        @mysqli_query($this->connection, "SET NAMES '$collation'");
    }

    public function setUtf8(){
        $this->setCollation("utf8");
    }



    public function setTimeZone($timezone){
        @mysqli_query($this->connection, "SET time_zone = '$timezone'");
    }
}