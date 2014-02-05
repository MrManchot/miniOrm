<?php

	class AdminControllerEdit extends AdminController {

		public function beforeDisplay() {
			if(class_exists($this->get['obj'])) {
				$obj = new $this->get['obj']();
			} else {
				$obj = new Obj($this->get['obj']);
			}
			if($this->id) {
				$this->currentObj = $obj::load($this->get['id']);
			} else {
				$this->currentObj = $obj;
			}
		}

	}