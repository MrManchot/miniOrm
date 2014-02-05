<?php

	class AdminControllerHome extends AdminController {
		
		public function beforeDisplay() {
			$result = Db::inst()->exec("SHOW TABLES");
			while ($row = $result->fetch(PDO::FETCH_NUM)) {
			    $tables[] = str_replace(_MO_DB_PREFIX_, '', $row[0]);
			}
			$this->tables = $tables;
			
		}

	}
