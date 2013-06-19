miniOrm
=======
Just a mini ORM, for using Object Model and MySQL Abstraction Layer as simply as possible

Simple, Light-weight & Extensible
--------
+ 1 Table = 1 Object Model. Create, read, update and delete in your database without using any SQL queries. 
+ Only one file to include and you're ready. Don't need to configuration your tables, miniOrm automatically determine your database model. 
+ Extend your object, create easily relation between it, overdie how to set values, automaticly validate fields & type and size and more... 

How to intall ?
--------
Just define your database connection in miniOrm.config.php, include the miniOrm.php file on you're ready !

```php
include('miniOrm.php');
```

Create, read, update and delete
--------

```php
$myCharacter = new Obj('character');
$myCharacter->name = 'Conan';
$myCharacter->damage = 10;
// Or use hydrate() to set multiple fiels
$myCharacter->hydrate(array('name' => 'Conan', 'damage' => 10));
$myCharacter->insert();
// Shortcut :
$myCharacter = Obj::create('character', array('name' => 'Conan', 'damage' => 10));
 
$firstCharacter = Obj::load(1, 'character');
$firstCharacter->damage = 12;
$firstCharacter->update(); // Can use too save() : update() if already exist else insert()
$firstCharacter->delete();

// Get an array of you object :
foreach(Obj::find(array("damage > 2"), 'character') as $strongCharacter) {
	echo $strongCharacter->name.'<br/>';
}
```

Extend your object
--------

```php
class Charcacter extends Obj {
     
    // Can define relation table, load the Race object for the id_race field
    public $relations = array(
        array('table' => 'race', 'field' => 'id_race')
    );

	// Define shortcut
    public static function load($findme) {
        return parent::load('character', $findme);
    }
 
    // Extends the set function
    // Call setDamage ( 'set' + 'damage' in camel case) before set in in the object
    public function setDamage($damage) {
        $maxDamage = 0;
        switch ($this->race->name) {
            case 'Orc': $maxDamage = 10;
            case 'Human': $maxDamage = 8;
        }
        if($damage > $maxDamage) $damage = $maxDamage;
        return $damage;
    }
     
}
 
$myCharacter = Charcacter::load(1); // Shortcut
$myCharacter->id_race = 2;
$myCharacter->refreshRelation(); // Now you have access to $myCharacter->race as an Obj
$myCharacter->damage = 12; // Call before the setDamage function
echo $myCharacter->race->name.' => '.$myCharacter->damage; // Human => 8
```

MySQL Abstraction Layer
--------

```php
// Db::inst() return an access to your database connection
$db = Db::inst();
$db->insert('mo_character', array('name' => 'Conan', 'damage' => 12));
$db->update('mo_character', array('damage' => 1), array('name="Conan"') );
// 4 types of select shortcut :
$db->getValue('damage', 'mo_character', array('name = "Conan"'));
// return : 12
$db->getRow('*', 'mo_character', array('id_character = 1'));
// return : Array ( [id_character] => 1 [name] => MrManchot [damage] => 10 )
$db->getArray('name, damage', 'mo_character', 'damage > 5', NULL, 'damage DESC', '0,2');
// return : Array ( [0] => Array ( [name] => Goldorak [damage] => 19 ) [1] => Array (...
$db->getValueArray('id_character', 'mo_character', 'damage > 5');
// return : Array ( [0] => 1 [1] => 2 [2] => 4 )
 
$db->delete('mo_character', array('name="Conan"') );
$nbCharacters = $db->count('mo_character', array('damage > 10'));
// Try a wrong query...
$db->update('mo_character', array('damage' => 1), array('invalid_field="Toto"') );
$error = $db->error();
```
