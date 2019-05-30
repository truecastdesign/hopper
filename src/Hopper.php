<?php
namespace Truecast;

use PDO;

/**
 * Database layer script for fast database interactions
 *
 * @package True Framework 6
 * @author Daniel Baldwin
 * @version 1.3.0
 * @copyright 2019 Truecast Design Studio
 */
class Hopper
{
	private $obj;
	private $result;
	private $err;
	private $sitePrefs;
	private $debug = 0; # set to 1 for debugging.
	private $errorNum = 0;
	private $errorMsg = null;
	private $query = '';
	private $userErrorReporter = false;
	private $queryList = array();
	private $driver = '';
	
	
	/**
	 * construct
	 *
	 * @param array|object $config  array( 'driver' => 'mysql', 'host' => 'localhost', 'username' => '', 'password' => '', 'database' => '', 'emulate_prepares'=>false, 'error_mode'=>PDO::ERRMODE_EXCEPTION, 'persistent'=> false, 'compress'=> false, 'charset' => 'utf8', 'port'=>3306, 'buffer'=>true );
	 * For SQLITE: use a config like ['driver'=>'sqlite', 'database'=>'data/main.db']
	 * @author Daniel Baldwin
	 */
	public function __construct($config)
	{		
		$options = [];

		if(is_array($config))
			$config = (object) $config;

		$this->driver = $config->driver;

		switch($config->driver)
		{
			case 'mysql':
				if(isset($config->host)) $dsn = 'mysql:host='.$config->host;
				else $dsn = 'mysql:host=localhost';
				if(isset($config->database)) $dsn .= ';dbname='.$config->database;
				if(isset($config->charset)) $dsn .= ';charset='.$config->charset;
				if(isset($config->port)) $dsn .= ';port='.$config->port;
				
				if(isset($config->emulate_prepares)) $options [PDO::ATTR_EMULATE_PREPARES] = $config->emulate_prepares;
				if(isset($config->error_mode)) $options [PDO::ATTR_ERRMODE] = $config->error_mode;
				if(isset($config->persistent)) $options [PDO::ATTR_PERSISTENT] = $config->persistent;
				if(isset($config->compress)) $options [PDO::MYSQL_ATTR_COMPRESS] = $config->compress;
				if(isset($config->buffer)) $options [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = $config->buffer;
				
				try {
					$this->obj = new PDO($dsn, $config->username, $config->password, $options);
				}
				catch(PDOException $ex) { 
					$this->setError($ex->getMessage()." ".$errorMsg);
					return false;
				}
			break;
			
			case 'sqlite':
				$dsn = 'sqlite:'.$config->database;
				
				try {
					$this->obj = new PDO($dsn);
				}
				catch(PDOException $ex) { 
					$this->setError($ex->getMessage()." ".$errorMsg);
					return false;
				}
			break;
		}
	}
		
	

	public function query($query, $errorMsg='')
	{ 
		if(!is_object($this->obj)) 
		{
			$this->setError("Database object not created. ".$query.'; '.$errorMsg);
			return false;
		}

		try { 
			$this->result = $this->obj->query($query); 
			return true;
		}
		catch(PDOException $ex) { 
			$this->setError($ex->getMessage().' '.$errorMsg); 
			return false;
		}
	}
	
	public function execute($query, $values, $errorMsg='')
	{
		if(!is_object($this->obj)) 
		{
			$this->setError("Database object not created. ".$query.'; '.$errorMsg);
			return false;
		}

		if(!is_string($query))
		{
			$this->setError("Query was not provided. ".$errorMsg.' | Query: '.$query);
			return false;
		}

		try { 
			$dbRes = $this->obj->prepare($query); 

			if(is_object($dbRes))
			{
				$dbRes->execute($values);
			}	
			else
			{
				$this->setError("Table not created. ".$errorMsg.' | Query: '.$query);
				return false;
			}

			return true;
		}
		catch(PDOException $ex) { 
			$this->setError($ex->getMessage()." ".$errorMsg.' | Query: '.$query);
			return false;
		}
	}
	
	/**
	 * delete record
	 *
	 * @param string $table - name of table
	 * @param string|array $id - value of id or list of values to match with the fields
	 * @param string|array $field - name of field or list of fields
	 * @param string $errorMsg - custom error message
	 * @return int - affected rows
	 * @author Daniel Baldwin
	 */
	public function delete($table, $id, $field='id', $errorMsg='')
	{
		if(is_array($id) AND is_array($field))
		{
			$query = "DELETE FROM ".$table." WHERE";
			
			foreach($field as $key)
				$query .= ' '.$key.'=?';
			
			$values = $id;
		}
		elseif(is_array($id))
		{
			$query = "DELETE FROM ".$table." WHERE ".$field." IN(".implode(',',$id).")";
			$values = $id;
		}
		else
		{
			$query = "DELETE FROM ".$table." WHERE ".$field."=?";
			$values[] = $id;
		}
		
		try {
			$dbRes = $this->obj->prepare($query);

			if(is_object($dbRes))
			{
				$dbRes->execute($values);
				return $dbRes->rowCount();
			} 
			else
			{
				$this->setError("Table not created. ".$errorMsg);
				return false;
			}
		}
		catch(PDOException $ex) {
			$this->setError($ex->getMessage()." ".$errorMsg);
			return false;
		}
		
	}
	
	/**
	 * set function for inserting or updating rows
	 *
	 * @param string $table - table name
	 * @param array $set - for prepared statements, array(value, value) format or array(':field1'=>value) format
	 * @param string $idfield - change if record id field is not 'id'
	 * @return int - record id
	 * @author Daniel Baldwin
	 *
	 * @example set('table',array('field1'=>'value1', 'field2'=>'value2')) # insert values
	 * @example set('table',array('id'=>6, 'field1'=>'value1', 'field2'=>'value2')) # update row where id=6
	 */
	public function set($table, $set=null, $idfield='id')
	{
		$update = false;
		
		$fieldCount = count($set);
		if($fieldCount < 1)
		{
			$this->setError('Key/Value array empty! '.$errorMsg);
			return false;
		}
		
		if(isset($set[$idfield]))
		{
			$update = true;
			$idValue = $set[$idfield];
			unset($set[$idfield]);
			$set[$idfield] = $idValue;
		}
		
		$values = array_values($set);
		$fields = array_keys($set);
		
		if($update)
		{
			array_pop($fields);
			$query = "UPDATE ".$table.' SET '.implode("=?, ",$fields).'=? WHERE '.$idfield.'=?';
		} 
		else 
		{
			$query = "INSERT INTO ".$table.' ('.implode(',',$fields).') VALUES(?';
			for ($i=1; $i < $fieldCount; $i++)
			{ 
				$query .= ',?';
			}
			$query .= ') ';
		}
		
		try {
			if(!is_object($this->obj))
			{
				$this->setError("Database object not created.".' | Query: '.$query);
				return false;
			}
			else
			{
				$dbRes = $this->obj->prepare($query);

				if(is_object($dbRes))
				{
					$dbRes->execute($values);

					if(isset($set[$idfield])) 
						return $set[$idfield]; # update
					else
						return $this->obj->lastInsertId(); # insert
				}
				else
					$this->setError("Database prepare statement didn't return an object.".' | Query: '.$query);
			}
		}
		catch(PDOException $ex) {
			$this->setError($ex->getMessage().' | Query: '.$query);
			return false;
		}
	}
	
	/**
	 * for select statements
	 *
	 * @param string $query - mysql query
	 * @param array $get - array of fields and values
	 * @param string $type - return an array | arrays | 2dim (deprecated: use arrays instead) | object | class | bound | number | value. 2dim will always return a two-dimensional array
	 * @param string $arrayIndex - alternate field should be the array index for multidimensional arrays 
	 * @param string $errorMsg - custom error message
	 * @return array | object; default is an object
	 * @author Daniel Baldwin
	 *
	 * @example get('select * from table where id IN(?,?)', array(1,3))
	 * @example get('select * from table where id=?, year=?', array(1,1998))
	 */
	public function get($query, $get=null, $type='array', $arrayIndex=null, $errorMsg='')
	{
		$this->query = $query;
		
		if(!is_object($this->obj)) 
		{
			$this->setError("Database object in DBPDO not available");
			return false;
		}	
		
		switch($type)
		{
			case 'array': $pdoType = 2; break;
			case 'arrays': $pdoType = 2; break;
			case '2dim': $pdoType = 2; break;
			case 'object': $pdoType = PDO::FETCH_OBJ; break;
			case 'objects': $pdoType = 8; break;
			case 'class': $pdoType = 8; break;
			case 'bound': $pdoType = 6; break;
			case 'number': $pdoType = 3; break;
			case 'value': $pdoType = 2; break;
			case 'keypair': $pdoType = PDO::FETCH_KEY_PAIR; break;
			default: $pdoType = 5; break;
		}
		
		try {
			if(is_array($get))
			{
				$dbRes = $this->obj->prepare($query);
			}	
			else
			{
				$dbRes = $this->obj->query($query);
			}

			if(is_object($dbRes))
			{
				if(is_array($get)) 
				{
					$dbRes->execute($get);
				}
				else
				{
					$dbRes->execute();
				}
			}	
			else
			{
				$this->setError("Table not created.");
				return false;
			}
			
			if($dbRes->rowCount() > 1 OR $type == '2dim' OR $type == 'arrays' OR $type == 'objects')
			{
				$result = $dbRes->fetchAll($pdoType);
			}
			else
			{
				$result = $dbRes->fetch($pdoType);
			}
			if(is_array($result))
			{
				if($arrayIndex == null)
				{
					if($type=='value' OR $type=='number')
						return current($result); # changed from $array[0]
					elseif($type=='array')
					{
						# if it is a multi-dim array make it 1 dim
						if(isset($result[0]) and @is_array($result[0]))
						{
							foreach($result as $values)
							{
								$tmpArray[] = current($values);
							}
							return $tmpArray;
						}
						else # removes arrays with a string key on them which causes problems with values lists on get queries
						{
							return $result;
						}
						
						
					}
					else
					{
						return $result;
					}
						
				}	
				else
				{
					foreach($result as $item)
					{
						if(is_array($item))
						{
							$output[$item[$arrayIndex]] = $item;
						}
						elseif(is_object($item))
						{
							$output[$item->{$arrayIndex}] = $item;
						}
					}
					return $output;
				}
			}
			elseif(is_object($result))
			{

				return $result;
			}
		}
		catch(PDOException $ex) {
			$this->setError($ex->getMessage());
			return false;
		}
	}
	
	/**
	 * set for Scalable Key-Value table
	 *
	 * @param string $table 
	 * @param string $set 
	 * @param string $settings array('record_id_field'=>'record_id', 'record_id'=>1, 'key_field'=>'field_name', 'value_field'=>'value')
	 * @return void
	 * @author Daniel Baldwin
	 */
	public function setScalableKeyValue($table, $set, $settings)
	{
		# build list of dependent fields
		$dependantFields = $settings;
		
		# remove the non dependent fields from the array
		unset($dependantFields['key_field'],$dependantFields['value_field'],$dependantFields['record_id_field']);
		
		# make sure all the important settings are there.
		if(is_null($settings['record_id_field']) OR is_null($settings['key_field']) OR is_null($settings['value_field']) OR is_null($table)) return false;
		
		# build where clause
		$dependantQuery = '';
		
		if(count($dependantFields) > 0)
		{
			foreach($dependantFields as $field=>$value)
			{
				$dependantQuery .= $field."=? AND ";
				$values[] = $value;
			}
		}	
		
		$dependantQuery .= $settings['key_field']." IN(?".str_repeat(",?", count($set)-1).")";	 
		
		# start by deleting all the rows for that record
		$deleteQuery = "Delete from ".$table." where ".$settings['record_id_field']."=?";
		
		$deleteValues[] = $settings['record_id'];
			
		
		try { 
			$this->obj->prepare($deleteQuery)->execute($deleteValues); 
		}
		catch(PDOException $ex) {
			$this->setError($ex->getMessage());
		}
		
		/*INSERT INTO table (artist, album, track, length) 
		VALUES 
		("$artist", "$album", "$track1", "$length1"), 
		("$artist", "$album", "$track2", "$length2"),
		("$artist", "$album", "$track3", "$length3"), 
		("$artist", "$album", "$track4", "$length4"),
		("$artist", "$album", "$track5", "$length5");*/
		
		$allFields = array_keys($dependantFields);
		
		$allFields[] = $settings['key_field'];
		$allFields[] = $settings['value_field'];
		
		$insertQuery = "Insert into ".$table." (".implode(",",$allFields).") values ";
		
		foreach($set as $dataField=>$dataValue)
		{
			# build query string
			$valuesStr .= '(?'.str_repeat(",?", count($allFields)-1).'),';
			
			# build values array
			foreach($dependantFields as $key=>$value)
			{
				$allValues[] = $value;
			}
			
			$allValues[] = $dataField;
			$allValues[] = $dataValue;
		}
		
		# remove last comma
		$valuesStr = rtrim($valuesStr, ",");
		
		$insertQuery .= $valuesStr;
				
		try { $this->obj->prepare($insertQuery)->execute($allValues); }
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->setError();}
		
	}
	
	
	# $table (string): the db table you are setting
	# $keys (array): references keys like category or products id and type of field
	# $values (array or string): value for key
	# $keyFields (array): field titles that store the keys
	# $valFields (array or string): field titles that store value contents
	function setPropArray($table, $keys, $values, $keyFields, $valFields)
	{
		# build set pairs for database insert
		if(is_array($keyFields))
		{
			if(count($keys) != count($keyFields)) { exception('key fields and key values count does not match.'); exit;}
			for($i=0; $i<count($keyFields); $i++)
			{
				# for inserting key field values
				if($set) $set .= ',';
				$set .= ' '.$keyFields[$i].'=\''.$keys[$i].'\'';
				
				# for checking if row exists in db
				if($check) $check .= ' AND ';
				$check .= ' '.$keyFields[$i].'=\''.$keys[$i].'\'';
			}
		}
		else $set = ' '.$keyFields.'=\''.$keys.'\'';
		
		# check if more than one value field
		if(is_array($valFields))
		{
			if(count($values) != count($valFields)) { exception('value fields and value values count does not match.'); exit;}
			for($i=0; $i<count($valFields); $i++)
			{
				if($setVal) $setVal .= ',';
				$setVal .= ' '.$valFields[$i].'=\''.$values[$i].'\'';
			}
		}
		else $setVal = ' '.$valFields.'=\''.$values.'\'';
		
		# check table to see if row exists or not
		$keyStr = (is_array($keyFields))? implode(',',$keyFields):'*';
		$result = mysql_query("SELECT $keyStr FROM $table WHERE $check", $this->link);
		$query = (@mysql_num_rows($result))? "UPDATE $table SET $setVal WHERE $check":"INSERT INTO $table SET $set, $setVal";
		$this->query($query);
	}
	
	/**
	 * get row count for query
	 *
	 * @param string $query 
	 * @param array $get 
	 * @param string $errorMsg 
	 * @return void
	 * @author Daniel Baldwin
	 */
	public function rowCount($query, $get=null, $errorMsg='')
	{
		try {
			$dbRes = $this->obj->prepare($query);
			if(is_object($dbRes))
			{
				$dbRes->execute($get);
				return $dbRes->rowCount();
			}	
			else
				$this->setError('Table not created!');
		}
		catch(PDOException $ex) {
			$this->setError($ex->getMessage());
		}
	}

	/**
	 * Get the last inserted id
	 * @return int the id
	 */
	public function lastInsertId()
	{
		return (int) $this->obj->lastInsertId();
	}
	
	/**
	 * Generate an error message and save it
	 *
	 * @return void
	 * @author Daniel Baldwin - danb@truecastdesign.com
	 **/
	public function setError($errorMsg = null)
	{
		$trace = debug_backtrace();

		if(!empty($errorMsg))
			$errorMsg = $errorMsg.' : ';
		
		$this->errorMsg .= $errorMsg.'Query '.htmlspecialchars((is_array($this->query)? implode(",", $this->query):$this->query)).' in '.$trace[2]['class'].'::'.$trace[2]['function'].' on line '.$trace[1]['line'].' in the file '.$trace[1]['file']."<br>";
	}

	/**
	 * get any errors that were generated
	 *
	 * @return string
	 * @author Daniel Baldwin
	 */
	public function getErrors()
	{
		return $this->errorMsg;
	}

	/**
	 * Return last query run
	 * 
	 * @return string - query string
	 * @author Daniel Baldwin <danielbaldwin@gmail.com>
	 */
	public function getLastQuery()
	{
		return $this->query;
	}
	
	/**
	 * get next row or rows
	 *
	 * @param string $args array('table'=>'tablename','id'=>1, 'fields'=>'field1,field2', 'idField'=>'id', 'number_of_rows'=>1)
	 * @return array
	 * @author Daniel Baldwin
	 */
	function next_row($args)
	{
		if(empty($args['fields'])) $args['fields'] = '*';
		if(empty($args['number_of_rows'])) $args['number_of_rows'] = 1;
		
		$query = 'SELECT '.$args['fields'].' FROM '.$args['table'].' where '.$args['idField'].' > ? LIMIT 0,'.$args['number_of_rows'];
		
		try {
			$dbres = $this->obj->prepare($query);
			$dbres->execute(array($args['id']));
			if($args['number_of_rows'] == 1)
				return $dbres->fetch(PDO::FETCH_ASSOC);
			else
				return $dbres->fetchAll(PDO::FETCH_ASSOC);
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
	}
	
	/**
	 * get range of rows with given id in the middle
	 *
	 * @param string $args array('table'=>'tablename','id'=>1, 'fields'=>'field1,field2', 'idField'=>'id', 'number_of_rows'=>1)
	 * @return array
	 * @author Daniel Baldwin
	 */
	public function getRange($args)
	{
		if(empty($args['fields'])) $args['fields'] = '*';
		if(empty($args['number_of_rows'])) $args['number_of_rows'] = 1;
		if(empty($args['idField'])) $args['idField'] = 'id';
		
		# previous records and center record
		$query1 = 'SELECT '.$args['fields'].' FROM '.$args['table'].' WHERE '.$args['idField'].' <= ? ORDER BY '.$args['idField'].' DESC LIMIT '.($args['number_of_rows']+1);
		
		$query2 = 'SELECT '.$args['fields'].' FROM '.$args['table'].' WHERE '.$args['idField'].' > ? ORDER BY '.$args['idField'].' ASC LIMIT '.$args['number_of_rows'];
		
		try {
			$dbres = $this->obj->prepare($query1);
			$dbres->execute(array($args['id']));
			$result = $dbres->fetchAll(PDO::FETCH_ASSOC);
			
			$result = array_reverse($result);
			
			$dbres = $this->obj->prepare($query2);
			$dbres->execute(array($args['id']));
			$result2 = $dbres->fetchAll(PDO::FETCH_ASSOC);
			
			return array_merge($result, $result2);
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
	}
	

	 
	/**
	 * Build arrays for tree
	 *
	 * @param array $arg array('table', 'idField', 'parentField', 'titleField', 'sortField', 'fullPathField', 'where', 'values', 'otherFields') 
	 * where: "field=? and field2=?"
	 * values: array for values for where string
	 * @return array multidimensional
	 * @author Daniel Baldwin
	 */
	public function tree($arg)
	{
		$where = (empty($arg['where'])? '':'WHERE '.$arg['where']);
		
		$fullPathFieldStr = (empty($arg['fullPathField'])? '':','.$arg['fullPathField']);
		
		$otherFieldsStr = (empty($arg['otherFields'])? '':','.$arg['otherFields']);
		
		$query = 'SELECT '.$arg['idField'].','.$arg['parentField'].','.$arg['titleField'].$fullPathFieldStr.$otherFieldsStr.'  FROM '.$arg['table'].' '.$where.' ORDER BY '.$arg['parentField'].','.$arg['sortField'].','.$arg['titleField'];
		
		$menuData = array('items'=>array(), 'parents'=>array());
		
		try {
			if(is_array($arg['values']))
			{
				$dbres = $this->obj->prepare($query);
				$dbres->execute($arg['values']);
			}	
			else $dbres = $this->obj->query($query);
			
			$array = $dbres->fetchAll(PDO::FETCH_ASSOC); 

			foreach($array as $menuItem)
			{
				$menuData['items'][$menuItem['id']] = $menuItem;
				$menuData['parents'][$menuItem['parent']][] = $menuItem['id'];
			}
			return $menuData;
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
	}

	/**
	 * Truncate table
	 * 
	 * @param  string $table table name
	 * @return null
	 */
	public function empty(string $table)
	{
		if($this->driver == 'mysql')
			$this->execute("TRUNCATE TABLE `".$table."`", []);
		elseif($this->driver == 'sqlite')
		{
			$this->execute("DELETE FROM `".$table."`", []);
			$this->execute("VACUUM", []);
		}	
	}
	
}