<?
namespace Truecast\Hopper;
/**
 * Database layer script for fast database interactions
 *
 * @package TrueAdmin 6
 * @author Daniel Baldwin
 * @version 1.1.0
 * @copyright 2017 Truecast Design Studio
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
	
	
	/**
	 * construct
	 *
	 * @param array|object $config  array( 'type' => 'mysql', 'hostname' => 'localhost', 'username' => '', 'password' => '', 'database' => '', 'emulate_prepares'=>false, 'error_mode'=>PDO::ERRMODE_EXCEPTION, 'persistent'=> false, 'compress'=> false, 'charset' => 'utf8', 'port'=>3306, 'buffer'=>true );
	 * @author Daniel Baldwin
	 */
	public function __construct($config)
	{		
		if(is_array($config))
			$config = (object) $config;

		switch($config->type)
		{
			case 'mysql':
				if(isset($config->hostname)) $dsn = 'mysql:host='.$config->hostname;
				else $dsn = 'mysql:host=localhost';
				if(isset($config->database)) $dsn .= ';dbname='.$config->database;
				if(isset($config->charset)) $dsn .= ';charset='.$config->charset;
				
				if(isset($config->emulate_prepares)) $options [PDO::ATTR_EMULATE_PREPARES] = $config->emulate_prepares;
				if(isset($config->error_mode)) $options [PDO::ATTR_ERRMODE] = $config->error_mode;
				if(isset($config->persistent)) $options [PDO::ATTR_PERSISTENT] = $config->persistent;
				if(isset($config->compress)) $options [PDO::MYSQL_ATTR_COMPRESS] = $config->compress;
				if(isset($config->buffer)) $options [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = $config->buffer;
				
				try {
					$this->obj = new PDO($dsn, $config->username, $config->password, $options);
				}
				catch(PDOException $ex) { 
					$this->errorMsg .= $ex->getMessage(); $this->display_error();
				}
			break;
			
			case 'sqlite':
				$dsn = 'sqlite:'.$config->database;
				
				try {
					$this->obj = new PDO($dsn);
				}
				catch(PDOException $ex) { 
					$this->errorMsg .= $ex->getMessage(); $this->display_error();
				}
			break;
		}
	}
		
	public function display_error($query=null)
	{
		$trace=debug_backtrace();
		$errorMsg = 'Query '.$this->errorMsg.' in '.$trace[2]['class'].'::'.$trace[2]['function'].' on line '.$trace[1]['line'].' in the file '.$trace[1]['file'];
		
		if(DEBUG==true) trigger_error('Database Error: '.$errorMsg.'<br/>Debug Error: '.htmlspecialchars((is_array($query)? implode(",", $query):$query)).'<br/>',512);	
	}

	/**
	 * returns an debug error string
	 *
	 * @return string with errors
	 * @author Daniel Baldwin - danb@truecastdesign.com
	 **/
	public function return_error()
	{
		$trace = debug_backtrace();
		if(!empty($this->errorMsg))
			return 'Database Error: Query '.$this->errorMsg.' in '.$trace[2]['class'].'::'.$trace[2]['function'].' on line '.$trace[1]['line'].' in the file '.$trace[1]['file'];
	}

	public function query($query, $errorMsg='')
	{ 
		$this->errorMsg = $errorMsg;
		
		if(!is_object($this->obj)) $this->display_error("Database object not created. ".$query);
		else
		{
			try { $this->result = $this->obj->query($query); return true;}
			catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query); echo $ex->getMessage(); return false;}
		}
	}
	
	public function execute($query, $values, $errorMsg='')
	{
		$this->errorMsg = $errorMsg;
		try { $dbRes = $this->obj->prepare($query); $dbRes->execute($values); return true;}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query); return false;}
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
		$this->errorMsg = $errorMsg;
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
		
		try {	$dbRes = $this->obj->prepare($query); $dbRes->execute($values); }
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
		return $dbRes->rowCount();
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
			$this->errorMsg = 'Key/Value array empty!';
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
			$query = "INSERT INTO ".$table.'('.implode(',',$fields).') VALUES(?';
			for ($i=1; $i < $fieldCount; $i++)
			{ 
				$query .= ',?';
			}
			$query .= ') ';
		}
		
		try {
			if(!is_object($this->obj)) $this->display_error("Database object not created. ".$query);
			else $this->obj->prepare($query)->execute($values);
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query.' '.implode(', ',$set));}
		
		# return the ID#, whether new insert id, or echoing back the update id
		if(isset($set[$idfield])) return $set[$idfield]; # update
		else
		{
			if(!is_object($this->obj)) $this->display_error("Database object not created. ".$query);
			else return $this->obj->lastInsertId(); # insert
		}
	}
	
	/**
	 * for select statements
	 *
	 * @param string $query - mysql query
	 * @param array $get - array of fields and values
	 * @param string $type - return an array | 2dim | object | class | bound | number | value. 2dim will always return a two-dimensional array
	 * @param string $arrayIndex - alternate field should be the array index for multidimensional arrays 
	 * @param string $errorMsg - custom error message
	 * @return array
	 * @author Daniel Baldwin
	 *
	 * @example get('select * from table where id IN(?,?)', array(1,3))
	 * @example get('select * from table where id=?, year=?', array(1,1998))
	 */
	public function get($query, $get=null, $type='array', $arrayIndex=null, $errorMsg='')
	{
		$this->errorMsg = $errorMsg;
		if(!is_object($this->obj)) echo "Database object in DBPDO not available";
		
		switch($type)
		{
			case 'array': $pdoType = PDO::FETCH_ASSOC; break;
			case '2dim': $pdoType = PDO::FETCH_ASSOC; break;
			case 'object': $pdoType = PDO::FETCH_OBJ; break;
			case 'class': $pdoType = PDO::FETCH_CLASS; break;
			case 'bound': $pdoType = PDO::FETCH_BOUND; break;
			case 'number': $pdoType = PDO::FETCH_NUM; break;
			case 'value': $pdoType = PDO::FETCH_ASSOC; break;
			default: $pdoType = PDO::FETCH_ASSOC; break;
		}
		
		try {
			if(is_array($get))
			{
				$dbres = $this->obj->prepare($query);
				$dbres->execute($get);
			}	
			else $dbres = $this->obj->query($query);
			
			if($dbres->rowCount() > 1 OR $type=='2dim')
				$result = $dbres->fetchAll($pdoType);
			else
				$result = $dbres->fetch($pdoType);
			
			if(is_array($result))
			{
				if($arrayIndex == null)
				{
					if($type=='value' OR $type=='number')
						return current($result); # changed from $array[0]
					elseif($type=='array')
					{
						# if it is a multi-dim array make it 1 dim
						if(is_array($result[0]))
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
						return $result;
				}	
				else
				{
					foreach($result as $item)
					{
						$output[$item[$arrayIndex]] = $item;
					}
					return $output;
				}
			}
			elseif(is_object($result))
			{
				return $result;
			}
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
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
			
		
		try { $this->obj->prepare($deleteQuery)->execute($deleteValues); }
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($deleteQuery.' '.implode(', ',$deleteValues));}
		
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
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($insertQuery.' '.implode(', ',$allValues));}
		
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
	 * @param string $get 
	 * @param string $errorMsg 
	 * @return void
	 * @author Daniel Baldwin
	 */
	public function rowCount($query, $get=null, $errorMsg='')
	{
		try {
			$dbres = $this->obj->prepare($query);
			$dbres->execute($get);
			return $dbres->rowCount();
		}
		catch(PDOException $ex) { $this->errorMsg .= $ex->getMessage(); $this->display_error($query);}
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
	
	function prev_id($id, $field='id')
	{
		for($i=0; $r = mysql_fetch_array($this->result); $i++)
		{
			if($r[$field]==$id) break;
		}
		if(!$i) $prevId = $this->last_id($field='id');
		else
		{
			@mysql_data_seek($this->result, $i-1);
			$r = mysql_fetch_array($this->result);
			$prevId = $r[$field];
		}
		return $prevId;
	}
	
	function next_prev_ids($id, $field='id')
	{
		$lastId = $this->last_id();
		@mysql_data_seek($this->result, 0);
		for($i=0; $r = mysql_fetch_array($this->result); $i++)
		{
			if($r[$field]==$id) break;
		}
		@mysql_data_seek($this->result, 0);
		if(!$i) $prevId = $lastId;
		else
		{
			mysql_data_seek($this->result, $i-1);
			$r = mysql_fetch_array($this->result);
			$prevId = $r[$field];
		}

		if($i==$this->num_rows()-1)
			$nextId = $this->first_id();
		else
		{
			mysql_data_seek($this->result, $i+1);
			$r = mysql_fetch_array($this->result);
			$nextId = $r[$field];
		}
		return array('prev'=>$prevId, 'next'=>$nextId);
	}
	
	function first_id($field='id')
	{
		@mysql_data_seek($this->result, 0);
		$r = mysql_fetch_array($this->result);
		return $r[$field];
	}
	
	function last_id($field='id')
	{
		$total = mysql_num_rows($this->result);
		@mysql_data_seek($this->result, $total-1);
		$r = mysql_fetch_array($this->result);
		return $r[$field];
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
	function tree($arg)
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
	
}