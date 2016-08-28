<?php
	/**
	 * @author Chris West
	 * @created 26/08/2016
	 */

	namespace Rest\RestServer;
	use PDO;
	
	class database extends PDO {

		var $query 		= false,
			$queryCount = 0;


		public function __construct() {
			define("DB_HOST", "localhost");
			define("DB_USER", "acwest10");
			define("DB_PASS", "Kplant10.");
			
			parent::__construct("mysql:host=". DB_HOST.";", DB_USER, DB_PASS, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
			$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
			$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
		}
		
		/**
		 * The execute function is built to pass through to the PDO::execute but is required to handle the extended resourses, this also has the added feature of being able to process multiple results through a prepared statement
		 * @param array of querys
		 * @param array prepared array of data
		 */
		public function execute($data, $array = false) {
			if ($array) {
				foreach ($array as $a) {
					$data->execute($a);
					$this->queryCount++;
					
				}
			} else {
				parent::execute($data);
				$this->queryCount++;
				
			}
		}
		
		
		/**
		 * This function can be used to Insert database records using prepared statements.
		 * @param string table name of which to insert into
		 * @param array prepared array of data to insert
		 * @return int affected rows
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
		 * This function will process the PDO::Query
		 * @param string the query
		 * @return string query
		 */
		public function query($query) {
			$this->query = parent::query($query);
			
			$this->queryCount++;
			return $this->query;
		}
		
		
		/**
		 * This function can be used to either update or insert database rows. The $where variable is false by default so any request without the where would execute the insert() function whereas requests with the $where variable will execute the update() function.
		 * @param string table name
		 * @param array of data to update or insert
		 * @param string where clause of query if updating
		 * @return int affected row count
		 */
		public function perform($table, $data, $where = false) {
			if ($where)
				return $this->update($table, $data, $where);
		
			return $this->insert($table, $data);
		}

		/**
		 * The processQuery() function is a simple way to quickly process a query with a prepared statement and get a return. This function can be used for row counts, columns, results, or updates.
		 * @param string query to process
		 * @param array of data to execute
		 * @param string of which mode to use can be fetch|fetchColumn|fetchAll|rowCount
		 * @return database object
		 */
		public function processQuery($query, $array, $mode = false) {
			if ($query) {
				if ($array) {
					$this->query = $this->prepare($query);
					$this->query->execute($array);
				} else {
					$this->query = $this->query($query);
				}
				$this->queryCount++;
			}
			if ($mode)
				return $this->query->$mode();

			return $this->query;
		}
		
		
		/**
		 * This function can be used to update database records using prepared statements.
		 * @param string table of which to update
		 * @param array prepared array of data
		 * @param array of prepared where clauses
		 * @return int number of affected rows
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
		 * The queryColumn function is a simple way to quickly process a query with a prepared statement and get a queryColumn return.
		 * @param string of which query to fetch column on
		 * @param array of data for prepared statement
		 * @return string of result from database
		 */
		public function queryColumn($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetchColumn");
		}


		/**
		 * The queryRowCount() function is a simple way to quickly process a query with a prepared statement and get a rowCount return.
		 * @param string query of which to fetch row count on
		 * @param array of data for prepared statement
		 * @return array of result from database
		 */
		public function queryRowCount($query = false, $array = false) {
			return $this->processQuery($query, $array, "rowCount");
		}

		
		/**
		 * The queryRow function is a simple way to quickly process a query with a prepared statement and get a fetch return.
		 * @param string query of which to fetch row on
		 * @param array of data for prepared statement
		 */
		public function queryRow($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetch");
		}

		
		/**
		 * The queryAll() function is a simple way to quickly process a query and get all results returned.
		 * @param string query of which to fetch all data on
		 * @param array of data for prepared statement
		 * @return array of results from database
		 */
		public function queryAll($query = false, $array = false) {
			return $this->processQuery($query, $array, "fetchAll");
		}
	}

/* ?> These are removed to stop header issues */