<?php

namespace Box\Mod\Odoo\Api;

use Api_Abstract;

class Admin extends Api_Abstract
{
    public function get_config()
    {
        $extensionService = $this->di['mod_service']('extension');
        return $extensionService->getConfig('mod_odoo');
    }

    public function getTaxGroups()
    {

    }

    public function save($data): array
    {
        $required = [
            'odoo_url' => 'Odoo URL is required',
            'odoo_db' => 'Odoo DB is required',
            'odoo_username' => 'Odoo Username is required',
            'odoo_api_key' => 'Odoo API key is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $extensionService = $this->di['mod_service']('extension');

        $extensionService->setConfig([
            'ext' => 'mod_odoo',
            'odoo_url' => $data['odoo_url'],
            'odoo_db' => $data['odoo_db'],
            'odoo_username' => $data['odoo_username'],
            'odoo_api_key' => $data['odoo_api_key']
        ]);

        return ['success' => true];
    }
}