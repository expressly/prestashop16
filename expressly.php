<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Expressly extends ModuleCore
{
    public $app;
    public $dispatcher;

    public function __construct()
    {
        $this->name = "expressly";
        $this->tab = "smart_shopping";
        $this->version = "1.0.0";
        $this->author = "Expressly";
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array(
            'min' => '1.6',
            'max' => _PS_VERSION_
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Expressly';
        $this->description = 'Description.';
        $this->confirmUninstall = 'Are you sure you want to uninstall?';
        require __DIR__ . '/vendor/autoload.php';
        $this->setup();
    }

    private function setup()
    {
        $expressly = new Expressly\Client();
        $app = $expressly->getApp();

        // override MerchantProvider
        $app['merchant.provider'] = $app->share(function ($app) {
            return new Module\Expressly\MerchantProvider();
        });

        $this->app = $app;
        $this->dispatcher = $this->app['dispatcher'];
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_HOST', sprintf('://%s', $_SERVER['HTTP_HOST']));
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION', '/');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER', true);
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD', Expressly\Entity\Merchant::createPassword());
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PATH',
            '?controller=dispatcher&fc=module&module=expressly&xly=');

        $merchant = $this->app['merchant.provider']->getMerchant();
        $this->dispatcher->dispatch('merchant.register', new Expressly\Event\MerchantEvent($merchant));

        return true;
    }

    public function getContent()
    {
        try {
            if (Tools::isSubmit('submitExpresslyPreferences')) {
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION',
                    Tools::getValue('EXPRESSLY_PREFERENCES_DESTINATION'));
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER',
                    Tools::getValue('EXPRESSLY_PREFERENCES_OFFER'));

                if (ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD') != Tools::getValue('EXPRESSLY_PREFERENCES_PASSWORD')) {
                    $oldPassword = ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD');
                    ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD',
                        Tools::getValue('EXPRESSLY_PREFERENCES_PASSWORD'));

                    // Send password update
                    $merchant = $this->app['merchant.provider']->getMerchant(true);
                    $event = new Expressly\Event\MerchantNewPasswordEvent($merchant, $oldPassword);
                    $this->dispatcher->dispatch('merchant.password.update', $event);
                }
            }
        } catch (\Exception $e) {
            // TODO: Log
        }

        return $this->displayForm();
    }

    public function displayForm()
    {
        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Expressly',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Destination',
                        'desc' => 'Redirect destination after checkout',
                        'name' => 'EXPRESSLY_PREFERENCES_DESTINATION',
                        'required' => false
                    ),
                    array(
                        'type' => 'switch',
                        'label' => 'Show offers',
                        'desc' => 'Show offers after checkout',
                        'name' => 'EXPRESSLY_PREFERENCES_OFFER',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => 'Enabled'
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => 'Disabled'
                            )
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Password',
                        'desc' => 'Expressly password for your store',
                        'name' => 'EXPRESSLY_PREFERENCES_PASSWORD',
                        'required' => false
                    )
                ),
                'submit' => array(
                    'title' => 'Save'
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

        $form->fields_value['EXPRESSLY_PREFERENCES_DESTINATION'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION');
        $form->fields_value['EXPRESSLY_PREFERENCES_OFFER'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_OFFER');
        $form->fields_value['EXPRESSLY_PREFERENCES_PASSWORD'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD');

        $language = new LanguageCore((int)ConfigurationCore::get('PS_LANG_DEFAULT'));
        $form->default_form_language = $language->id;

        return $form->generateForm(array($fields));
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        $merchant = $this->app['merchant.provider']->getMerchant();
        $this->dispatcher->dispatch('merchant.delete', new Expressly\Event\MerchantEvent($merchant));

        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_HOST');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_DESTINATION');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_OFFER');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PASSWORD');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PATH');

        return true;
    }
}