<?php

use Expressly\Entity\Customer;
use Expressly\Entity\Phone;
use Expressly\Event\CustomerMigrateEvent;

class expresslymigratecompleteModuleFrontController extends ModuleFrontControllerCore
{
    public function init()
    {
        // get key from url
        if (empty($_GET['uuid'])) {
            Tools::redirect('/');
        }

        // get json
        $merchant = $this->module->app['merchant.provider']->getMerchant();
        $event = new CustomerMigrateEvent($merchant, $_GET['uuid']);
        $this->module->dispatcher->dispatch('customer.migrate.complete', $event);

        $json = $event->getResponse();

        if (!empty($json['code'])) {
            // record error

            Tools::redirect('/');
        }

        if (empty($json['migration'])) {
            // record error

            Tools::redirect('/');
        }

        // 'user_already_migrated' should be proper error message, not a plain string
        if ($json == 'user_already_migrated') {
            Tools::redirect('/');
        }

        $email = $json['migration']['data']['email'];
        $id = CustomerCore::customerExists($email, true);
        $psCustomer = new CustomerCore();

        if ($id) {
            $psCustomer = new CustomerCore($id);
        } else {
            $customer = $json['migration']['data']['customerData'];

            $psCustomer->firstname = $customer['firstName'];
            $psCustomer->lastname = $customer['lastName'];
            $psCustomer->email = $email;
            $psCustomer->passwd = md5('xly' . microtime());
            $psCustomer->id_gender = $customer['gender'] && $customer['gender'] == Customer::GENDER_FEMALE ? 2 : 1;
            $psCustomer->newsletter = true;
            $psCustomer->optin = true;

            if (!empty($customer['dob'])) {
                $psCustomer->birthday = date('Y-m-d', $customer['dob']);
            }
            if (!empty($customer['companyName'])) {
                $psCustomer->company = $customer['companyName'];
            }

            $psCustomer->add();

            // Addresses
            foreach ($customer['addresses'] as $address) {
                $phone = !empty($customer['phones'][$address['phone']]) ?: null;
                $psAddress = new AddressCore();

                $psAddress->id_customer = $psCustomer->id;
                $psAddress->id_country = 3;
                $psAddress->alias = $address['addressAlias'];
                $psAddress->firstname = $address['firstName'];
                $psAddress->lastname = $address['lastName'];

                if (!empty($address['address1'])) {
                    $psAddress->address1 = $address['address1'];
                }
                if (!empty($address['address2'])) {
                    $psAddress->address2 = $address['address2'];
                }

                $psAddress->postcode = $address['zip'];
                $psAddress->city = $address['city'];

                if (!is_null($phone)) {
                    if ($phone['type'] == Phone::PHONE_TYPE_MOBILE) {
                        $psAddress->phone_mobile = $phone['number'];
                    } elseif ($phone['type'] == Phone::PHONE_TYPE_HOME) {
                        $psAddress->phone = $phone['number'];
                    }
                }

                $psAddress->add();
            }
        }

        $cartId = $psCustomer->getLastCart(false);
        $psCart = new CartCore($cartId, $this->context->language->id);
        $psCart->id_currency = 1;

        if (is_null($cartId)) {
            $psCart->id_customer = $id;
            $psCart->add();
        }

        if (!empty($json['migration']['cart'])) {
            if (!empty($json['migration']['cart']['productId'])) {
                $psCart->updateQty(1, $json['migration']['cart']['productId']);
            }

            if (!empty($json['migration']['cart']['couponCode'])) {
                $psCouponId = CartRuleCore::getIdByCode($json['migration']['cart']['couponCode']);

                if ((int)$psCouponId > 0) {
                    $psCart->addCartRule($psCouponId);
                }
            }

            $psCart->add();
        }

        $mailUser = ConfigurationCore::get('PS_MAIL_USER');
        $mailPass = ConfigurationCore::get('PS_MAIL_PASSWD');
        if (!empty($mailUser) && !empty($mailPass)) {
            $context = ContextCore::getContext();

            if (MailCore::Send(
                $context->language->id,
                'password_query',
                MailCore::l('Password query confirmation'),
                $mail_params = array(
                    '{email}' => $psCustomer->email,
                    '{lastname}' => $psCustomer->lastname,
                    '{firstname}' => $psCustomer->firstname,
                    '{url}' => $context->link->getPageLink('password', true, null,
                        'token=' . $psCustomer->secure_key . '&id_customer=' . (int)$psCustomer->id)
                ),
                $psCustomer->email,
                sprintf('%s %s', $psCustomer->firstname, $psCustomer->lastname)
            )
            ) {
                $context->smarty->assign(array('confirmation' => 2, 'customer_email' => $psCustomer->email));
            }
        }

        // Forcefully log user in
        $psCustomer->logged = 1;
        $this->context->customer = $psCustomer;

        $this->context->cookie->id_compare = isset($this->context->cookie->id_compare) ?
            $this->context->cookie->id_compare : CompareProductCore::getIdCompareByIdCustomer($psCustomer->id);
        $this->context->cookie->id_customer = (int)($psCustomer->id);
        $this->context->cookie->customer_lastname = $psCustomer->lastname;
        $this->context->cookie->customer_firstname = $psCustomer->firstname;
        $this->context->cookie->logged = 1;
        $this->context->cookie->is_guest = $psCustomer->isGuest();
        $this->context->cookie->passwd = $psCustomer->passwd;
        $this->context->cookie->email = $psCustomer->email;
        $this->context->cookie->id_cart = $psCart instanceof CartCore ? (int)$psCart->id : $psCart;
        $this->context->cookie->write();

        Tools::redirect('/');

        parent::init();
    }
}