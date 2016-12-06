<?php

use Expressly\Entity\Customer;
use Expressly\Entity\Phone;
use Expressly\Event\CustomerMigrateEvent;
use Expressly\Exception\GenericException;
use Expressly\Exception\UserExistsException;
use Expressly\Subscriber\CustomerMigrationSubscriber;

class expresslymigratecompleteModuleFrontController extends ModuleFrontControllerCore
{
    private $email;

    public function init()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        $uuid = $_GET['uuid'];

        if (empty($uuid)) {
            Tools::redirect($this->context->shop->getBaseURL());
            return;
        }

        $app = $this->module->getApp();
        $dispatcher = $this->module->getDispatcher();
        $existing = false;

        try {
            $merchant = $app['merchant.provider']->getMerchant();
            $event = new CustomerMigrateEvent($merchant, $uuid);
            $dispatcher->dispatch(CustomerMigrationSubscriber::CUSTOMER_MIGRATE_DATA, $event);

            $json = $event->getContent();
            if (!$event->isSuccessful()) {
                // TODO: hold exception definitions in common
                if (!empty($json['code']) && $json['code'] == 'USER_ALREADY_MIGRATED') {
                    $existing = true;

                    throw new UserExistsException();
                }

                throw new GenericException(Expressly::processError($event));
            }

            $email = $json['migration']['data']['email'];
            $id = CustomerCore::customerExists($email, true);
            $psCustomer = new CustomerCore();

            if ($id) {
                $this->email = $email;
                $psCustomer = new CustomerCore($id);

                $app['logger']->warning(sprintf(
                    'User %s already exists in the store',
                    $email
                    //$merchant->getName()
                ));

                $event = new CustomerMigrateEvent($merchant, $_GET['uuid'], CustomerMigrateEvent::EXISTING_CUSTOMER);
                $existing = true;
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
                    $psCustomer->birthday = $customer['dob'];
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
                    if (!empty($address['companyName'])) {
                        $psAddress->company = $address['companyName'];
                    }
                    if (!empty($customer['taxNumber'])) {
                        $psAddress->vat_number = $customer['taxNumber'];
                    }

                    $psAddress->postcode = $address['zip'];
                    $psAddress->city = $address['city'];

                    $iso2 = $countryCodeProvider->getIso2($address['country']);
                    $psAddress->id_country = CountryCore::getByIso($iso2);

                    if (!is_null($phone)) {
                        if ($phone['type'] == Phone::PHONE_TYPE_MOBILE && empty($psAddress->phone_mobile)) {
                            $psAddress->phone_mobile = $phone['number'];
                        } else {
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
                try {
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
                } catch (\Exception $e) {
                    $app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
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
            Tools::redirect('https://prod.expresslyapp.com/api/redirect/migration/' . $uuid . '/success');
            return;

        } catch (\Exception $e) {
            $app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
        }

        if (!$existing) {
            Tools::redirect('https://prod.expresslyapp.com/api/redirect/migration/' . $uuid . '/failed');
            return;
        }

        $this->context->smarty->assign(array('shop_base_url' => $this->context->shop->getBaseURL()));
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $this->addJS(_THEME_JS_DIR_ . 'index.js');

        $this->context->smarty->assign(array(
            'HOOK_HOME' => Hook::exec('displayHome'),
            'HOOK_HOME_TAB' => Hook::exec('displayHomeTab'),
            'HOOK_HOME_TAB_CONTENT' => Hook::exec('displayHomeTabContent'),
            'EMAIL' => $this->email
        ));

        $this->setTemplate('migratecomplete.tpl');
    }
}