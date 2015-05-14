<?php

use Expressly\Client;

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
        $this->confirmUninstall = 'Sure';

//        $this->registerHook('moduleRoutes');

//        $context = ContextCore::getContext();
//        $language = $context->language->id;
//        $shop = $context->shop->id;
//        $dispatcher = DispatcherCore::getInstance();
//        $dispatcher->addRoute('module-expressly-dispatcher', 'expressly/api/{id}', 'expresslydispatcher', $language, array(), array(), $shop);
////        $dispatcher->addRoute('module-expressly-migrate', 'expressly/api/user/{email}', 'expresslymigrate', $language, array(), array(), $shop);
//        $dispatcher->addRoute('module-expressly-ping', 'module/expressly/expressly/ping', 'expresslyping', null, array(
////            'module' => array('regexp' => 'expressly', 'param' => 'module'),
////            'controller' => array('regexp' => 'expresslyping', 'param' => 'controller')
//        ), array(
////            'params' => array('fc' => 'module')
//        ), null);
//        $dispatcher->addRoute('module-expressly-migrate', 'xly/hi', 'expresslytest2', Context::getContext()->language->id, array(), array(), Context::getContext()->shop->id);

//        $this->registerHook('moduleRoutes');
//        $dispatcher->addRoute('expressly_migrate', 'xly/hi', 'expresslytest2');

//        var_dump($dispatcher->createUrl('module-expressly-ping'));
//        die;

        $this->setup();

//        if (!Configuration::get(''))
    }

    /*
     * Run up method of db migration
     * Create password
     * Dispatch password event
     * Dispatch hostname event
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        return 'hi';
    }

    /*
     * Run down method of db migration
     * Tell xly that we're uninstalling?
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    private function setup()
    {
        require __DIR__ . '/vendor/autoload.php';
        $expressly = new Client();
        $this->app = $expressly->getApp();
        $this->dispatcher = $this->app['dispatcher'];
    }
}