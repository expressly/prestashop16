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

        if (preg_match("/^\/?expressly\/api\/user\/([\w]+@[\w]+\.{1}[\w]{1,3}[\.[\w]{2,4}]?)\/?$/", $query, $matches)) {
            $email = array_pop($matches);
            Tools::redirect("sendcustomer&fc=module&module=expressly&email={$email}");
        }

        if (preg_match("/^\/?expressly\/api\/([\w]+)\/?$/", $query, $matches)) {
            $key = array_pop($matches);
            Tools::redirect("migrate&fc=module&module=expressly&key={$key}");
        }

        Tools::redirect('/');
    }
}