<?php

use Expressly\Event\ResponseEvent;

class expresslypingModuleFrontController extends ModuleFrontControllerCore
{
    public function init()
    {
        $dispatcher = $this->module->dispatcher;
        $response = $dispatcher->dispatch('utility.ping', new ResponseEvent())->getResponse();
        die(Tools::jsonEncode($response));
    }
}