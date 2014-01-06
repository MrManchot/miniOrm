<?php

	class AdminControllerHome extends AdminController {

		public function beforeDisplay() {
			$result = Db::inst()->exec("SHOW TABLES");
			 while ($row = $result->fetch(PDO::FETCH_NUM)) {
	            echo $row[0].'<br/>';
	        }
		}
		
		public function displayContent() {
			// parent::displayContent();
			// print_r($this->vars['tables']);
		}

	}