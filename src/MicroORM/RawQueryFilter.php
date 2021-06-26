<?php


namespace MicroORM;


class RawQueryFilter
{
    /**
     * @var string
     */
    private $sql;

    /**
     * RawQueryFilter constructor.
     * @param string $str
     */
    public function __construct(string $str)
    {
        $this->sql = $str;
    }

    /**
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }


}