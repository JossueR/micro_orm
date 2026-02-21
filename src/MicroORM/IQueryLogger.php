<?php
declare(strict_types=1);

namespace MicroORM;

interface IQueryLogger
{
    public function log(string $sql, bool $isSelect, QueryInfo $summary);
}