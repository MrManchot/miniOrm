<?php

class AdminController extends Obj {

	    public function __construct($controller) {
	    	$this->controller = $controller;
	    	$this->initVars();
            $this->beforeDisplay();
			$this->displayHeader();
            $this->displayContent();
            $this->displayFooter();
        }
		
		public function initVars() {
			$this->viewDir = __DIR__.'/../view';
			$this->get = $_GET;
		}
	
		public function beforeDisplay() {}
		
		public function displayHeader() {
			require_once($this->viewDir.'/inc/header.php');
		}
		
		public function displayContent() {
			require_once($this->viewDir.'/'.$this->controller.'.php');
		}
		
		public function displayFooter() {
			require_once($this->viewDir.'/inc/footer.php');
		}

}