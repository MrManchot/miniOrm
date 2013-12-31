<?php

	class AdminControllerEdit extends AdminController {

		public function beforeDisplay() {
			if(class_exists($this->vars['obj'])) {
				$obj = new $this->vars['obj']();
			} else {
				$obj = new Obj($this->vars['obj']);
			}
			if($this->vars['id']) {
				$this->currentObj = $obj::load($this->vars['id']);
			} else {
				$this->currentObj = $obj;
			}
		}
		
		public function displayContent() {
			parent::displayContent();
			AdminHelperForm::displayForm($this->currentObj->vDescribe);
		}

	}