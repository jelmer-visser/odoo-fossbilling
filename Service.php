<?php

namespace Box\Mod\Odoo;

require_once __DIR__ . "/vendor/autoload.php";

use Box\Mod\Odoo\Factories\ServiceFactory;
use Box_Event;
use Box_Exception;
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

    public static function onAfterAdminInvoiceApprove(Box_Event $event) {
        self::updateOrCreateInvoice($event);
    }

    public static function onAfterAdminInvoiceUpdate(Box_Event $event) {
        self::updateOrCreateInvoice($event);
    }

    public static function onAfterAdminInvoicePaymentReceived(Box_Event $event) {
        self::markInvoiceAsPaid($event);
    }

    public static function onAfterAdminInvoiceDelete(Box_Event $event)
    {
        //TODO: Delete invoice in Odoo
    }

    private static function updateOrCreatePartner(Box_Event $event) {
        try {
            $odooService = ServiceFactory::getInstance()->getOdooService($event->getDi());
            $odooService->updateOrCreatePartner($event);
        } catch (Box_Exception $e) {
            $event->getDi()['logger']->setChannel('odoo')->error($e->getMessage());
        }
    }

    private static function updateOrCreateInvoice(Box_Event $event) {
        try {
            $odooService = ServiceFactory::getInstance()->getOdooService($event->getDi());
            $odooService->createOrUpdateInvoice($event);
        } catch (Box_Exception $e) {
            $event->getDi()['logger']->setChannel('odoo')->error($e->getMessage());
        }
    }

    private static function markInvoiceAsPaid(Box_Event $event) {
        try {
            $odooService = ServiceFactory::getInstance()->getOdooService($event->getDi());
            $odooService->markInvoiceAsPaid($event);
        } catch (Box_Exception $e) {
            $event->getDi()['logger']->setChannel('odoo')->error($e->getMessage());
        }
    }
}