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

        $merchant = $this->module->app['merchant.provider']->getMerchant();
        $event = new CustomerMigrateEvent($merchant, $_GET['uuid']);

        try {
            $this->module->dispatcher->dispatch('customer.migrate.popup', $event);

            if (!$event->isSuccessful()) {
                throw new \Exception(Expressly::processError($event));
            }

            $this->response = $event->getResponse();
        } catch (\Exception $e) {
            $this->module->app['logger'] = (string)$e;

            ToolsCore::redirect('/');
        }

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'xly_popup' => $this->response->getContent()
        ));
        $this->setTemplate('migratestart.tpl');
    }
}