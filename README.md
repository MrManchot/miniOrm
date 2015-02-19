miniOrm
=======
Just a mini ORM, for using Object Model and MySQL Abstraction Layer as simply as possible

Simple, Light-weight & Extensible
--------
+ 1 Table = 1 Object Model. Create, read, update and delete in your database without using any SQL queries. 
+ Only one file to include and you're ready. Don't need tables configuration, miniOrm automatically determine your database model. 
+ Extend your object, create easily relation between it, override how to set values, automatically validate fields & type and size and more... 

How to install ?
--------
Install with composer `composer require mrmanchot/miniorm`
Then just define your database connection :

```php
define('_MO_DB_NAME_', 'dbname');
define('_MO_DB_LOGIN_', 'dblogin');
define('_MO_DB_PASSWORD_', 'dbpassword');
define('_MO_DB_SERVER_', 'localhost');
```

How it works ?
--------

###Create, read, update and delete
```php
### Create
$firstCharacter = new miniOrm\Obj('character');
$firstCharacter->name = 'Conan';
$firstCharacter->damage = 10;
$firstCharacter->insert();

# Can use hydrate() to set multiple fields
$secondCharacter = new miniOrm\Obj('character');
$secondCharacter->hydrate(array('name' => 'Hulk', 'damage' => 12));
$secondCharacter->insert();

# Can do all in one method
$thirdCharacter = miniOrm\Obj::create(
	'character',
	array('name' => 'Spiderman', 'damage' => 1)
);

### Update
$conan->damage = 13;
$conan->update(); // Can use too save() : update() if already exist, else insert()

### Delete
$firstCharacter->delete();

```

###Load Object
```php
### Load
$conan = miniOrm\Obj::load(1, 'character');

### Multiple Load
$strongCharacters = miniOrm\Obj::find(array("damage > 1"), 'character');
foreach($strongCharacters as $strongCharacter) {
	echo $strongCharacter->name.' : '.$strongCharacter->damage.'<br/>';
}
```

### Extend your object
```php
class Character extends miniOrm\Obj {

	# Define your database name
	protected static $tableStatic = 'character';

    # Can define relation table, load the Race object for the id_race field
    public $relations = array(
        array('obj' => 'race', 'field' => 'id_race')
    );

    # Extends 'set' functions : call set_damage() ( 'set_' + 'damage') 
    public function set_damage($damage) {
        switch ($this->race->name) {
            case 'Orc':
            	$maxDamage = 10;
				break;
            case 'Human':
            	$maxDamage = 8;
				break;
			default: $maxDamage = 20;
        }
        if($damage > $maxDamage)
        	$damage = $maxDamage;
        return $damage;
    }

}

$hulk = Character::load(2);
$hulk->id_race = 2;

# Give you have access to $secondCharacter->race as an Obj
$hulk->getRelations();

# Call before the Character set_damage() method
$hulk->damage = 12;
$hulk->update();

# Will return : 'Hulk (Human) : 8'
echo $hulk->name.' ('.$hulk->race->name.') : '.$hulk->damage.'<br/>';
```

### MySQL Abstraction Layer
```php
### Get an access to your database connection
$db = miniOrm\Db::inst();

### Insert, update, delete and count
$db->insert('character', array('name' => 'Wolverine', 'damage' => 1));
$db->update('character', array('damage' => 12), array('name="Wolverine"') );
$db->delete('character', array('name="Wolverine"') );
$db->count('character', array('damage > 10'));

### Select shortcuts :

# Return a field
$damage = $db->getValue('damage', 'character', array('name = "Wolverine"')); 

# Return a row
$character = $db->getRow('*', 'character', array('id_character = 1'));  

# Return an array
$characters = $db->getArray(
	'name, damage',
	'character',
	'damage > 5',
	NULL,
	'damage DESC'
); 

# Return an array of the value (in example : id_character)
$charactersIds = $db->getValueArray(
	'id_character',
	'character',
	'damage > 5'
);
```

Optional configuration
--------
```php
# Freeze option permit to add to cache your database configuration.
# Once activated, you can't access to new table dynamically : just active it in production.
define('_MO_FREEZE_', true); // Default : false

# Display MySQL errors
define('_MO_DEBUG_', false); // Default : true

# Define the cache dir
define('_MO_CACHE_DIR_', '/var/www/mycachedir/'); // Default : miniOrm/cache
```