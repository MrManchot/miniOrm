<?php

	class AdminControllerEdit extends AdminController {

		public function beforeDisplay() {
			$obj = new $this->vars['obj']();
			$this->currentObj = $obj::load($this->vars['id']);
		}
		
		public function displayContent() {
			parent::displayContent();
			AdminHelperForm::displayForm($this->currentObj->vDescribe);
		}

	}