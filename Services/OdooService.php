<?php

namespace Box\Mod\Odoo\Services;

use Box\Mod\Client\Service;
use Box\Mod\Odoo\Request\Method;
use Box_Event;
use Box_Exception;
use Model_Client;
use Model_Invoice;
use Obuchmann\OdooJsonRpc\Odoo;

class OdooService
{
    public function __construct(protected Odoo $odoo)
    {
    }

    /**
     * @throws Box_Exception
     */
    public function updateOrCreatePartner(Box_Event $event): void
    {
        $user = $this->getUser($event);
        $partner = $this->getPartner($event);

        $partnerData = [
            "name" => $user->getFullName(),
            "street" => $user->address_1,
            "city" => $user->city,
            "zip" => $user->postcode,
            "phone" => $user->phone,
            "email" => $user->email,
            "is_company" => $user->type === 'company',
            "customer_rank" => 1,
            "ref" => $this->getClientRef($user)
        ];

        if ($partner) {
            $this->odoo->updateById('res.partner', $partner->id, $partnerData);
            $event->getDi()['logger']->setChannel('odoo')->info('Partner updated: ' . json_encode($partnerData));
        } else {
            $this->odoo->create('res.partner', $partnerData);
            $event->getDi()['logger']->setChannel('odoo')->info('Partner created: ' . json_encode($partnerData));
        }
    }

    /**
     * @throws Box_Exception
     */
    public function createOrUpdateInvoice(Box_Event $event): void
    {
        $invoice = $this->getInvoice($event);
        $partner = $this->getPartner($event, $invoice['client']['id']);

        $countryDomain = new Odoo\Request\Arguments\Domain();
        $countryDomain->where('name', '=', $invoice['taxname']);

        $country_ids = $this->odoo->search('res.country', $countryDomain);

        $taxId = null;


        if (count($country_ids) > 0) {
            $country_id = $country_ids[0];

            $taxDomain = new Odoo\Request\Arguments\Domain();
            $taxDomain->where('country_id', '=', $country_id);
            $taxDomain->where('amount', '=', $invoice['taxrate']);
            $taxDomain->where('type_tax_use', '=', 'sale');

            $tax_ids = $this->odoo->search('account.tax', $taxDomain);

            if (count($tax_ids) > 0) {
                $taxId = $tax_ids[0];
            }
        }

        $invoiceData = [
            'partner_id' => $partner->id,
            'move_type' => 'out_invoice',
            'ref' => $this->getInvoiceRef($invoice),
            'name' => $invoice['serie_nr'],
            'payment_state' => $invoice['status'] === 'paid' ? 'paid' : 'not_paid',
            'invoice_date' => $invoice['created_at'],
            'invoice_date_due' => $invoice['due_at'],
            'invoice_line_ids' => $this->prepareInvoiceLines($invoice['lines'], $taxId)
        ];

        $invoiceDomain = new Odoo\Request\Arguments\Domain();
        $invoiceDomain->where('ref', '=', $this->getInvoiceRef($invoice));

        $odooInvoice = $this->odoo->search('account.move', $invoiceDomain);

        if(empty($odooInvoice)) {
            $invoiceId = $this->odoo->create('account.move', $invoiceData);
            $response = $this->odoo->execute(new Method('account.move', 'post', [$invoiceId]));
            $event->getDi()['logger']->setChannel('odoo')->info('Invoice created: ' . json_encode($response));
        } else {
            $invoiceId = $odooInvoice[0];

            $deleteLines = [];

            if ($invoiceId) {
                $existingLines = $this->odoo->read('account.move', [$invoiceId], ['invoice_line_ids']);
                $existingLineIds = $existingLines[0]->invoice_line_ids;

                $deleteLines = array_map(function($lineId) {
                    return [2, $lineId, 0];
                }, $existingLineIds);
            }

            $invoiceData['invoice_line_ids'] = array_merge($deleteLines, $invoiceData['invoice_line_ids']);

            $this->odoo->execute(new Method('account.move', 'button_draft', [$invoiceId]));
            $this->odoo->updateById('account.move', $invoiceId, $invoiceData);

            $response = $this->odoo->execute(new Method('account.move', 'post', [$invoiceId]));

            $event->getDi()['logger']->setChannel('odoo')->info('Invoice updated: ' . json_encode($response));
        }
    }

    /**
     * @throws Box_Exception
     */
    public function markInvoiceAsPaid(Box_Event $event): void
    {
        $invoice = $this->getInvoice($event);
        $partner = $this->getPartner($event, $invoice['client']['id']);

        $journalDomain = new Odoo\Request\Arguments\Domain();
        $journalDomain->where('type', '=', 'sale');
        $journals = $this->odoo->searchRead('account.journal', $journalDomain, ['id']);

        $invoiceDomain = new Odoo\Request\Arguments\Domain();
        $invoiceDomain->where('ref', '=', $this->getInvoiceRef($invoice));

        $odooInvoice = $this->odoo->search('account.move', $invoiceDomain);

        $invoiceId = $odooInvoice[0];

        $paymentData = [
            "active_model" => "account.move",
            'payment_type' => 'inbound',
            'partner_type' => 'customer',
            'partner_id' => $partner->id,
            'amount' => $invoice['total'],
            'journal_id' => $journals[0]->id,
            "active_ids" => [$invoiceId]
        ];

        $wizardId = $this->odoo->write('account.payment.register', [], $paymentData);

        $event->getDi()['logger']->setChannel('odoo')->info('Invoice payed: ' . json_encode($wizardId));
    }

    private function prepareInvoiceLines(array $items, int $taxId) : array
    {
        $lines = [];
        foreach ($items as $item) {
            $line = [
                'name' => $item['title'],
                'quantity' => $item['quantity'],
                'price_unit' => $item['price'],
            ];

            if ($taxId) {
                $line['tax_ids'] = [[6, 0, [$taxId]]];
            }

            $lines[] = [
                0,
                0,
                $line
            ];
        }
        return $lines;
    }

    private function getClientRef(Model_Client $user): string
    {
        return 'FOSSBilling_' . $user->id;
    }

    private function getInvoiceRef(array $invoice): string
    {
        return 'FOSSBilling_' . $invoice['id'];
    }

    /**
     * @throws Box_Exception
     */
    private function getUser(Box_Event $event, int $userId = null): Model_Client
    {
        /** @var Service $service */
        $service = $event->getDi()['mod_service']('client');

        if (!$userId) {
            $userId = $event->getParameters()['id'];
        }

        return $service->get(['id' => $userId]);
    }

    /**
     * @throws Box_Exception
     */
    private function getPartner(Box_Event $event, $userId = null): ?object {
        $user = $this->getUser($event, $userId);

        return $this->odoo
            ->model('res.partner')
            ->where('email', '=', $user->email)
            ->where('ref', '=', $this->getClientRef($user))
            ->first();
    }

    /**
     * @throws Box_Exception
     */
    private function getInvoice(Box_Event $event): array
    {
        $di = $event->getDi();

        /** @var Service $service */
        $service = $di['mod_service']('invoice');
        $invoiceId = $event->getParameters()['id'];
        $invoice = $di['db']->load('Invoice', $invoiceId);

        return $service->toApiArray($invoice);
    }
}