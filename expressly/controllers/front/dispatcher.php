<?php

use Expressly\Presenter\PingPresenter;
use Module\Expressly\Customers;
use Module\Expressly\Invoices;

class expresslydispatcherModuleFrontController extends ModuleFrontControllerCore
{
    public function init()
    {
        if (empty($_GET['xly'])) {
            Tools::redirect('/');
        }

        $query = $_GET['xly'];
        $app = $this->module->getApp();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                if (preg_match("/^\/?expressly\/api\/batch\/invoice\/?$/", $query, $matches)) {
                    Invoices::getBulk($app);
                }

                if (preg_match("/^\/?expressly\/api\/batch\/customer\/?$/", $query, $matches)) {
                    Customers::getBulk($app);
                }
                break;
            case 'GET':
                if (preg_match("/^\/?expressly\/api\/ping\/?$/", $query, $matches)) {
                    $presenter = new PingPresenter();
                    die(Tools::jsonEncode($presenter->toArray()));
                }

                if (preg_match("/^\/?expressly\/api\/user\/([\w-\.]+@[\w-\.]+)\/?$/", $query, $matches)) {
                    $email = array_pop($matches);
                    Customers::getByEmail($app, $email);
                }

                if (preg_match("/^\/?expressly\/api\/([\w-]+)\/?$/", $query, $matches)) {
                    $key = array_pop($matches);
                    Tools::redirect("migratestart&fc=module&module=expressly&uuid={$key}");
                }
                break;
        }

        Tools::redirect('/');
    }
}