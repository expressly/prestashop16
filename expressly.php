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

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_HOST', sprintf('//%s', $_SERVER['HTTP_HOST']));
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION', '');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER', true);
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD', Expressly\Entity\Merchant::createPassword());
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PATH',
            sprintf('//%s/%s', $_SERVER['HTTP_HOST'], '?controller=dispatcher&fc=module&module=expressly&xly='));

        $merchant = $this->app['merchant.provider']->getMerchant();
        $this->dispatcher->dispatch('merchant.register', new Expressly\Event\MerchantEvent($merchant));

        return true;
    }

    public function getContent()
    {
        return ConfigurationCore::get('EXPRESSLY_PREFERENCES_HOST');
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
}