<?php

use Buzz\Exception\ExceptionInterface;
use Expressly\Event\CustomerMigrateEvent;

class expresslymigratestartModuleFrontController extends ModuleFrontControllerCore
{
    private $response;

    public function init()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        $merchant = $this->module->app['merchant.provider']->getMerchant();
        $event = new CustomerMigrateEvent($merchant, $_GET['uuid']);

        try {
            $this->module->dispatcher->dispatch('customer.migrate.popup', $event);

            if (!$event->isSuccessful()) {
                throw new Expressly\Exception\GenericException(Expressly::processError($event));
            }

            $this->response = $event->getResponse();
        } catch (ExceptionInterface $e) {
            $this->module->app['logger']->addError(Expressly\Exception\ExceptionFormatter::format($e));

            ToolsCore::redirect('/');
        } catch (\Exception $e) {
            $this->module->app['logger']->addError(Expressly\Exception\ExceptionFormatter::format($e));

            ToolsCore::redirect('/');
        }

        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $this->addJS(_THEME_JS_DIR_.'index.js');

        $this->context->smarty->assign(array(
            'HOOK_HOME' => Hook::exec('displayHome'),
            'HOOK_HOME_TAB' => Hook::exec('displayHomeTab'),
            'HOOK_HOME_TAB_CONTENT' => Hook::exec('displayHomeTabContent'),
            'EXPRESSLY_POPUP' => $this->response->getContent()
        ));

        $this->setTemplate('migratestart.tpl');
    }
}