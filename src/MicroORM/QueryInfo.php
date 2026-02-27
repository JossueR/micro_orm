<?php
declare(strict_types=1);

namespace MicroORM;

use mysqli_result;

class QueryInfo
{
    public ?mysqli_result $result = null;

    public ?int $total = null;

    public ?int $new_id = null;

    public ?int $allRows = null;

    public ?int $errorNo = null;

    public ?string $error = null;

    public bool $inArray = true;

    public ?string $sql = null;
}