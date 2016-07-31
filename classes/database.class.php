<?php

	namespace Rest\RestServer;
	use PDO;
	
	class database extends PDO {

		var $query 		= false,
			$queryCount = 0;

		/**
		Database class written purely for a PDO Connection
		This database class extends the built-in PDO class, all PDO functions are available from this class.
		Author: Chris West
		Created: 29/04/2015
		**/
		public function __construct() {
			// parent::__construct("mysql:host=". DB_HOST.";", DB_USER, DB_PASS);
			parent::__construct("mysql:host=". DB_HOST.";", DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
			$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
		}

		/**
		 * @title Debug
		 * @description The debug function will check the last request from PDO and die if there was an error
		 * @return PDO Error {errorcode: errordescription}
		 */
		private function debugQuery() {
			$errorCheck = parent::errorInfo();
			if ($errorCheck[1]) {
				// die(print_r(array('PDO ErrorInfo' => $errorCheck), true));
				die("PDO Error {$errorCheck[1]}: {$errorCheck[2]}");
			}
		}
		
		/**
		 * @title Execute
		 * @description The <sampl>execute()</sampl> function is built to pass through to the PDO::execute but is required to handle the extended resourses, this also has the added feature of being able to process multiple results through a prepared statement
		 * @arg:data resource (query)
		 * @arg:array (prepared array)
		 */
		public function execute($data, $array = false) {
			if ($array) {
				foreach ($array as $a) {
					$data->execute($a);
					$this->queryCount++;
					$this->debugQuery();
				}
			} else {
				parent::execute($data);
				$this->queryCount++;
				$this->debugQuery();
			}
		}
		
		
		/**
		 * @title Insert
		 * @description This function can be used to Insert database records using prepared statements.
		 * @arg:table string (table)
		 * @arg:data array (prepared array)
		 */
		public function insert($table, $data) {
    		reset($data);
      		$query = "INSERT INTO $table (";

			while(list($field,) = each($data)) {
        		$query .= "`$field`, ";
      		}

			$query = substr($query, 0, -2).") VALUES (";
      		reset($data);
			while(list($field,$value) = each($data)) {
				if (preg_match("/(NOW\(\))/i", $value)) {
					$query .= "$value, ";
					unset($data[$field]);
				} else {
					$query .= ":$field, ";
				}
      		}

			$query = substr($query, 0, -2).")";
    		
    		return $this->processQuery($query, $data, "rowCount");
		}


		/**
		 * @title Query
		 * @description This function will process the PDO::Query
		 * @arg:query string (query)
		 */
		public function query($query) {
			$this->query = parent::query($query);
			$this->debugQuery();
			$this->queryCount++;
			return $this->query;
		}
		
		
		/**
		 * @title Perform
		 * @description This function can be used to either update or insert database rows.
		   The <strong>$where</strong> variable is false by default so any request without the where would execute the <code>insert()</code> function whereas requests with the <strong>$where</strong> variable will execute the <code>update()</code> function.
		 * @arg:table "mydb.mytable"
		 * @arg:data array('name' => 'Chris West')
		 * @arg:where array('id', '=', 1) [default = false]
		 */
		public function perform($table, $data, $where = false) {
			if ($where)
				return $this->update($table, $data, $where);
		
			return $this->insert($table, $data);
		}

		/**
		 * @title Process Query
		 * @description The <sampl>processQuery()</sampl> function is a simple way to quickly process a query with a prepared statement and get a return. This function can be used for row counts, columns, results, or updates.
		 * @arg:query string (query)
		 * @arg:array array (prepared array)
		 * @arg:mode fetch|fetchColumn|fetchAll|rowCount [default = false]
		 */
		public function processQuery($query, $array, $mode = false) {
			if ($query) {
				if ($array) {
					$this->query = $this->prepare($query);
					$this->debugQuery($this->query);
					$this->query->execute($array);
				} else {
					$this->query = $this->query($query);
					$this->debugQuery($this->query);
				}
				$this->queryCount++;
			}
			if ($mode)
				return $this->query->$mode();
			return $this->query;
		}
		
		
		/**
		 * @title Update
		 * @description This function can be used to update database records using prepared statements.
		 * @arg:table string (table)
		 * @arg:data array (prepared array)
		 * @arg:where array (prepared array)
		 */
		public function update($table, $data, $where) {
    		reset($data);
      		$query = "UPDATE $table SET ";

			while(list($field,$value) = each($data)) {
				if (preg_match("/(NOW\(\))/i", $value)) {
					$query .= "`$field`=$value, ";
					unset($data[$field]);
				} else {
					$query .= "`$field`=:$field, ";	
				}
      		}

			$query = substr($query, 0, -2)." WHERE ";
			foreach ($where as $w) {
				$query.= "{$w[0]} {$w[1]} :lookup{$w[0]} AND ";
				$data["lookup{$w[0]}"] = $w[2];
			}

			$query = substr($query, 0, -5);    		
    		return $this->processQuery($query, $data, "rowCount");
		}


		/**
		 * @title Query Column
		 * @description The <sampl>queryColumn()</sampl> function is a simple way to quickly process a query with a prepared statement and get a <sampl>queryColumn</sampl> return.
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryColumn($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetchColumn");
		}


		/**
		 * @title Query Row Count
		 * @description The <sampl>queryRowCount()</sampl> function is a simple way to quickly process a query with a prepared statement and get a <sampl>rowCount</sampl> return.
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryRowCount($query = false, $array = false) {
			return $this->processQuery($query, $array, "rowCount");
		}

		
		/**
		 * @title Query Row
		 * @description The <sampl>queryRow()</sampl> function is a simple way to quickly process a query with a prepared statement and get a <sampl>fetch</sampl> return.
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryRow($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetch");
		}

		
		/**
		 * @title Query All
		 * @description The <sampl>queryAll()</sampl> function is a simple way to quickly process a query and get all results returned.
This can be pretty slow as you can double-store the database results by using this method, the better way to do it would be using this <a href="#queries">query method</a> as there would not be a variable containing all of the restults.
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryAll($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetchAll");
		}
		
		
		/**
		 * @title Query All By Key
		 * @description The <sampl>queryAllByKey()</sampl> function is a simple way to quickly process a query and get all results returned group by a specified key.
This can be pretty slow as you can double-store the database results by using this method, the better way to do it would be using this <a href="#queries">query method</a> as there would not be a variable containing all of the restults.
		 * @arg:key string (key to group)
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryAllByKey($key = false, $query = false, $array = false){
			$query = $this->query($query, $array);
			$resultsArray = array();
			while($row = $query->fetch()){
				$resultsArray[$row->$key][] = $row;
			}
			return $resultsArray;
		}
		
		
		/**
		 * @title Query All By Unique Key
		 * @description The <sampl>queryAllByUniqueKey()</sampl> function is a simple way to quickly process a query and get all results returned group by a specified key.
This can be pretty slow as you can double-store the database results by using this method, the better way to do it would be using this <a href="#queries">query method</a> as there would not be a variable containing all of the restults.
		 * @arg:key string (key to group)
		 * @arg:query string (query)
		 * @arg:array array (prepared array) [default = false]
		 */
		public function queryAllByUniqueKey($key = false, $query = false, $array = false){
			$query = $this->query($query, $array);
			$resultsArray = array();
			while($row = $query->fetch()){
				$resultsArray[$row->$key] = $row;
			}
			return $resultsArray;
		}
	}

/* ?> These are removed to stop header issues */