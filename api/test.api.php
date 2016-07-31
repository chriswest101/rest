<?php

	/*
	 * @author Chris West
	 * @created 12/11/2015
	*/
	
	use \Rest\RestServer\RestException;
	
	$classIntro = "This API is used for testing only!
	
This is an example to show the different methods that we can send and functions we can use.
Please feel free to experiment with this test API to try different calls.";
	
	class test {		
		/**
		 * @title Get Help Information
		 * @description Prints help for each class
		 * @url GET /help
		 * @output Array of methods
		 */
		public function printHelp($id = null) {
			if ($this->server->mode == "production")
				$this->server->handleError(500);
			// curl -H "Content-Type: application/json" -X GET http://localrest/test/help
			echo print_r($this->server->map, true);	
		}
		
		
		/**
		 * @title Load a request from another API
		 * @description This example shows how you can call data from external API.
<strong>Retrieve data from a GET request</strong>
<code>$externalGet = $this->server->getExternalRequest("pricing/test"); // returns object</code>
<strong>Send data to a PUT request</strong>
<code>$externalPut = $this->server->getExternalRequest('pricing/getitemprice', array('Code' => 'INDY007', 'AccType' => 'retail')); // returns object</code>
<strong>Send data to a POST request</strong>
<code>$externalPut = $this->server->getExternalRequest('pricing/getitemprice', array('Code' => 'INDY007', 'AccType' => 'retail'), 'POST'); // returns object</code>
		 * @url GET /externalapitest
		 * @output
		 */
		public function externalAPItest() {
			// return "Test";
			$thisFunction = new stdClass;
			$thisFunction->functionData = "Test ".date("d/m/Y H:i a");
			// Load an external GET
			$externalGet = $this->server->getExternalRequest("pricing/test");
			// $externalPut = $this->server->getExternalRequest('pricing/getitemprice', array('Code' => 'INDY007', 'AccType' => 'retail'));
			$externalPost = $this->server->getExternalRequest('pricing/getitemprice', array('Code' => 'INDY007', 'AccType' => 'retail'), 'POST');
			$this->server->sendData(array(
				'thisFunction' => $thisFunction,
				'externalGet'  => $externalGet,
				'externalPost' => $externalPost
			));
		}
		
		
		/**
		 * @title Get Item
		 * @description Gets an item by id or current user
		 * @url GET /item/$id
		 * @cacheable
		 * @cachetime 6 hours
		 * @output {"id": "5", "name": "ITEM NAME"}
		 */
		public function getItem($id = null) {
			// curl -H "Content-Type: application/json" -X GET http://localrest/test/item/5
			return array("id" => $id, "name" => $this->server->uppercase('Gets an item')); // serializes object into JSON
		}
		
		/**
		 * @title Set Average Category Weights
		 * @description This is used for updating category weights
		 * @url GET /updatecategoryaverageweights
		 * @output array of new average weights
		**/
		public function updateCategoryAverageWeights(){
			$categories = $this->server->queryAllByKey("cat_no", "SELECT cat_no FROM `cmpo_site`.`categories`");
			//$categories = $this->server->query("SELECT cat_no FROM `cmpo_site`.`categories`");
			
			$categories = array_keys($categories);
			$partsByCategory = array();
			$categoryWeights = array();
			
			foreach($categories as $category){
				$partsByCategory[$category] = array_keys($this->server->queryAllByKey("newcode", "SELECT `newcode` FROM `cmpo_site`.`temp_parts` WHERE cat_no = '$category'"));
			}
			
			foreach($partsByCategory as $cat_no => $category){
				$categoryWeights[$cat_no] = 0;
				foreach($category as $key => $part){
					if(!isset($part) || !$part){
						unset($partsByCategory[$cat_no][$key]);
						continue;
					}
					$weight = $this->server->queryColumn("SELECT `kg` FROM `cmpo_site`.`bin_bins` WHERE p_code = '$part' AND kg != 0 AND kg != ''");
					unset($partsByCategory[$cat_no][$key]);
					if($weight){
						$partsByCategory[$cat_no][$part] = '';
						$partsByCategory[$cat_no][$part] = $weight;
						$categoryWeights[$cat_no] += $weight;
					}
				}
			}
			
			//echo "<pre>CategoryWeights: ".print_r($categoryWeights, true)."</pre>";
			//echo "<pre>parts: ".print_r($partsByCategory, true)."</pre>";
			
			foreach($categoryWeights as $key => $categoryWeight){
				unset($categoryWeights[$key]);
				$categoryWeights[$key]['totalWeight'] = $categoryWeight;
				$categoryWeights[$key]['totalParts'] = count($partsByCategory[$key]);
				$averageWeight = sprintf("%01.2f", round($categoryWeight / count($partsByCategory[$key]), 2));
				$categoryWeights[$key]['averageWeight'] = $averageWeight;
				
				$this->server->query("UPDATE `cmpo_site`.`categories` SET `average_weight` = '$averageWeight' WHERE cat_no = '$key'");
				
			}
			
			//echo "<pre>".print_r($categoryWeights, true)."</pre>";
			
		}
		
		
		
		/**
		 * @title Update Item
		 * @description This is used for updating items
		 * @url PUT /item/$id
		 * @output ("id": "100", "action": "Update item", "data": $data)
		 */
		public function updateItem($id = null, $data) {
			// curl -H "Content-Type: application/json" -X PUT -d '{"username":"xyz","password":"xyz"}' http://localrest/test/item/5
			return array("id" => $id, "action" => "Update item", 'data' => $data);
		}
		
		
		/**
		 * @title Post new item
		 * @description This is used for updating items
		 * @url POST /item
		 * @output {"id":$id, "action": "Insert item", "data": $data}
		 */
		public function insertItem($data) {
			// curl -H "Content-Type: application/json" -X POST -d '{"username":"xyz","password":"xyz"}' http://localrest/test/item
			return array("action" => "Insert item", 'data' => $data);
		}
		
		
		/**
		* @title Load Lists
		* @description This is a sample of using multiple variables through a singular function in the API
		* @url GET /list/$id
		* @url GET /list/$id/$date
		* @url GET /list/$id/$date/$interval/
		* @url GET /list/$id/$date/$interval/$months
		*/
		public function getItemsWithFilter($id = null, $date = null, $interval = 30, $months = 12) {
			echo "id = $id, date = $date, interval = $interval, months = $months";
		}
		
		
		public function throwError() {
			throw new RestException(401, "Empty password not allowed");
		}
	}

/* ?> These are removed to stop header issues */