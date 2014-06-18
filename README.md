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
$myCharacter = new miniOrm\Obj('character');
$myCharacter->name = 'Conan';
$myCharacter->damage = 10;
// Or use hydrate() to set multiple fiels
$myCharacter->hydrate(array('name' => 'Conan', 'damage' => 10));
$myCharacter->insert();
// Shortcut :
$myCharacter = miniOrm\Obj::create('character', array('name' => 'Conan', 'damage' => 10));
 
$firstCharacter = miniOrm\Obj::load(1, 'character');
$firstCharacter->damage = 12;
$firstCharacter->update(); // Can use too save() : update() if already exist, else insert()
$firstCharacter->delete();

// Get an array of you object :
foreach(miniOrm\Obj::find(array("damage > 2"), 'character') as $strongCharacter) {
	echo $strongCharacter->name.'<br/>';
}
```

Extend your object
--------

```php
class Character extends miniOrm\Obj {

	// Define
	protected static $tableStatic = 'character';
     
    // Can define relation table, load the Race object for the id_race field
    public $relations = array(
        array('obj' => 'race', 'field' => 'id_race')
    );
 
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

$testCharacter = new Character();
$testCharacter->name = "Wesh";
$testCharacter->save();
 
$myCharacter = Character::load(1);
$myCharacter->id_race = 2;
$myCharacter->refreshRelation(); // Now you have access to $myCharacter->race as an Obj
$myCharacter->damage = 12; // Call before the setDamage function
echo $myCharacter->race->name.' => '.$myCharacter->damage; // Human => 8
```

MySQL Abstraction Layer
--------

```php
// miniOrm\Db::inst() return an access to your database connection
$db = miniOrm\Db::inst();
$db->insert('character', array('name' => 'Conan', 'damage' => 1));
$db->update('character', array('damage' => 12), array('name="Conan"') );
// 4 types of select shortcut :
$db->getValue('damage', 'character', array('name = "Conan"'));
// return : 12
$db->getRow('*', 'character', array('id_character = 1'));
// return : Array ( [id_character] => 1 [name] => MrManchot [damage] => 10 )
$db->getArray('name, damage', 'character', 'damage > 5', NULL, 'damage DESC', '0,2');
// return : Array ( [0] => Array ( [name] => Goldorak [damage] => 19 ) [1] => Array (...
$db->getValueArray('id_character', 'character', 'damage > 5');
// return : Array ( [0] => 1 [1] => 2 [2] => 4 )
 
$db->delete('character', array('name="Conan"') );
$db->count('character', array('damage > 10'));
// Try a wrong query...
$db->update('character', array('damage' => 1), array('invalid_field="Toto"') );
```
