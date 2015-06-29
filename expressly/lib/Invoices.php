<?php

namespace Module\Expressly;

use Expressly\Entity\Invoice;
use Expressly\Entity\Order;
use Expressly\Presenter\BatchInvoicePresenter;

class Invoices
{
    public static function getBulk()
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);

        $invoices = array();

        foreach ($json->customers as $customer) {
            $psCustomer = new \CustomerCore();
            $psCustomer->getByEmail($customer->email);

            if (empty($psCustomer->id)) {
                continue;
            }

            $invoice = new Invoice();
            $invoice->setEmail($customer->email);
            $psOrderIds = \OrderCore::getOrdersIdByDate($customer->from, $customer->to, $psCustomer->id);
            foreach ($psOrderIds as $id) {
                $psOrder = new \OrderCore($id);

                // if is paid


                $psCurrency = new \CurrencyCore($psOrder->id_currency);
                $tax = (double)$psOrder->total_paid_tax_incl - (double)$psOrder->total_paid_tax_excl;

                $order = new Order();
                $order
                    ->setId($psOrder->reference)
                    ->setDate(new \DateTime($psOrder->date_add))
                    ->setCurrency($psCurrency->iso_code)
                    ->setTotal($psOrder->total_paid_tax_excl, $tax);

                $order->setItemCount(count($psOrder->getCartProducts()));
                $invoice->addOrder($order);
            }

            $invoices[] = $invoice;
        }

        $presenter = new BatchInvoicePresenter($invoices);
        die(\Tools::jsonEncode($presenter->toArray()));
    }
}