<?php

namespace MicroORM;

interface IQueryLogger
{
    public function log(string $sql, bool $isSelect, QueryInfo $summary);
}