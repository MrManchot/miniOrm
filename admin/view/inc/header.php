<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>miniOrm Admin</title>
	
	<script src="//code.jquery.com/jquery-git2.js"></script>
	<script src="//code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.3/js/bootstrap.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/ckeditor/4.2/ckeditor.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/ckeditor/4.2/adapters/jquery.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/select2/3.4.5/select2.min.js"></script>
	<script src="asset/miniOrm.js"></script>
	
	<link href="//cdnjs.cloudflare.com/ajax/libs/ckeditor/4.2/contents.css" rel="stylesheet">
	<link href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" rel="stylesheet">
	<link href="//cdnjs.cloudflare.com/ajax/libs/select2/3.4.5/select2.min.css" media="screen" rel="stylesheet">
	<link href="//cdnjs.cloudflare.com/ajax/libs/select2/3.4.5/select2-bootstrap.css" media="screen" rel="stylesheet">
	<link href="//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" rel="stylesheet">
	<link href="asset/miniOrm.css" rel="stylesheet">
	
</head>
<body>


<div class="body-wrap">
    <!--container-->
    <div class="container" id="content">
    
    	<!-- Header -->
		<nav class="navbar navbar-default" role="navigation">
		  <div class="container-fluid">
		
		    <div class="navbar-header">
		      <a class="navbar-brand" href="index.php">MiniOrm</a>
		    </div>
		
			<form class="navbar-form navbar-left">
				<div class="form-group">
					<select id="select_table" name="obj" class="select" onChange="window.location.replace('?controller=edit&obj=' + this.options[this.selectedIndex].value)">
						<option></option>
					<?php foreach($this->tables as $table) { ?>
						<option value="<?=$table?>" <?=(isset($this->get['obj']) && $this->get['obj']==$table ? 'selected="selected"' : '')?>>
							<?=AdminHelperForm::displayLabel($table)?>
						</option>
					<?php } ?>
					</select>
				</div>
			</form>
		
		  </div>
		</nav>