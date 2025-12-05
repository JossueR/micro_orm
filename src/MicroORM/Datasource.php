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

    private ?IQueryLogger $logger;

    /**
     * Datasource constructor.
     * @throws Exception
     */
    public function __construct($host,$bd,$usuario,$pass, $port=null)
    {
        $this->status=false;
        $this->transaction_in_process = false;
        $this->connect($host,$bd,$usuario,$pass, $port);
    }

    public function getLogger(): IQueryLogger
    {
        return $this->logger;
    }

    public function setLogger(IQueryLogger $logger): void
    {
        $this->logger = $logger;
    }




    /**
     * @throws Exception
     */
    private function connect($host, $bd, $usuario, $pass, $port=null): void
    {

        $this->connection=mysqli_connect($host,$usuario,$pass,$bd, $port);
        if($this->connection){
            $this->status = true;
        }else{
            throw new Exception('no conectado');
        }

    }

    /**
     * @param $sql
     * @param bool $isSelect
     * @param QueryParams|null $params
     * @return QueryInfo
     */
    function &execQuery($sql, bool $isSelect= true, QueryParams $params=null): QueryInfo
    {
        $summary = new QueryInfo();

        if($isSelect && $params != null){
            $sql = $this->addOrder($sql, $params);
            $sql = $this->addPagination($sql, $params);
        }


        $summary->result = @mysqli_query($this->connection, $sql );

        $summary->errorNo = mysqli_errno($this->connection);

        $summary->error = mysqli_error($this->connection);

        //almacena en el query info el último sql
        $summary->sql = $sql;

        $this->logger?->log($sql, $isSelect, $summary);

        if($isSelect){

            $summary->total  = ($summary->result)? intval(mysqli_num_rows($summary->result)) : 0;

            if($params != null && $params->isEnablePaging()){
                $sql = "SELECT FOUND_ROWS();";
                $rows = @mysqli_query( $this->connection, $sql);
                $rows = mysqli_fetch_row($rows);

                $summary->allRows = $rows[0];
            }
        }else{
            $summary->total = mysqli_affected_rows($this->connection);
            $summary->allRows = $summary->total;
            $summary->new_id = mysqli_insert_id($this->connection);
        }




        return $summary;
    }

    public function execNoQuery($sql): bool
    {
        $summary = $this->execQuery($sql,false);

        return ($summary->errorNo == 0);
    }

    public function execAndFetch($sql, $inArray=null){
        $summary = self::execQuery($sql, true);

        if($inArray !== null){
            $summary->inArray=$inArray;
        }

        $row = $this->fetch($summary);

        $resp = null;

        if($summary->errorNo == 0){
            //si solo se estaba buscando un campo
            if($row && self::getNumFields($summary) == 1){
                //obtener el primer campo
                $resp = reset($row);
            }else{
                $resp =  $row;
            }
        }


        return $resp;
    }

    function fetch(QueryInfo $summary)
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
    public function fetchAll(QueryInfo $summary): array
    {
        $valores = array();

        while($row = self::fetch($summary)){
            $valores[] = $row;
        }

        return $valores;
    }

    public function escape($str, $putQuotes = true){
        if(is_array($str)){
            foreach ($str as $k => $v){
                $str[$k] = $this->escape($v);
            }
        }else{
            if(is_null($str) ){
                $str = "null";
            }else if(!($str instanceof RawQueryFilter)) {

                $str = mysqli_real_escape_string($this->connection, $str);
                if($putQuotes){
                    $str = "'" . $str . "'";
                }

            }
        }

        return $str;
    }

    function StartTransaction(): bool
    {
        $sql = "START TRANSACTION";
        $summary = $this->execQuery($sql, false);

        $this->transaction_in_process = ($summary->error == 0);

        return $this->transaction_in_process;
    }


    function CommitTransaction(): bool
    {
        $sql = "COMMIT";
        $this->execQuery($sql, false);

        $this->transaction_in_process = false;

        return true;
    }

    function RollBackTransaction(): bool
    {
        $sql = "ROLLBACK";
        $this->execQuery($sql, false);

        $this->transaction_in_process = false;

        return true;
    }

    function &_insert($table, $searchArray): QueryInfo
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

    function &_update($table, $searchArray, $condition): QueryInfo
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

    function &_delete($table, $condition): QueryInfo
    {
        /** @noinspection SqlWithoutWhere */
        $sql = "DELETE FROM $table ";


        $sql .= " WHERE ";

        $sql .=  $this->buildSQLFilter($condition,"AND");

        //ejecuta
        return $this->execQuery($sql, false);
    }

    public function buildSQLFilter(array $filterArray, $join): string
    {
        //inicializa el campo q sera devuelto
        $campos = array();

        if(count($filterArray)>0){
            //para cara elemento, ya escapado
            foreach ($filterArray as $key => $value) {

                if($value instanceof RawQueryFilter){
                    $campos[] = $key . " " . $value->getSql();
                }else {

                    //si no tiene las comillas las pone
                    if (strpos($key, '.') === false && strpos($key, '`') === false) {
                        $key = "`" . $key . "`";
                    }

                    //Si el elemento no es nulo
                    if($value != null) {

                        //si es un arreglo genera un IN
                        if (is_array($value)) {

                            //Une los valores del array y los separa por comas
                            $value = implode(" ,", $value);


                            //si no hay negacion
                            if (strpos($key, "!") === false) {
                                //almacena el filtro IN
                                $campos[] = "$key IN(" . $value . ") ";
                            } else {
                                $key = str_replace("!", "", $key);

                                //almacena el filtro IN
                                $campos[] = "$key NOT IN(" . $value . ") ";
                            }

                            //Si no es un arreglo
                        } else if ($value == "null") {
                            $campos[] = "$key IS NULL";
                        } else {
                            //usa igual
                            $campos[] = "$key=" . $value;
                        }

                    }

                }
            }
        }

        $campos = implode(" " . $join . " ", $campos);
        return " (" . $campos . ") ";
    }

    function existBy($table, $searchArray): bool
    {
        $sql = "SELECT COUNT(*) FROM " .
            $table .
            " WHERE " .
            $this->buildSQLFilter($searchArray, "AND");

        $summary = $this->execQuery($sql);
        $row = $this->fetch($summary);

        if($row){
            $val = reset($row);
        }else{
            $val = 0;
        }


        return $val > 0;
    }

    public function getNumFields(QueryInfo &$sumary): int
    {
        return mysqli_num_fields($sumary->result);
    }

    /**
     * @param QueryInfo $sumary
     * @param $i
     * Devuelve un objeto que contiene la información de definición del campo o false si no está disponible la información del campo especificada por fieldnr.
     * @return object{name: string, table: string, type:string, max_length:int}
     *
     */
    public function getFieldInfo(QueryInfo &$sumary, $i){

        return mysqli_fetch_field_direct($sumary->result, $i);
    }


    public function getFieldType(QueryInfo &$sumary, $i)
    {

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

    public function getFieldFlags(QueryInfo &$sumary, $i): string
    {


        //convierte a binario, invierte y divide de uno en uno
        $bin_flags = $this->getFieldFlagsBin($sumary, $i);

        $flags = array();

        if($bin_flags[0] == 1){
            $flags[] = "not_null";
        }


        return implode(" ", $flags);
    }

    public function setCharset($collation){
        mysqli_set_charset($this->connection,$collation);
    }

    public function setUtf8(){
        $this->setCharset("utf8");
    }



    public function setTimeZone($timezone){
        @mysqli_query($this->connection, "SET time_zone = '$timezone'");
    }

    private function addPagination($sql, QueryParams $params)
    {
        if($params != null) {
            $page = intval($params->getPage());

            //agrega limit si page es un numero mayor a cero
            if ($params->isEnablePaging() && $page >= 0) {
                //agrega SQL_CALC_FOUND_ROWS al query
                $sql = trim($sql);
                $sql = str_replace("\n", " ", $sql);
                $exploded = explode(" ", $sql);
                $exploded[0] .= " SQL_CALC_FOUND_ROWS ";
                $sql = implode(" ", $exploded);


                $desde = ($page) * $params->getCantByPage();

                $sql_pagination = " LIMIT $desde, " .$params->getCantByPage();
                if($params->getPaginationReplaceTag() != ""){
                    $sql = str_replace($params->getPaginationReplaceTag(), $sql_pagination,$sql );
                }else{
                    $sql .= " " . $sql_pagination;
                }

            }
        }
        return $sql;
    }

    function addOrder($sql, QueryParams $params)
    {
        if($params) {
            $fields = $params->getOrderFields();

            $val = null;

            //agrega SQL_CALC_FOUND_ROWS al query
            $sql = trim($sql);

            if (!is_null($fields) && $fields != "") {
                if (is_array($fields) && count($fields) > 0) {
                    $all_orders = array();
                    foreach ($fields as $order_name => $order_type) {
                        if($order_type){
                            $order_type = "ASC";
                        }else{
                            $order_type = "DESC";
                        }

                        //if (self::validFieldExist($order_name, $sql)) {
                        $order_name = "`$order_name`";
                        $all_orders[] = $order_name . " " . $order_type;
                        //}
                    }
                    $val = " ORDER BY " . implode(",", $all_orders);
                }


            }

            if($params->getOrderReplaceTag() != ""){
                $sql = str_replace($params->getOrderReplaceTag(), $val,$sql );
            }else{
                $sql .= " " . $val;
            }
        }
        return $sql;
    }
}
