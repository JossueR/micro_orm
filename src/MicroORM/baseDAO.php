<?php


namespace MicroORM;


use Exception;

class baseDAO
{
    /**
     * @var Datasource
     */
    private $datasource;
    private $table;
    private $id;

    private $enableHistory;
    private $historyTable;
    private $historyMap = false;
    /**
     * @var QueryInfo
     */
    private $summary;
    /**
     * @var array
     */
    private $errors;
    private $lastSelectQuery;
    private $validate;
    /**
     * @var QueryParams
     */
    private $query_params;

    /**
     * @throws Exception
     */
    function __construct($table, $id, $datasource=null) {

        if(!$datasource){
            $datasource = ConnectionHolder::getInstance()->getDefaultConnection();
        }

        if(!$datasource){
            throw new Exception("No connection found");
        }

        $this->table = $table;
        $this->id = $id;
        $this->datasource=$datasource;

        $this->errors = array();
        $this->validate=true;

    }

    /**
     * @return bool
     */
    public function isValidate(): bool
    {
        return $this->validate;
    }

    /**
     * @param bool $validate
     */
    public function setValidate(bool $validate)
    {
        $this->validate = $validate;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }



    function setHistory($table, $map){
        $this->enableHistory = true;

        $this->historyTable=$table;
        $this->historyMap=$map;
    }

    function _history($searchArray){
        if($this->enableHistory){
            $this->datasource->_insert($this->historyTable, $searchArray);
        }
    }

    function &insert($searchArray, $escape=true){
        if($escape){
            $searchArray = $this->datasource->escape($searchArray);
        }

        $this->summary = $this->datasource->_insert($this->table, $searchArray);



        $this->_history($searchArray);
        return $this->summary;

    }

    function &update($searchArray, $condition, $escape=true){
        if($escape){
            $condition = $this->datasource->escape($condition);
            $searchArray = $this->datasource->escape($searchArray);
        }

        $this->summary = $this->datasource->_update($this->table, $searchArray, $condition);

        //Update no hace history por que podria no estar actualizando algo solo por id, sino multiples registros
        return $this->summary;

    }

    function &delete($condition, $escape=true){


        if($escape){
            $condition = $this->datasource->escape($condition);
        }


        $this->summary= $this->datasource->_delete($this->table, $condition);

        //$this->_history($searchArray);
        return $this->summary;

    }

    /**
     * @param $searchArray
     * @return bool
     */
    public function save($searchArray)
    {



        if(!$this->validate($searchArray)){
            return false;
        }


        $searchArray = $this->datasource->escape($searchArray);
        $idArray = $this->extractID($searchArray);



        if(!$this->datasource->existBy($this->table, $idArray)){

            $this->summary = $this->insert($searchArray, false);

        }else{

            foreach ($this->id as $key ) {
                unset($searchArray[$key]);
            }
            $this->summary = $this->update($searchArray, $idArray, false);
            $this->_history(array_merge($searchArray,$idArray));
        }

        if($this->summary->errorNo != 0){
            $this->errors[] = $this->summary->error;
        }

        return ($this->summary->errorNo == 0);
    }

    private function validate($searchArray): bool
    {
        $errors = array();

        $fields = array_keys($searchArray);
        $fields_all = implode(',', $this->datasource->escape($fields));
        $sql = "SELECT " . $fields_all . " FROM " . $this->table . " LIMIT 0";
        $sumary =$this->datasource->execQuery($sql);

        $i = 0;
        $total = $this->datasource->getNumFields($sumary);

        while ($i < $total) {
            $f = $fields[$i];
            $type = $this->datasource->getFieldType($sumary, $i);
            $len = $this->datasource->getFieldLen($sumary, $i);
            $flag = explode(" ", $this->datasource->getFieldFlags($sumary, $i));

            //verifica requerido
            if(in_array("not_null", $flag)){

                if($searchArray[$f] === null || $searchArray[$f] === "null" || $searchArray[$f] === ""){
                    //error
                    $errors[] = "$f:required";
                }

            }

            //verifica tipo
            if($type == "string"){

                //verifica maxlen
                if(strlen($searchArray[$f]) > ($len / 3)){
                    //error maxlen
                    $errors[] = "$f:too_long";
                }

            }

            if($type == "int"){


                //verifica si es entero
                if(($searchArray[$f] != "" && !is_numeric($searchArray[$f])) || $searchArray[$f] - intval($searchArray[$f]) != 0){
                    //error no es numero entero
                    $errors[] = "$f:no_int";
                }
            }

            if($type == "real"){
                //verifica si es real
                if( ($searchArray[$f] != "" && !is_numeric($searchArray[$f])) || floatval($searchArray[$f]) - $searchArray[$f] != 0 ){
                    //error no es numero real
                    $errors[] = "$f:no_decimal";
                }
            }


            $i++;
        }


        $this->errors = array_merge( $this->errors, $errors);
        return (count($errors) == 0);
    }

    /**
     * @param string $sql
     * @param QueryParams|null $params
     */
    public function find(string $sql, QueryParams $params=null): bool
    {
        $this->lastSelectQuery = $sql;

        if($params != null){
            if($this->query_params != null){
                $params->copyFrom($this->query_params);
            }
        }else{
            $params = $this->query_params;
        }

        $this->summary = $this->datasource->execQuery($sql, true, $params );

        if($this->summary->errorNo != 0){
            $this->errors[] = $this->summary->error;
        }

        return ($this->summary->errorNo == 0);
    }

    function fetch()
    {
        return $this->datasource->fetch($this->summary);
    }


    public function fetchAll(): array
    {
        return $this->datasource->fetchAll($this->summary);
    }

    public function getAll(): bool
    {
        $sql = "SELECT * FROM " . $this->table ;
        return $this->find($sql);
    }

    public function getById(array $params): bool
    {
        $searchArray = $this->extractID($params);
        $searchArray = $this->datasource->escape($searchArray);

        $where = $this->datasource->buildSQLFilter($searchArray,"AND");

        $sql = "SELECT * FROM " . $this->table . " WHERE $where";
        return $this->find($sql);
    }



    /**
     * @return Datasource
     */
    public function getDatasource(): Datasource
    {
        return $this->datasource;
    }

    /**
     * @return QueryInfo
     */
    public function getSummary(): QueryInfo
    {
        return $this->summary;
    }

    function extractID($searchArray): array
    {
        $condition = array();

        foreach ($this->id as $key ) {
            $condition[$key] = (isset($searchArray[$key]))? $searchArray[$key] : null;
        }

        return $condition;
    }

    /**
     * @param QueryParams $params
     */
    function setQueryFilters(QueryParams $params){
        $this->query_params = $params;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    protected function buildSelectBy($searchArray): string
    {
        $searchArray = $this->getDatasource()->escape($searchArray);

        $where = $this->getDatasource()->buildSQLFilter($searchArray, "AND");

        return "SELECT * FROM " . $this->table . " WHERE ". $where;
    }
}