<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Expressly extends ModuleCore
{
    public $setup = false;
    public $app;
    public $dispatcher;

    public function __construct()
    {
        $this->name = "expressly";
        $this->tab = "advertising_marketing";
        $this->version = "0.1.3";
        $this->author = "Expressly";
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array(
            'min' => '1.6',
            'max' => _PS_VERSION_
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Expressly';
        $this->description = 'https://buyexpressly.com';
        $this->confirmUninstall = 'Are you sure you want to uninstall?';
    }

    public function getApp()
    {
        $this->setup();

        return $this->app;
    }

    public function getDispatcher()
    {
        $this->setup();

        return $this->dispatcher;
    }

    /**
     * Cannot be autoloaded in the constructor as Avalara disagrees with PHP namespaces being anymore than 1 level deep.
     * Application instantiation has to now be called explicitly.
     */
    private function setup()
    {
        if (!$this->setup) {
            /**
             * Has been reported to Avalara, awaiting response.
             * Temporary fix until Avalara fixes their autoloading issue, which violates PHP namespaces.
             */
            spl_autoload_unregister('avalaraAutoload');
            require_once __DIR__ . '/vendor/autoload.php';

            $expressly = new Expressly\Client(Expressly\Entity\MerchantType::PRESTASHOP);
            $app = $expressly->getApp();

            // override MerchantProvider
            $app['merchant.provider'] = $app->share(function () {
                return new Module\Expressly\MerchantProvider();
            });

            $this->app = $app;
            $this->dispatcher = $this->app['dispatcher'];

            $this->setup = true;
        }
    }

    public function getContent()
    {
        $errors = array();
        $this->setup();

        try {
            if (Tools::isSubmit('submitExpresslyPreferences')) {
                $register = Tools::getValue('REGISTER');
                $provider = $this->app['merchant.provider'];
                $merchant = $provider->getMerchant();

                $merchant
                    ->setImage(Tools::getValue('EXPRESSLY_PREFERENCES_IMAGE'))
                    ->setTerms(Tools::getValue('EXPRESSLY_PREFERENCES_TERMS'))
                    ->setPolicy(Tools::getValue('EXPRESSLY_PREFERENCES_POLICY'));
//                    ->setDestination(Tools::getValue('EXPRESSLY_PREFERENCES_DESTINATION'))
//                    ->setOffer(Tools::getValue('EXPRESSLY_PREFERENCES_OFFER'));

                $event = new Expressly\Event\PasswordedEvent($merchant);

                if (!empty($register)) {
                    $event = new Expressly\Event\MerchantEvent($merchant);
                    $this->dispatcher->dispatch('merchant.register', $event);
                } else {
                    $this->dispatcher->dispatch('merchant.update', $event);
                }

                if (!$event->isSuccessful()) {
                    throw new Expressly\Exception\GenericException(self::processError($event));
                }

                if (!empty($register)) {
                    $content = $event->getContent();

                    $merchant
                        ->setUuid($content['merchantUuid'])
                        ->setPassword($content['secretKey']);

                }

                $provider->setMerchant($merchant);
            }
        } catch (Buzz\Exception\RequestException $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError('We had trouble talking to the server. The server could be down; please contact expressly.');
        } catch (\Exception $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError((string)$e->getMessage());
        }

        /*
         * This is a terrible method to display errors
         * TODO: Evaluate an override that makes sense
         */

        return implode('', $errors) . $this->displayForm();
    }

    public static function processError($event)
    {
        $content = $event->getContent();
        $message = array(
            $content['description']
        );

        $addBulletpoints = function ($key, $title) use ($content, &$message) {
            if (!empty($content[$key])) {
                $message[] = '<br>';
                $message[] = $title;
                $message[] = '<ul>';

                foreach ($content[$key] as $point) {
                    $message[] = "<li>{$point}</li>";
                }

                $message[] = '</ul>';
            }
        };

        // TODO: translatable
        $addBulletpoints('causes', 'Possible causes:');
        $addBulletpoints('actions', 'Possible resolutions:');

        return implode('', $message);
    }

    public function displayForm()
    {
        $uuid = ConfigurationCore::get('EXPRESSLY_PREFERENCES_UUID');
        $image = ConfigurationCore::get('EXPRESSLY_PREFERENCES_IMAGE');
        $terms = ConfigurationCore::get('EXPRESSLY_PREFERENCES_TERMS');
        $policy = ConfigurationCore::get('EXPRESSLY_PREFERENCES_POLICY');
        $password = ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD');

        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Expressly',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Shop Image URL',
                        'desc' => sprintf(
                            '<img src="%s" width="100px" height="100px" style="border: 1px solid black;"/>',
                            $image
                        ),
                        'name' => 'EXPRESSLY_PREFERENCES_IMAGE',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Terms and Conditions URL',
                        'desc' => sprintf(
                            'URL for the Terms & Conditions for your store. <a href="%s">Check</a>',
                            $terms
                        ),
                        'name' => 'EXPRESSLY_PREFERENCES_TERMS',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Privacy Policy URL',
                        'desc' => sprintf('URL for the Privacy Policy for your store. <a href="%s">Check</a>', $policy),
                        'name' => 'EXPRESSLY_PREFERENCES_POLICY',
                        'required' => true
                    ),
//                    array(
//                        'type' => 'text',
//                        'label' => 'Destination',
//                        'desc' => 'Redirect destination after checkout',
//                        'name' => 'EXPRESSLY_PREFERENCES_DESTINATION',
//                        'required' => true
//                    ),
//                    array(
//                        'type' => 'switch',
//                        'label' => 'Show offers',
//                        'desc' => 'Show offers after checkout',
//                        'name' => 'EXPRESSLY_PREFERENCES_OFFER',
//                        'is_bool' => true,
//                        'values' => array(
//                            array(
//                                'id' => 'active_on',
//                                'value' => true,
//                                'label' => 'Enabled'
//                            ),
//                            array(
//                                'id' => 'active_off',
//                                'value' => false,
//                                'label' => 'Disabled'
//                            )
//                        ),
//                        'required' => true
//                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Password',
                        'desc' => 'Expressly password for your store',
                        'name' => 'EXPRESSLY_PREFERENCES_PASSWORD',
                        'disabled' => true
                    ),
                    array(
                        'type' => 'hidden',
                        'name' => 'REGISTER'
                    )
                ),
                'submit' => array(
                    'title' => (empty($uuid) && empty($password)) ? 'Register' : 'Save'
                )
            )
        );

        $form = new HelperFormCore();
        $form->module = $this;
        $form->show_toolbar = false;
        $form->table = $this->table;
        $form->identifier = $this->identifier;
        $form->submit_action = 'submitExpresslyPreferences';
        $form->token = Tools::getAdminTokenLite('AdminModules');

        $form->fields_value['EXPRESSLY_PREFERENCES_IMAGE'] = $image;
        $form->fields_value['EXPRESSLY_PREFERENCES_TERMS'] = $terms;
        $form->fields_value['EXPRESSLY_PREFERENCES_POLICY'] = $policy;
//        $form->fields_value['EXPRESSLY_PREFERENCES_DESTINATION'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION');
//        $form->fields_value['EXPRESSLY_PREFERENCES_OFFER'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_OFFER');
        $form->fields_value['EXPRESSLY_PREFERENCES_PASSWORD'] = $password;
        $form->fields_value['REGISTER'] = (empty($uuid) && empty($password)) ? true : false;

        $language = new LanguageCore((int)ConfigurationCore::get('PS_LANG_DEFAULT'));
        $form->default_form_language = $language->id;

        return $form->generateForm(array($fields));
    }

    public function install($register = false)
    {
        if (!$register && !parent::install()) {
            return false;
        }

        $url = sprintf('http://%s', $_SERVER['HTTP_HOST']);
        $url = rtrim($url, '/') . '/';

        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_UUID', '');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_IMAGE',
            sprintf('%simg/%s', $url, ConfigurationCore::get('PS_LOGO')));
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_TERMS', $url . 'index.php?id_cms=3&controller=cms');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_POLICY', $url . 'index.php?id_cms=3&controller=cms');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_HOST', $url);
//        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION', '/');
//        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER', true);
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD', '');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PATH',
            '?controller=dispatcher&fc=module&module=expressly&xly=');

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        try {
            $this->setup();
            $merchant = $this->app['merchant.provider']->getMerchant();
            $this->dispatcher->dispatch('merchant.delete', new Expressly\Event\PasswordedEvent($merchant));
        } catch (\Exception $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
        }

        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_IMAGE');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_TERMS');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_POLICY');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_HOST');
//        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_DESTINATION');
//        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_OFFER');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PASSWORD');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PATH');

        return true;
    }
}