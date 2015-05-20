<?php

class expresslydispatcherModuleFrontController extends ModuleFrontControllerCore
{
    public function init()
    {
        if (empty($_GET['xly'])) {
            Tools::redirect('/');
        }

        $query = $_GET['xly'];

        if (preg_match("/^\/?expressly\/api\/ping\/?$/", $query)) {
            Tools::redirect('ping&fc=module&module=expressly');
        }

        if (preg_match("/^\/?expressly\/api\/user\/([\w-\.]+@[\w-\.]+)\/?$/", $query, $matches)) {
            $email = array_pop($matches);

            var_dump($email);die;
            Tools::redirect("sendcustomer&fc=module&module=expressly&email={$email}");
        }

        if (preg_match("/^\/?expressly\/api\/([\w-]+)\/?$/", $query, $matches)) {
            $key = array_pop($matches);
            Tools::redirect("migratestart&fc=module&module=expressly&uuid={$key}");
        }

        Tools::redirect('/');
    }
}