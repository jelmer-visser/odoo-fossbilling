<?php

namespace Box\Mod\Odoo\Services;

use Box_Event;
use Obuchmann\OdooJsonRpc\Odoo;

class OdooService
{
    public function __construct(protected Odoo $odoo)
    {
    }

    public function updateOrCreatePartner(Box_Event $event): void
    {
        $service = $event->getDi()['mod_service']('client');
        $userId = $event->getParameters()['id'];
        $user = $service->get(['id' => $userId]);
        $ref = 'FOSSBilling_' . $user->id;

        $partnerData = [
            "name" => $user->first_name . " " . $user->last_name,
            "street" => $user->address_1,
            "city" => $user->city,
            "zip" => $user->postcode,
            "phone" => $user->phone,
            "email" => $user->email,
            "is_company" => (bool) $user->company,
            "customer_rank" => 1,
            "ref" => $ref
        ];

        $partner = $this->odoo
            ->model('res.partner')
            ->where('email', '=', $user->email)
            ->where('ref', '=', $ref)
            ->first();

        if ($partner) {
            $this->odoo->updateById('res.partner', $partner->id, $partnerData);
            $event->getDi()['logger']->setChannel('odoo')->info('Partner updated: ' . json_encode($partnerData));
        } else {
            $this->odoo->create('res.partner', $partnerData);
            $event->getDi()['logger']->setChannel('odoo')->info('Partner created: ' . json_encode($partnerData));
        }
    }
}