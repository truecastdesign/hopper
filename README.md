Hopper - Database Abstraction Layer (DBAL) for PHP
=======================================

Version: 1.2.3

This library provides a simple and powerful way to SET and GET records from a PDO database. It has many powerful database interaction methods that have been developed over the past 10 years.

Install
-------

To install with composer:

```sh
composer require truecastdesign/hopper
```

Requires PHP 5.5 or newer.

Usage
-----

Here's a basic usage example:


```php
# composer autoloader
require '/path/to/vendor/autoload.php';

# create a new instance of the Hopper class
$DB = new \Truecast\Hopper(['type'=>'mysql', 'username'=>'', 'password'=>'', 'database'=>'']);

# insert a record
$DB->set('table_name', ['first_name'=>'John', 'last_name'=>'Doe', 'phone'=>'541-555-5555', 'status'=>'live']);  # id:1
$DB->set('table_name', ['first_name'=>'Tim', 'last_name'=>'Baldwin', 'phone'=>'541-555-5551', 'status'=>'live']); # id:2

# update a record
$DB->set('table_name', ['id'=>1, 'phone'=>'541-555-5556']);

# get single record
$recordObj = $DB->get('select * from table_name where id=?', [1], 'object');
$recordArray = $DB->get('select * from table_name where id=?', [1], 'array');

# output:
stdClass Object ('id'=>1, first_name'=>'John', 'last_name'=>'Doe', 'phone'=>'541-555-5555', 'status'=>'live')
Array ('id'=>1, first_name'=>'John', 'last_name'=>'Doe', 'phone'=>'541-555-5555', 'status'=>'live')

# get a single value
$value = $DB->get('select first_name from table_name where id=?', [1], 'value');

# output
string 'John'

# get several records
$recordList = $DB->get('select id,first_name,last_name from table_name where status=?', ["live"], '2dim');

# output
Array (	[0]=>Array ('id'=>1, first_name'=>'John', 'last_name'=>'Doe')
		[1]=>Array ('id'=>2, 'first_name'=>'Tim', 'last_name'=>'Baldwin'))
```

Create config file in the format and the truecastdesign/config class to turn it into an object to pass to the construct. This allows you to store configuration in an ini file.

```sh
; MySQL database config

config_title = 'mysql'
type = 'mysql'
hostname = 'localhost'
username = 'root'
password  = 'password'
database  = 'dbname'
port = 3306
persistent = true
emulate_prepares = false
compress = true
charset = 'utf8'
buffer = true
```

Instantiate using a config file.

```php
$DB = new \Truecast\Hopper($Config->mysql);
```

Delete a record

```php
$DB->delete('table_name', 1); # deletes record with id=1

$DB->delete('table_name', 'value', 'field_name'); # deletes records with field_name='value'
```

There is a method for setting a record into a Scalable Key-Value table.

Scalable Key-Value table is a table that have field names like id, record_id, key, value. Each key-value pair in a records is stored in its own table row. This way you can dynamically add and remove fields you want to store.

```php
$settings = ['record_id_field'=>'record_id', 'record_id'=>1, 'key_field'=>'field_name', 'value_field'=>'value'];

$DB->setScalableKeyValue('table_name', ['first_name'=>'John', 'last_name'=>'Doe', 'phone'=>'541-555-5555', 'status'=>'live'], $settings)
```

More method documentation coming as soon as I have time to write it up.

