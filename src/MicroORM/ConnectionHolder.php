<?php
declare(strict_types=1);

namespace MicroORM;


use Exception;

class ConnectionHolder
{

    private static ?ConnectionHolder $instance = null;

    /**
     * ConnectionHolder constructor.
     */
    public function __construct()
    {
        $this->lib = [];
    }

    /**
     * @return ConnectionHolder
     */
    public static function getInstance(): ConnectionHolder
    {
        if(self::$instance == null){
            self::$instance = new ConnectionHolder();
        }

        return self::$instance;
    }



    protected array $lib;
    protected ?string $default = null;

    /**
     * @param $name
     * @param Datasource $connection
     * @param bool $isDefault
     */
    public function add($name, Datasource $connection, bool $isDefault=true): void
    {
        $this->lib[$name] = $connection;

        if($isDefault){
            $this->default=$name;
        }
    }

    /**
     * @param string $name
     * @return Datasource|null
     */
    function getConnection(string $name): ?Datasource
    {
        return $this->lib[$name] ?? null;
    }

    /**
     * @return Datasource|null
     */
    function getDefaultConnection(): ?Datasource
    {
        return $this->getConnection($this->default);
    }

    function loadConfig(array $config_array): void
    {
        if(is_array($config_array)){
            $first = true;
            foreach ($config_array as $config){

                    $ds = new Datasource($config["host"], $config["db"], $config["user"], $config["pass"], $config["port"] ?? 3306);


                    if(isset($config["utf8"]) && $config["utf8"]){
                        $ds->setUtf8();
                    }

                    if(isset($config["charset"])){
                        $ds->setCharset($config["charset"]);
                    }

                    if(isset($config["timezone"])){
                        $ds->setTimeZone($config["timezone"]);
                    }

                    $isDefault = false;
                    if($first || strtolower((string)$config["name"]) == "main"){
                        $isDefault = true;
                        $first = false;
                    }


                    $this->add($config["name"], $ds, $isDefault);


            }
        }
    }

}