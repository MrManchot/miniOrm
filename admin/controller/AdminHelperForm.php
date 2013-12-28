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
			
			if($inputInfo['extra']=='auto_increment') return;
			
			return '<div class="form-group">'."\n".
				'<label class="col-sm-3 control-label" for="'.$inputName.'">'.self::displayLabel($inputName).' :</label>'."\n".
				self::displayFormInput($inputName, $inputInfo)."\n".
			'</div>'."\n";
			
		}
		
		private static function displayFormInput($inputName, $inputInfo) {

			$colSize = 9;
			$maxlength = isset($inputInfo['size']) ? $inputInfo['size'] : null;

			if(in_array($inputInfo['type'], array("text", "mediumtext", "longtext"))) {
				$htmlInput = '<textarea name="'.$inputName.'" class="form-control"></textarea>'."\n";
			} else {
				if($inputInfo['type']=="date") {
					$colSize = 6;
					$type = "date";
				} elseif(in_array($inputInfo['type'], array("datetime", "timestamp"))) {
					$colSize = 6;
					$type = "datetime";
				} elseif(in_array($inputInfo['type'], array("int", "tinyint", "bigint"))) {
					$colSize = 3;
					$type = "number";
				} else {
					if($maxlength < 20) {
						$colSize = 3;
					} elseif($maxlength < 20) {
						$colSize = 6;
					}
					$type = "text";
				}
				$htmlInput = '<input type="'.$type.'" '.($maxlength ? 'maxlength="'.$maxlength.'"' : '' ).' class="form-control input-'.$type.'" name="'.$inputName.'" />'."\n";
			}
			return '<div class="col-sm-'.$colSize.'">'."\n".$htmlInput.'</div>'."\n";
		}

		private static function displayLabel($name) {
			return ucfirst(str_replace('_', ' ', $name));
		}
	
}