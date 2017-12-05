Config - .ini config file manager for PHP
=======================================

This library provides the ability to create, read/load into memory, and write configuration files in the .ini format.

Install
-------

To install with composer:

```sh
composer require truecast/config
```

Requires PHP 5.5 or newer.

Usage
-----

Here's a basic usage example:

Create config file in the format:

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

```php
<?

# composer autoloader
require '/path/to/vendor/autoload.php';

# load in needed config files into Config object
$TAConfig = new \Truecast\Config();
$TAConfig->load('/path/to/config/mysql.ini'); 
# can load multiple config files at once with a comma between. Example: '/path/to/config/mysql.ini, /path/to/config/site.ini'
```

### Accessing Config Values

The "config_title" in the config file is the keyword you use to access that config file. To access one of the other values you would use a sub object key.

```php
echo $TAConfig->mysql->username; # this would output the string "root" using the above config file.
```

To use sections within a config file you use square brackets around the section title.

Example:

```php
config_title="items"

[secion_title]
name="The Name"
version="1.0"
date="2017-02-12"
show=true
sort=2

[secion_title_two]
name="The Name"
version="1.0"
date="2017-02-12"
show=true
sort=2
```

These section values can be accessed by adding an extra object level using the section title when getting the value.

Example:

```php
echo $TAConfig->items->secion_title_two->version;
```

