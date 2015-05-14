<?php

class expresslytest2ModuleFrontController extends ModuleFrontControllerCore {


    public function __construct() {
        parent::__construct();
    }

    public function init() {
        $this->page_name = 'xly2';
        $this->display_column_left = true;
        $this->display_column_right = true;
        parent::init();
    }
}