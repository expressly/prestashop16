<?php

use Expressly\Entity\Route;
use Expressly\Presenter\PingPresenter;
use Expressly\Presenter\RegisteredPresenter;
use Expressly\Route\BatchCustomer;
use Expressly\Route\BatchInvoice;
use Expressly\Route\CampaignMigration;
use Expressly\Route\CampaignPopup;
use Expressly\Route\Ping;
use Expressly\Route\Registered;
use Expressly\Route\UserData;
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
        $route = $app['route.resolver']->process($query);

        if ($route instanceof Route) {
            switch ($route->getName()) {
                case Ping::getName():
                    echo \Tools::jsonEncode($this->ping());
                    die;
                    break;
                case Registered::getName():
                    echo \Tools::jsonEncode($this->registered());
                    die;
                    break;
                case UserData::getName():
                    $data = $route->getData();
                    echo \Tools::jsonEncode(Customers::getByEmail($app, $data['email']));
                    die;
                    break;
                case CampaignPopup::getName():
                    $data = $route->getData();
                    \Tools::redirect("migratestart&fc=module&module=expressly&uuid={$data['uuid']}");
                    break;
                case CampaignMigration::getName():
                    $data = $route->getData();
                    \Tools::redirect("migratecomplete&fc=module&module=expressly&uuid={$data['uuid']}");
                    break;
                case BatchCustomer::getName():
                    echo \Tools::jsonEncode(Customers::getBulk($app));
                    die;
                    break;
                case BatchInvoice::getName():
                    echo \Tools::jsonEncode(Invoices::getBulk($app));
                    die;
                    break;
            }
        }

        if (http_response_code() === 401) {
            die;
        }

        \Tools::redirect('/');
    }

    private function ping()
    {
        $presenter = new PingPresenter();

        return $presenter->toArray();
    }

    private function registered()
    {
        $presenter = new RegisteredPresenter();

        return $presenter->toArray();
    }
}