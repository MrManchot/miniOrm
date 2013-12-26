<?php

class AdminController extends Obj {
	
		protected $viewDir; 
		protected $vars;
		public $currentObj;
	
	    public function __construct() {
	    	$this->initVars();
            $this->beforeDisplay();
			$this->displayHeader();
            $this->displayContent();
            $this->displayFooter();
        }
		
		public function initVars() {
			$this->viewDir = __DIR__.'/../view';
			$this->vars = $_GET;
		}
	
		public function beforeDisplay() {}
		
		public function displayHeader() {
			require_once($this->viewDir.'/inc/header.php');
		}
		
		public function displayContent() {
			require_once($this->viewDir.'/'.$this->vars['controller'].'.php');
		}
		
		public function displayFooter() {
			require_once($this->viewDir.'/inc/footer.php');
		}

}