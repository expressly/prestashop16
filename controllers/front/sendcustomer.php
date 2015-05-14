<?php

use Expressly\Entity\Address;
use Expressly\Entity\Customer;
use Expressly\Entity\Phone;

class expresslysendcustomerModuleFrontController extends ModuleFrontControllerCore {
    public function init() {
        if (empty($_GET['email'])) {
            Tools::redirect('/');
        }

        $email = $_GET['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Tools::redirect('/');
        }

        $customer = new Customer();
        $db = Db::getInstance();

        $psCustomer = $db->getRow($this->getQuery('select_customer', $email));

        if (!empty($psCustomer)) {
            $id = $psCustomer['id_customer'];

            $customer
                ->setFirstName($psCustomer['firstname'])
                ->setLastName($psCustomer['lastname'])
                ->setCompany($psCustomer['company'])
                ->setBirthday(new \DateTime($psCustomer['birthday']))
                ->setDateUpdated(new \DateTime($psCustomer['date_upd']));

            $gender = $psCustomer['id_gender'] ? Customer::GENDER_MALE : Customer::GENDER_FEMALE;
            $customer->setGender($gender);

            $first = true;
            foreach ($db->executeS($this->getQuery('select_address', $id)) as $psAddress) {
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

                if (!empty($psAddress['phone'])) {
                    $phone = new Phone();
                    $phone
                        ->setType(Phone::PHONE_TYPE_HOME)
                        ->setNumber($psAddress['phone'])
                        ->setCountryCode($psAddress['call_prefix']);

                    $customer->addPhone($phone);

                    if (empty($psAddress['phone_mobile'])) {
                        $address->setPhonePosition($customer->getPhoneIndex($phone));
                    }
                }

                if (!empty($psAddress['phone_mobile'])) {
                    $phone = new Phone();
                    $phone
                        ->setType(Phone::PHONE_TYPE_MOBILE)
                        ->setNumber($psAddress['phone_mobile'])
                        ->setCountryCode($psAddress['call_prefix']);

                    $customer->addPhone($phone);

                    if (empty($psAddress['phone'])) {
                        $address->setPhonePosition($customer->getPhoneIndex($phone));
                    }
                }

                $customer->addAddress($address, $first, Address::ADDRESS_BOTH);
                $first = false;
            }
        }

        die(Tools::jsonEncode($customer->toArray()));

        parent::init();
    }

    private function getQuery($key, $data) {
        $queries = array(
            'select_customer' => sprintf('SELECT * FROM `%scustomer` WHERE `email`="%s";', _DB_PREFIX_, $data),
            'select_address' => sprintf('SELECT * FROM `%saddress` a, `%scountry` c WHERE `a`.`id_customer`=%u AND `a`.`id_country` = `c`.`id_country`', _DB_PREFIX_, _DB_PREFIX_, $data)
        );

        return $queries[$key];
    }
}