<?php

	class AdminControllerHome extends AdminController {

		public function beforeDisplay() {
			
			$tables = array();
			$result = Db::inst()->exec("SHOW TABLES");
			while ($row = $result->fetch(PDO::FETCH_NUM)) {
			    $tables[] = $row[0];
			}
			print_r($tables);
		}
		
		public function displayContent() {
			// parent::displayContent();
			// print_r($this->vars['tables']);
		}

	}
