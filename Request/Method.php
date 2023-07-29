<?php

namespace Box\Mod\Odoo\Request;

use Obuchmann\OdooJsonRpc\Odoo\Context;

class Method extends Context
{
    public function __construct(string $model, string $method, protected array $fields = [])
    {
        parent::__construct( $model, $method);
    }

    public function toArray(): array
    {
        return $this->fields;
    }
}