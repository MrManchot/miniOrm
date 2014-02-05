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
			$this->tables = $this->getTables();
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
		
		public function getTables() {
			$result = Db::inst()->exec("SHOW TABLES");
			while ($row = $result->fetch(PDO::FETCH_NUM)) {
			    $tables[] = str_replace(_MO_DB_PREFIX_, '', $row[0]);
			}
			return $tables;
		}

}