<?php

namespace Box\Mod\Odoo\Context;

class Context extends \Obuchmann\OdooJsonRpc\Odoo\Context
{
    public function __construct(
        protected array $context = []
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return $this->context;
    }
}