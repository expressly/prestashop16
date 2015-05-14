<?php

class expresslymigrateModuleFrontController extends ModuleFrontControllerCore
{
    public function __construct()
    {
        parent::__construct();
    }

    public function init()
    {
        $this->page_name = 'xly';
        $this->display_column_left = true;
        $this->display_column_right = true;

        var_dump($_REQUEST);
        die;

        parent::init();
    }
}