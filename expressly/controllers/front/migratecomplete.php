<?php

use Expressly\Entity\Customer;
use Expressly\Entity\Phone;
use Expressly\Event\CustomerMigrateEvent;
use Expressly\Exception\GenericException;

class expresslymigratecompleteModuleFrontController extends ModuleFrontControllerCore
{
    public function init()
    {
        // get key from url
        if (empty($_GET['uuid'])) {
            Tools::redirect('/');
        }

        $app = $this->module->getApp();
        $dispatcher = $this->module->getDispatcher();

        try {
            $merchant = $app['merchant.provider']->getMerchant();
            $event = new CustomerMigrateEvent($merchant, $_GET['uuid']);
            $dispatcher->dispatch('customer.migrate.data', $event);

            $json = $event->getContent();

            if (!$event->isSuccessful()) {
                throw new GenericException(Expressly::processError($event));
            }


            if (!empty($json['code'])) {
                // record error

                Tools::redirect('/');
            }

            if (empty($json)) {
                // record error

                Tools::redirect('/');
            }

            // 'user_already_migrated' should be proper error message, not a plain string
            if ($json == 'user_already_migrated') {
                throw new GenericException(sprintf('User %s already migrated', $_GET['uuid']));
            }

            $email = $json['migration']['data']['email'];
            $id = CustomerCore::customerExists($email, true);
            $psCustomer = new CustomerCore();


            if ($id) {
                $psCustomer = new CustomerCore($id);

                $app['logger']->addWarning(sprintf(
                    'User %s already exists in the store %s',
                    $email,
                    $merchant->getName()
                ));

                $event = new CustomerMigrateEvent($merchant, $_GET['uuid'], CustomerMigrateEvent::EXISTING_CUSTOMER);
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
                    $countryCodeProvider = $app['country_code.provider'];
                    $phone = isset($address['phone']) ?
                        (!empty($customer['phones'][$address['phone']]) ?
                            $customer['phones'][$address['phone']] : null) : null;
                    $psAddress = new AddressCore();

                    $psAddress->id_customer = $psCustomer->id;
                    $psAddress->alias = !empty($address['alias']) ? $address['alias'] : 'default';
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

                    $iso2 = $countryCodeProvider->getIso2($address['country']);
                    $psAddress->id_country = CountryCore::getByIso($iso2);

                    if (!is_null($phone)) {
                        if ($phone['type'] == Phone::PHONE_TYPE_MOBILE) {
                            $psAddress->phone_mobile = $phone['number'];
                        } elseif ($phone['type'] == Phone::PHONE_TYPE_HOME) {
                            $psAddress->phone = $phone['number'];
                        }
                    }

                    $psAddress->add();
                }

                // Forcefully log user in, if we just created them
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

                // Dispatch password creation email
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
            }

            // Add items (product/coupon) to cart
            if (!empty($json['cart'])) {
                $cartId = $psCustomer->getLastCart(false);
                $psCart = new CartCore($cartId, $this->context->language->id);

                if ($psCart->id == null) {
                    $psCart = new CartCore();
                    $psCart->id_language = $this->context->language->id;
                    $psCart->id_currency = (int)($this->context->cookie->id_currency);
                    $psCart->id_shop_group = (int)$this->context->shop->id_shop_group;
                    $psCart->id_shop = $this->context->shop->id;
                    $psCart->id_customer = $psCustomer->id;
                    $psCart->id_shop = $this->context->shop->id;
                    $psCart->id_address_delivery = 0;
                    $psCart->id_address_invoice = 0;
                    $psCart->add();
                }

                if (!empty($json['cart']['productId'])) {
                    $psProduct = new ProductCore($json['cart']['productId']);

                    if ($psProduct->checkAccess($psCustomer->id)) {
                        $psProductAttribute = $psProduct->getDefaultIdProductAttribute();

                        if ($psProductAttribute > 0) {
                            $psCart->updateQty(1, $json['cart']['productId'], $psProductAttribute, null, 'up', 0,
                                $this->context->shop);
                        }
                    }
                }

                if (!empty($json['cart']['couponCode'])) {
                    $psCouponId = CartRuleCore::getIdByCode($json['cart']['couponCode']);

                    if ($psCouponId) {
                        $psCart->addCartRule($psCouponId);
                    }
                }

                if ($this->context->cookie->logged) {
                    $this->context->cookie->id_cart = $psCart instanceof CartCore ? (int)$psCart->id : $psCart;
                }
            }

            $this->context->cookie->write();

            $dispatcher->dispatch('customer.migrate.success', $event);
        } catch (\Exception $e) {
            $app['logger']->addError(Expressly\Exception\ExceptionFormatter::format($e));
        }

        Tools::redirect('/');
    }
}