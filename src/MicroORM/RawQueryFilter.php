<?php
declare(strict_types=1);

namespace MicroORM;


class RawQueryFilter
{
    private string $sql;

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

    public function __toString()
    {
        return $this->getSql();
    }
}