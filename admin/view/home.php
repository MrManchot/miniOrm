<form>
	<select name="obj" onChange="window.location.replace('?controller=edit&obj=' + this.options[this.selectedIndex].value)">
	<?php foreach($this->tables as $table) { ?>
		<option value="<?=$table?>"><?=$table?></option>
	<?php } ?>
	</select>
</form>