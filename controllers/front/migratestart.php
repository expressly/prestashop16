<?php

use Expressly\Event\CustomerMigrateEvent;

class expresslymigratestartModuleFrontController extends ModuleFrontControllerCore
{
    private $response;

    public function init()
    {
        $this->page_name = 'xly';
        $this->display_column_left = true;
        $this->display_column_right = true;

        try {
            $merchant = $this->module->app['merchant.provider']->getMerchant();
            $event = new CustomerMigrateEvent($merchant, $_GET['uuid']);
            $this->module->dispatcher->dispatch('customer.migrate.start', $event);
            $this->response = $event->getResponse();
        } catch (\Exception $e) {
            // TODO: Log
        }

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'xly_popup' => $this->response
        ));
        $this->setTemplate('migratestart.tpl');
    }
}