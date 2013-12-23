<?php

include('miniOrm.php');

$article = Article::load(1);
echo $article->id_rubrique->titre;

$db = Db::inst();