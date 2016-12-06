<?php

namespace Module\Expressly;

use Expressly\Entity\Address;
use Expressly\Entity\Customer;
use Expressly\Entity\Email;
use Expressly\Entity\Phone;
use Expressly\Exception\ExceptionFormatter;
use Expressly\Exception\GenericException;
use Expressly\Presenter\BatchCustomerPresenter;
use Expressly\Presenter\CustomerMigratePresenter;
use Pimple\Container;

class Customers
{
    public static function getByEmail(Container $app, $emailAddress)
    {
        try {
            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                \Tools::redirect('/');
            }

            $customer = new Customer();

            $psCustomer = new \CustomerCore();
            $psCustomer->getByEmail($emailAddress);

            if (!$psCustomer->id) {
                header('HTTP/1.1 404 Unauthorized', true, 404);
                return;
            }

            $customer
                ->setFirstName($psCustomer->firstname)
                ->setLastName($psCustomer->lastname)
                ->setCompany($psCustomer->company)
                ->setDateUpdated(new \DateTime($psCustomer->date_upd));
            $hasDob = $psCustomer->birthday != null && $psCustomer->birthday != '0000-00-00' ;
            if ($hasDob) {
                $customer ->setBirthday(new \DateTime($psCustomer->birthday));
            }
            $gender = $psCustomer->id_gender == 1 ? Customer::GENDER_MALE : Customer::GENDER_FEMALE;
            $customer->setGender($gender);

            $email = new Email();
            $email
                ->setEmail($emailAddress)
                ->setAlias('primary');
            $customer->addEmail($email);

            $first = true;
            $context = \ContextCore::getContext();

            foreach ($psCustomer->getAddresses($context->language->id) as $psAddress) {
                $address = new Address();
                $address
                    ->setFirstName($psAddress['firstname'])
                    ->setLastName($psAddress['lastname'])
                    ->setAddress1($psAddress['address1'])
                    ->setAddress2($psAddress['address2'])
                    ->setCity($psAddress['city'])
                    ->setCompanyName($psAddress['company'])
                    ->setZip($psAddress['postcode'])
                    ->setAlias($psAddress['alias']);

                $psCountry = new \CountryCore($psAddress['id_country']);
                $address->setCountry($psCountry->iso_code);

                if (!empty($psAddress['vat_number'])) {
                    $customer->setTaxNumber($psAddress['vat_number']);
                }

                /*
                 * PrestaShop uses the country prefix from the address, which is logically incorrect.
                 * An address may be in the UK, but the owner may have a DE number, this cannot be handled at current time.
                 * TODO: Find a way that actually works, will require an expressly table to relate customers, phones, and prefix
                 */
                if (!empty($psAddress['phone'])) {
                    $phone = new Phone();
                    $phone
                        ->setType(Phone::PHONE_TYPE_HOME)
                        ->setNumber((string)$psAddress['phone'])
                        ->setCountryCode((int)$psCountry->call_prefix);

                    $customer->addPhone($phone);
                    $address->setPhonePosition($customer->getPhoneIndex($phone));
                }

                if (!empty($psAddress['phone_mobile'])) {
                    $phone = new Phone();
                    $phone
                        ->setType(Phone::PHONE_TYPE_MOBILE)
                        ->setNumber((string)$psAddress['phone_mobile'])
                        ->setCountryCode((int)$psCountry->call_prefix);

                    $customer->addPhone($phone);

                    if (empty($psAddress['phone'])) {
                        $address->setPhonePosition($customer->getPhoneIndex($phone));
                    }
                }

                $customer->addAddress($address, $first, Address::ADDRESS_BOTH);
                $first = false;
            }

            $merchant = $app['merchant.provider']->getMerchant();
            $response = new CustomerMigratePresenter($merchant, $customer, $emailAddress, $psCustomer->id);

            return $response->toArray();
        } catch (\Exception $e) {
            $app['logger']->error(ExceptionFormatter::format($e));
        }

        return array();
    }

    public static function getBulk(Container $app)
    {
        $json = file_get_contents('php://input');
        $json = json_decode($json);

        $users = array();

        try {
            if (!property_exists($json, 'emails')) {
                throw new GenericException('Invalid JSON input');
            }

            foreach ($json->emails as $customer) {
                $id = \CustomerCore::customerExists($customer, true);
                if (!$id) {
                    continue;
                }
                $users[] = $customer;
            }
        } catch (\Exception $e) {
            $app['logger']->error(ExceptionFormatter::format($e));
        }

        $presenter = new BatchCustomerPresenter($users);
        return $presenter->toArray();
    }
}