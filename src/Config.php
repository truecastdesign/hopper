<?php
namespace Truecast;

/**
 * This is a global configuration storage class so you can easily access configuration strings
 *
 * @package TrueAdmin 6
 * @author Daniel Baldwin
 * @version 1.1.2
 */

class Config
{
	var $items = array();
	
	private $booleans = array('1' => true, 'on' => true, 'true' => true, 'yes' => true, '0' => false, 'off' => false, 'false' => false, 'no' => false);
	
	protected $linebreak = "\n";
	
	protected $quoteStrings = true; # quote Strings - if true, writes ini files with doublequoted strings
	
	protected $delim = '"'; # string delimiter
	
	public function __construct($files = null)
	{
		$this->load($files);
	}

	/**
	 * Use this method to load into memory the config settings
	 *
	 * @param string $files - use the config path starting from web root with no starting slash
	 * example: system/config/site.ini
	 * @return void
	 * @author Daniel Baldwin - danielbaldwin@gmail.com
	 **/
	public function load($files)
	{
		# multiple files
		if(strpos($files, ','))
			$filesList = explode(',', $files);
		else # single file
			$filesList[] = $files;
			
		foreach($filesList as $file)
		{
			$file = trim($file);
			
			# convert file into array
			if(file_exists($file))
				$config = parse_ini_file($file, true);

			# if it has sections, remove the config_title array that gets created
			$configTitle = $config['config_title'];
			unset($config['config_title']);
			
			# add the array using the config title as a key to the items array
			if(is_array($config))
				$this->items[$configTitle] = (object) $config;
		}
	}

	/**
	 * return value or values from config file without loading into config items
	 *
	 * @param string $file, file path from web root. example: modules/modname/config.ini
	 * @param string $key (optional) if provided only the value of given key will be returned
	 * @return object|string, will return object of no key is provided and a string if a key is given.
	 * @author Daniel Baldwin - danielbaldwin@gmail.com
	 **/
	public function get(string $file, string $key=null)
	{
		$config = parse_ini_file($file, true);
		if($key != null)
			return $config[$key];
		else
			return (object) $config;
	}
	
	/**
	 * Use the config_title value and the config value to access the value
	 *
	 * @param string $key the key you want to return the value for.
	 * @return string
	 * @author Daniel Baldwin - danielbaldwin@gmail.com
	 **/
	public function __get($key)
	{	
		if(array_key_exists($key, $this->items))
			return $this->items[$key];
	}

	/**
	 * Temporally add to the config object in memory
	 * Example: $Config->title->key = 'value';
	 *
	 * @param string $key
	 * @param string $value
	 * @return void
	 * @author Daniel Baldwin - danb@truecastdesign.com
	 **/
	public function __set($key, $value)
	{
        $this->items[$key] = $value;
    }
	
	/**
	 * Write a data object to a ini file
	 *
	 * @param $filename, path and filename of ini file
	 * @param $data, array of objects for configs with sections and just an object for no sections for ini file
	 * @param $append, true if you want to append to end of file
	 * @return void
	 * @author Daniel Baldwin - danielbaldwin@gmail.com
	 **/
	public function write(string $filename, $data, bool $append)
	{
		$content = '';
		$sections = '';
		$globals  = '';
		$fileContents  = '';

		# no sections
		if(is_object($data))
		{
			$values = (array) $data;

			foreach($values as $key=>$value)
			{
				$content .= "\n".$key."=".$this->normalizeValue($value);
			}
		}

		# has sections
		elseif(is_array($data))
		{
			foreach($data as $section=>$values)
			{
				$content .= "\n[" . $section . "]";

				foreach($values as $key=>$value)
				{
					$content .= "\n".$key."=".$this->normalizeValue($value);
				}
			}
		}
		
		if($append)
		{
			$fileContents = file_get_contents($filename)."\n";
		}
		
		file_put_contents($filename, $fileContents.$content);
	}
	
	/**
	 * normalize a Value by determining the Type
	 *
	 * @param string $value value
	 * @return string
	 */
	protected function normalizeValue($value)
	{
		if (is_bool($value))
		{
			$value = $this->toBool($value);
			return $value;
		}
		elseif (is_numeric($value))
		{
			return $value;
		}
		if ($this->quoteStrings)
		{
			$value = $this->delim . $value . $this->delim;
		}
		return $value;
	}
	
	/**
	 * converts string to a representable Config Bool Format
	 *
	 * @param string $value value
	 * @return string
	 */
	protected function toBool($value)
	{
		if ($value === true)
		{
			return 'yes';
		}
		return 'no';
	}
}