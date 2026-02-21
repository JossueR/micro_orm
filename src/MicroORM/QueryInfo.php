<?php
declare(strict_types=1);

namespace MicroORM;

class QueryInfo
{
    public ?object $result = null;

    public ?int $total = null;

    public ?int $new_id = null;

    public ?int $allRows = null;

    public ?int $errorNo = null;

    public ?string $error = null;

    public bool $inArray = true;

    public ?string $sql = null;
}