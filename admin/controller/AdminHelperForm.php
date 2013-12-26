<?php

class AdminHelperForm {

		public static function displayForm($vDescribe) {

			$html = '<form class="form-horizontal" role="form">';
			foreach($vDescribe as $inputName => $inputInfo) {
				$html .= self::displayFormControl($inputName, $inputInfo);
			}
			$html .= '<button type="submit" class="btn btn-primary pull-right">Submit</button>
			</form>';
			echo $html;
			
			# Debug
			echo '<pre>'.print_r($vDescribe, true).'</pre>';
		}
		
		private static function displayFormControl($inputName, $inputInfo) {
			return '<div class="form-group">'."\n".
				'<label class="col-sm-3 control-label" for="'.$inputName.'">'.self::displayLabel($inputName).' :</label>'."\n".
				'<div class="col-sm-9">'."\n".
				self::displayFormInput($inputName, $inputInfo)."\n".
				'</div>'."\n".
			'</div>'."\n";
		}
		
		private static function displayFormInput($inputName, $inputInfo) {
			
			$textareaTypes = array("text", "mediumtext", "longtext");
			$datetimeTypes = array("datetime", "datetime");
			$numberTypes = array("int", "tinyint", "bigint ");
			
			if(in_array($inputInfo['type'], $textareaTypes)) {
				return '<textarea name="'.$inputName.'" class="form-control"></textarea>'."\n";
			} else {
				if(in_array($inputInfo['type'], $datetimeTypes)) {
					$type = "datetime";
				} elseif(in_array($inputInfo['type'], $numberTypes)) {
					$type = "number";
				} else {
					$type = "text";
				}
				return '<input type="'.$type.'" class="form-control" name="'.$inputName.'" />'."\n";
			}
			
		}

		private static function displayLabel($name) {
			return ucfirst(str_replace('_', ' ', $name));
		}
	
}