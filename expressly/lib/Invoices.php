<?php

namespace Module\Expressly;

use Expressly\Entity\Invoice;
use Expressly\Entity\Order;
use Expressly\Exception\ExceptionFormatter;
use Expressly\Exception\GenericException;
use Expressly\Presenter\BatchInvoicePresenter;
use Pimple\Container;

class Invoices
{
    public static function getBulk(Container $app)
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);

        $invoices = array();

        try {
            if (!property_exists($json, 'customers')) {
                throw new GenericException('Invalid JSON input');
            }

            foreach ($json->customers as $customer) {
                if (!property_exists($customer, 'email')) {
                    continue;
                }

                $psCustomer = new \CustomerCore();
                $psCustomer->getByEmail($customer->email);

                if (empty($psCustomer->id)) {
                    continue;
                }

                $from = \DateTime::createFromFormat('Y-m-d', $customer->from, new \DateTimeZone('UTC'));
                $to = \DateTime::createFromFormat('Y-m-d', $customer->to, new \DateTimeZone('UTC'));
                $invoice = new Invoice();
                $invoice->setEmail($customer->email);
                $psOrderIds = \OrderCore::getOrdersIdByDate($customer->from, $customer->to, $psCustomer->id);
                foreach ($psOrderIds as $id) {
                    $psOrder = new \OrderCore($id);

                    // if is paid
                    $orderDate = new \DateTime($psOrder->date_add, new \DateTimeZone('UTC'));
                    $orderDate = \DateTime::createFromFormat('Y-m-d', $orderDate->format('Y-m-d'), new \DateTimeZone('UTC'));
                    if ($orderDate >= $from && $orderDate < $to) {
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
                }

                $invoices[] = $invoice;
            }
        } catch (\Exception $e) {
            $app['logger']->error(ExceptionFormatter::format($e));
        }

        $presenter = new BatchInvoicePresenter($invoices);
        return $presenter->toArray();
    }
}