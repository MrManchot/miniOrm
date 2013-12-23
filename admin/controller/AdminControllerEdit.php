<?php

	class AdminControllerEdit extends AdminController {
		
		public function beforeDisplay() {
			
			$obj = new $this->vars['obj']($this->vars['id']);
		}
		
	}