<?php


namespace MicroORM;


use Exception;

/**
 * Class baseDAO
 *
 * Provides a data access object (DAO) for managing database interactions.
 * It includes methods for creating, updating, deleting, and validating database records.
 */
class baseDAO
{

    private ?Datasource $datasource = null;
    private string $table;
    private array $id;

    private $enableHistory;
    private $historyTable;
    private $historyMap = false;
    /**
     * @var QueryInfo
     */
    private QueryInfo $summary;
    /**
     * @var array
     */
    private array $errors;
    private ?string $lastSelectQuery = null;
    private bool $validate = true;
    /**
     * @var QueryParams
     */
    private QueryParams $query_params;

    private ?string $mainAlias = null;

    /**
     * Constructor for initializing the object with a table, identifier, and optional datasource name.
     *
     * @param string $table The name of the database table.
     * @param array $id The identifier values for the table.
     * @param string|null $datasource_name Optional name of the datasource connection.
     * @return void
     * @throws Exception If no connection is found.
     */
    function __construct(string $table, array $id, ?string $datasource_name=null) {

        if(!$datasource_name){
            $datasource = ConnectionHolder::getInstance()->getDefaultConnection();
        }else{
            $datasource = ConnectionHolder::getInstance()->getConnection($datasource_name);
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
     * @return string|null
     */
    public function getMainAlias(): ?string
    {
        return $this->mainAlias;
    }

    /**
     * @param string|null $mainAlias
     */
    public function setMainAlias(?string $mainAlias): void
    {
        $this->mainAlias = $mainAlias;
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
    public function setValidate(bool $validate): void
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



    function setHistory($table, $map): void
    {
        $this->enableHistory = true;

        $this->historyTable=$table;
        $this->historyMap=$map;
    }

    function _history($searchArray): ?QueryInfo{
        if($this->enableHistory){
            return $this->datasource->_insert($this->historyTable, $searchArray);
        }
        return null;
    }

    function &insert($searchArray, $escape=true): QueryInfo
    {
        if($escape){
            $searchArray = $this->datasource->escape($searchArray);
        }

        $this->summary = $this->datasource->_insert($this->table, $searchArray);



        $this->_history($searchArray);
        return $this->summary;

    }

    function &update($searchArray, $condition, $escape=true): QueryInfo
    {
        if($escape){
            $condition = $this->datasource->escape($condition);
            $searchArray = $this->datasource->escape($searchArray);
        }

        $this->summary = $this->datasource->_update($this->table, $searchArray, $condition);

        $this->_history([...$condition,...$searchArray ]);
        return $this->summary;

    }

    function &delete($condition, $escape=true): QueryInfo
    {


        if($escape){
            $condition = $this->datasource->escape($condition);
        }


        $this->summary= $this->datasource->_delete($this->table, $condition);

        $this->_history($condition);
        return $this->summary;

    }

    /**
     * @param $searchArray
     * @return bool
     */
    public function save($searchArray): bool
    {



        $idArray = $this->datasource->escape($this->extractID($searchArray, false));


        if(!$this->datasource->existBy($this->table, $idArray)){
            if($this->validate){
                if(!$this->validate($searchArray)){
                    return false;
                }
            }

            $this->summary = $this->insert($searchArray);

        }else{
            if($this->validate){
                if(!$this->validate($searchArray, false)){
                    return false;
                }
            }

            foreach ($this->id as $key ) {
                unset($searchArray[$key]);
            }
            $updateData = $this->datasource->escape($searchArray);
            $this->summary = $this->update($updateData, $idArray, false);

        }

        if($this->summary->errorNo != 0){
            $this->addSummaryError();
        }

        return ($this->summary->errorNo == 0);
    }

    private function addSummaryError(){
        $this->errors[] = $this->summary->sql . ":" . $this->summary->error;
    }

    private function validate($searchArray, $validateAll = true): bool
    {
        $errors = array();

        $searchFields = array_keys($searchArray);
        $fields = array_keys($searchArray);


        if($validateAll){
            $fields_all = "*";
        }else{
            $fields_all = implode(',', $fields);
        }

        $sql = "SELECT $fields_all FROM " . $this->table . " LIMIT 0";
        $summary =$this->datasource->execQuery($sql);
        $total = $this->datasource->getNumFields($summary);

        $i = 0;


        $mysql_data_type_hash = array(
            1 => 'tinyint',
            2 => 'smallint',
            3 => 'int',
            4 => 'float',
            5 => 'double',
            7 => 'timestamp',
            8 => 'bigint',
            9 => 'mediumint',
            10 => 'date',
            11 => 'time',
            12 => 'datetime',
            13 => 'year',
            14 => 'newdate',
            16 => 'bit',
            245 => 'json',
            246 => 'decimal',
            247 => 'enum',
            248 => 'set',
            249 => 'tiny_blob',
            250 => 'medium_blob',
            251 => 'long_blob',
            252 => 'blob',
            253 => 'varchar',
            254 => 'char',
            255 => 'geometry'
        );


        while ($i < $total) {

            $field_info = $this->datasource->getFieldInfo($summary, $i);
            $f = $field_info->name;
            $type = $field_info->type;
            $len = $field_info->max_length;
            $flag = explode(" ", $this->datasource->getFieldFlags($summary, $i));

            //verifica requerido
            if(in_array("not_null", $flag)){

                if(!isset($searchArray[$f]) || $searchArray[$f] === "null" || $searchArray[$f] === ""){
                    //error
                    $errors[] = "$f:required";
                }

            }

            //si el campo está en los que se desea validar
            if(in_array($f, $searchFields)) {

                //verifica tipo
                if ($mysql_data_type_hash[$type] == "string") {

                    //verifica maxlen
                    if (strlen($searchArray[$f]) > ($len / 3)) {
                        //error maxlen
                        $errors[] = "$f:too_long";
                    }

                }

                if ($mysql_data_type_hash[$type] == "int") {


                    //verifica si es entero
                    if (($searchArray[$f] != "" && !is_numeric($searchArray[$f])) || $searchArray[$f] - intval($searchArray[$f]) != 0) {
                        //error no es numero entero
                        $errors[] = "$f:no_int";
                    }
                }

                if ($mysql_data_type_hash[$type] == "real") {
                    //verifica si es real
                    if (($searchArray[$f] != "" && !is_numeric($searchArray[$f])) || floatval($searchArray[$f]) - $searchArray[$f] != 0) {
                        //error no es numero real
                        $errors[] = "$f:no_decimal";
                    }
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
     * @return bool
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
            $this->addSummaryError();
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
        $sql = $this->getBaseQuery() . $this->table ;
        return $this->find($sql);
    }

    public function getById(array $params): bool
    {
        $searchArray = $this->extractID($params);
        $searchArray = $this->datasource->escape($searchArray);

        $where = $this->datasource->buildSQLFilter($searchArray,"AND");

        $sql = $this->getBaseQuery() . " WHERE $where";
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

    function extractID($searchArray, $prependAlias = true): array
    {
        $condition = array();
        $alias = $this->getMainAlias();

        if(!$prependAlias || $alias == null){
            $alias = '';
        }else{
            $alias .= ".";
        }

        foreach ($this->id as $key ) {
            $condition[$alias . $key] = (isset($searchArray[$key]))? $searchArray[$key] : null;
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

        return $this->getBaseQuery() .  " WHERE ". $where;
    }

    protected function getBaseQuery(): string
    {
        return "SELECT * FROM " . $this->table ;
    }

    function StartTransaction(): bool
    {
        return $this->getDatasource()->StartTransaction();
    }


    function CommitTransaction(): bool
    {
        return $this->getDatasource()->CommitTransaction();
    }

    function RollBackTransaction(): bool
    {
        return $this->getDatasource()->RollBackTransaction();
    }
}
