miniOrm
=======

Just a mini ORM, for using Object Model and MySQL Abstraction Layer as simply as possible

Todo
--------
* find => load several Objects
* create(array('...')); // new Obj('character', array('...'));
* dynamic relationship (1-1 / 1-n / n-n)
* prefix php define + initialisation


Simple
--------
1 Table = 1 Object Model.
Create, read, update and delete in your database without using any SQL queries. 

Light-weight
--------
Only one file to include and you're ready.
Don't need to configuration your tables, it automaticlly determine your database model. 

Extensible
--------
Only one file to include and you're ready.
Don't need to configuration your tables, it automaticlly determine your database model. 

How to intall ?
--------

Just define your database connection, include the miniOrm.php file on you're ready !

```php
define('_DB_NAME_', 'mini_orm');
define('_DB_LOGIN_', 'root');
define('_DB_MDP_', '');
define('_DB_SERVER_', 'localhost');
define('_DB_PREFIX_', 'mo_');
 
include('miniOrm.php');
```

Create, read, update and delete
--------

```php
$myCharacter = new Obj('character');
$myCharacter->name = 'Conan';
$myCharacter->damage = 10;
$myCharacter->insert();
 
$firstCharacter = Obj::load('character', 1);
$firstCharacter->damage = 12;
echo 'Character damage : '.$firstCharacter->damage.'<br>';
$firstCharacter->update();
 
$firstCharacter->delete();
```

Extend your object
--------

```php
class Charcacter extends Obj {
     
    // Can define relation table, load the Race object for the id_race field
    public $relations = array(
        array('table' => 'race', 'field' => 'id_race')
    );
     
    // Shortcut
    public function __construct() {
        return parent::__construct('character');
    }
 
    public static function load($findme) {
        return parent::load('character', $findme);
    }
 
    // Extends the set function
    // Call setDamage ( 'set' + 'damage' in camel case) before set in in the object
    public function setDamage($damage) {
        $maxDamage = 0;
        switch ($this->race->name) {
            case 'Orc':
                $maxDamage = 10;
            case 'Human':
                $maxDamage = 8;
        }
        if($damage > $maxDamage) $damage = $maxDamage;
        return $damage;
    }
     
}
 
// Shortcut
// $myCharacter = new Charcacter();
$myCharacter = Charcacter::load(array("name = 'Goldorak'"));
$myCharacter->id_race = 2;
$myCharacter->refreshRelation();
// Now you have access to $myCharacter->race as an Obj
 
// Call before the setDamage function. $myCharacter is an Human, so it damage will be 8
$myCharacter->damage = 12;
echo $myCharacter->race->name.' => '.$myCharacter->damage; // Human => 8
```




MySQL Abstraction Layer
--------

```php
// Db::inst() return an access to your database connection
$db = Db::inst();
// Let's create some character (notice, you have to use the table "full name")
$db->insert('mo_character', array('name' => 'Conan', 'damage' => 12));
$db->insert('mo_character', array('name' => 'Rahan', 'damage' => 8));
$db->insert('mo_character', array('name' => 'Toto', 'damage' => 0));
$db->insert('mo_character', array('name' => 'Goldorak', 'damage' => 19));
Db::inst()->update('mo_character', array('damage' => 1), array('name="Toto"') );
// 4 type of select shortcut :
$characterDamage = $db->getValue('damage', 'mo_character', array('name = "Conan"'));
// return : 12
$characterInformations = $db->getRow('*', 'mo_character', array('id_character = 1'));
// return : Array ( [id_character] => 1 [name] => MrManchot [damage] => 10 )
$twoStrongestCharacters = $db->getArray('name, damage', 'mo_character', 'damage > 5', NULL, 'damage DESC', '0,2');
// return : Array ( [0] => Array ( [name] => Goldorak [damage] => 19 ) [1] => Array ( [name] => Conan [damage] => 12 ) )
$twoStrongestCharactersIds = $db->getValueArray('id_character', 'mo_character', 'damage > 5');
// return : Array ( [0] => 1 [1] => 2 [2] => 4 )
 
$db->delete('mo_character', array('name="Toto"') );
$nbCharacters = $db->count('mo_character', array('damage > 10'));
// Try a wrong query...
$db->update('mo_character', array('damage' => 1), array('invalid_field="Toto"') );
$error = $db->error();
```


Full Sample
--------

```php
<?php

// First for follow the sample you have to create the DateBase :
/*
 CREATE TABLE IF NOT EXISTS `mo_character` (
 `id_character` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
 `id_race` int(11) NOT NULL,
 `name` varchar(50) COLLATE latin1_general_ci NOT NULL,
 `damage` tinyint(3) unsigned NOT NULL DEFAULT '0',
 PRIMARY KEY (`id_character`),
 UNIQUE KEY `name` (`name`)
 ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

 CREATE TABLE IF NOT EXISTS `mo_race` (
 `id_race` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) NOT NULL,
 PRIMARY KEY (`id_race`)
 ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

 INSERT INTO `mo_race` (`id_race`, `name`) VALUES (1, 'Human'), (2, 'Orc');
 */

define('_DB_NAME_', 'mini_orm');
define('_DB_LOGIN_', 'root');
define('_DB_MDP_', '');
define('_DB_SERVER_', 'localhost');
define('_DB_PREFIX_', 'mo_');

define('_CACHE_FILE_', 'miniOrm.tmp');
define('_CACHE_DIR_', '');
define('_FREEZE_', false);

include ('miniOrm.php');

// Start using object : no configuration !
$myCharacter= new Obj('character');
// Php Exception, test if the value size is correct
$myCharacter->name= 'Conan the barbarian who has a really really long name';
// Set value
$myCharacter->name= 'Conan';
// Php Exception, test if the type is correct
$myCharacter->damage= 'XX';
// Set value
$myCharacter->damage= 10;
// you may also use :
$myCharacter->hydrate(array('name' => 'Conan', 'damage' => 10));
$myCharacter->insert();
// Select Obj by Id or by Where conditions
$firstCharacter= Obj::load(1, 'character');
$firstCharacter= Obj::load(array("name = 'Conan'"), 'character');
echo 'Character damage : ' . $firstCharacter->damage . '<br/>';
$firstCharacter->damage= 12;
echo 'Character damage : ' . $firstCharacter->damage . '<br/>';
$firstCharacter->update();
// $firstCharacter->save();  Save update if exist, insert else
$firstCharacter->delete();

// You can extend easily an Obj

class Character extends Obj {

	// Can define relation table, load the Race object for the id_race field
	public $relations= array( array('table' => 'race', 'field' => 'id_race'));

	// Shortcut
	public function __construct() {
		return parent::__construct('character');
	}

	public static function load($findme, $table= 'character') {
		return parent::load($findme, $table);
	}

	// Extends the set function
	// Call setDamage ( 'set' + 'damage' in camel case) before set in in the object
	public function setDamage($damage) {
		$maxDamage= 0;
		switch ($this->race->name) {
			case 'Orc' :
				$maxDamage= 10;
			case 'Human' :
				$maxDamage= 8;
		}
		if ($damage > $maxDamage)
			$damage= $maxDamage;
		return $damage;
	}

}

// Shortcut
// $myCharacter = new Character();
$myCharacter= Character::load(array("name = 'Goldorak'"));
$myCharacter->id_race= 2;
$myCharacter->refreshRelation();
// Now you have access to $myCharacter->race as an Obj

// Call before the setDamage function. $myCharacter is an Human, so it damage will be 8
$myCharacter->damage= 12;
echo $myCharacter->race->name . ' => ' . $myCharacter->damage;
// Human => 8

// miniOrm provide too a simple Db access & query shortcut
// Db::inst() return an access to your database connection
$db= Db::inst();
// Let's create some character (notice, you have to use the table "full name")
$db->insert('mo_character', array('name' => 'Conan', 'damage' => 12));
$db->insert('mo_character', array('name' => 'Rahan', 'damage' => 8));
$db->insert('mo_character', array('name' => 'Toto', 'damage' => 0));
$db->insert('mo_character', array('name' => 'Goldorak', 'damage' => 19));
Db::inst()->update('mo_character', array('damage' => 1), array('name="Toto"'));
// 4 type of select shortcut :
$characterDamage= $db->getValue('damage', 'mo_character', array('name = "Conan"'));
// return : 12
$characterInformations= $db->getRow('*', 'mo_character', array('id_character = 1'));
// return : Array ( [id_character] => 1 [name] => MrManchot [damage] => 10 )
$twoStrongestCharacters= $db->getArray('name, damage', 'mo_character', 'damage > 5', NULL, 'damage DESC', '0,2');
// return : Array ( [0] => Array ( [name] => Goldorak [damage] => 19 ) [1] => Array ( [name] => Conan [damage] => 12 ) )
$twoStrongestCharactersIds= $db->getValueArray('id_character', 'mo_character', 'damage > 5');
// return : Array ( [0] => 1 [1] => 2 [2] => 4 )

$db->delete('mo_character', array('name="Toto"'));
$nbCharacters= $db->count('mo_character', array('damage > 10'));
// Try a wrong query...
$db->update('mo_character', array('damage' => 1), array('invalid_field="Toto"'));
$error= $db->error();
echo '<pre>' . print_r($error, true) . '</pre>';
```