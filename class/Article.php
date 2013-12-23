<?php

class Article extends Obj {

    // Define
    protected static $tableStatic = 'articles';

    // Can define relation table, load the Race object for the id_race field
    public $relations = array(
        array('obj' => 'rubrique', 'field' => 'id_rubrique'),
        array('obj' => 'rubrique', 'field' => 'id_secteur'),
    );

}