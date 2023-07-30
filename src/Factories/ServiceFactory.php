<?php

namespace Box\Mod\Odoo\Factories;

use Box\Mod\Odoo\Services\OdooService;
use Obuchmann\OdooJsonRpc\Odoo;
use Obuchmann\OdooJsonRpc\Odoo\Config;
use Pimple\Container;

class ServiceFactory
{
    private static self $instance;

    private array $services;

    protected function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getOdooService(Container $di): OdooService
    {
        return $this->getService(OdooService::class, new OdooService($this->getOdoo($di)));
    }

    private function getOdoo(Container $di): Odoo
    {
        $extensionService = $di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_odoo');

        return $this->getService(Odoo::class, new Odoo(
            new Config(
                $config['odoo_db'],
                $config['odoo_url'],
                $config['odoo_username'],
                $config['odoo_api_key']
            )
        ));
    }

    private function getService(string $name, $service = null)
    {
        if (isset($this->services[$name]) === false) {
            $this->services[$name] = $service ?? new $name;
        }

        return $this->services[$name];
    }
}