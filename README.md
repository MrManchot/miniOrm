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

Demo
--------
Want to see code examples? Everything is in the sample.php file
