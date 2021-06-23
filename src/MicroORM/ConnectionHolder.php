<?php


namespace MicroORM;


use Exception;

class ConnectionHolder
{
    /**
     * @var ConnectionHolder
     */
    private static $instance;

    /**
     * ConnectionHolder constructor.
     */
    public function __construct()
    {
        $this->lib = array();
    }

    /**
     * @return ConnectionHolder
     */
    public static function getInstance(){
        if(self::$instance == null){
            self::$instance = new ConnectionHolder();
        }

        return self::$instance;
    }



    private $lib;
    private $default;

    /**
     * @param $name
     * @param Datasource $connection
     * @param bool $isDefault
     */
    public function add($name, $connection, $isDefault=true){
        $this->lib[$name] = $connection;

        if($isDefault){
            $this->default=$name;
        }
    }

    /**
     * @param $name
     * @return Datasource|null
     */
    function getConnection($name){
        if(isset($this->lib[$name])){
            return $this->lib[$name];
        }else{
            return null;
        }
    }

    /**
     * @return Datasource|null
     */
    function getDefaultConnection(){
        return $this->getConnection($this->default);
    }

    function loadConfig($config_array){
        if(is_array($config_array)){
            foreach ($config_array as $config){
                try {
                    $ds = new Datasource($config["host"], $config["db"], $config["user"], $config["pass"]);


                    if(isset($config["utf8"]) && $config["utf8"]){
                        $ds->setUtf8();
                    }

                    if(isset($config["timezone"])){
                        $ds->setTimeZone($config["timezone"]);
                    }


                    $this->add($config["name"], $ds);

                } catch (Exception $e) {

                }
            }
        }
    }

}