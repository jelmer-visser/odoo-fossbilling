<?php

namespace Box\Mod\Odoo;

require_once __DIR__ . "/vendor/autoload.php";

use Box\Mod\Odoo\Factories\ServiceFactory;
use Box_Event;
use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;

class Service implements InjectionAwareInterface
{
    private Container $di;

    public function setDi(Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public static function onAfterClientProfileUpdate(Box_Event $event) {
        self::updateOrCreatePartner($event);
    }

    public static function onAfterAdminClientUpdate(Box_Event $event) {
        self::updateOrCreatePartner($event);
    }

    public static function onAfterClientSignUp(Box_Event $event) {
        self::updateOrCreatePartner($event);
    }

    public static function onAfterAdminCreateClient(Box_Event $event) {
        self::updateOrCreatePartner($event);
    }

    private static function updateOrCreatePartner(Box_Event $event) {
        $odooService = ServiceFactory::getInstance()->getOdooService($event->getDi());
        $odooService->updateOrCreatePartner($event);
    }
}