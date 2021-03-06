<?php

use Buzz\Exception\ExceptionInterface;
use Expressly\Event\CustomerMigrateEvent;
use Expressly\Subscriber\CustomerMigrationSubscriber;

class expresslymigratestartModuleFrontController extends ModuleFrontControllerCore
{
    private $response;

    public function init()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        $app = $this->module->getApp();
        $dispatcher = $this->module->getDispatcher();
        $uuid = $_GET['uuid'];

        if (empty($uuid)) {
            Tools::redirect($this->context->shop->getBaseURL());
            return;
        }

        $merchant = $app['merchant.provider']->getMerchant();
        $event = new CustomerMigrateEvent($merchant, $uuid);

        try {
            $dispatcher->dispatch(CustomerMigrationSubscriber::CUSTOMER_MIGRATE_POPUP, $event);

            if (!$event->isSuccessful()) {
                throw new Expressly\Exception\GenericException(Expressly::processError($event));
            }

            $this->response = $event->getResponse();
        } catch (ExceptionInterface $e) {
            $app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            Tools::redirect('https://prod.expresslyapp.com/api/redirect/migration/' . $uuid . '/failed');
            return;
        } catch (\Exception $e) {
            $app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            Tools::redirect('https://prod.expresslyapp.com/api/redirect/migration/' . $uuid . '/failed');
            return;
        }

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
            'EXPRESSLY_POPUP' => $this->response->getContent()
        ));

        $this->setTemplate('migratestart.tpl');
    }
}