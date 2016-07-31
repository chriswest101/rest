<?php

	/*
	 * @author Chris West
	 * @created 12/11/2015
	*/

	namespace Rest\RestServer;
	require(__DIR__ . '/RestFormat.php');
	require(__DIR__ . '/RestException.php');
	require(__DIR__ . "/../../classes/database.class.php");
	require(__DIR__ . "/../../classes/common.class.php");
		
	use \Exception;
	use \ReflectionClass;
	use \ReflectionObject;
    use \ReflectionMethod;
	
	class RestServer extends common {
		public $cacheable			= 0,
			   $cached 				= 0,
			   $cacheTime 			= "6 hours",
			   $data,
			   $debugArray 			= array(),
			   $debugger 			= false,
			   $debugLines			= array(),
			   $debugSpace 			= "",
			   $externalRequests 	= array(),
			   $forcedMethod,
			   $forcedPut,
			   $forcedUrl,
		       $format,
			   $function,
			   $header 				= array("200", "OK"),
			   $map 				= array(),
			   $method,
			   $mode 				= "production", // production|development
			   $model,
			   $output,
			   $params 				= array(),
			   $paramStore			= "",
			   $path,
			   $root 				= "/",
			   $mapCreated,
			   $url;
		
		protected $cachedMap;
		protected $errorClasses = array();
		protected $hasError = 0;
	
		/** I AM THE CONSTRUCTOR! **/
		public function  __construct() {
			parent::__construct();
			
			$this->checkAccess();
			$this->checkMode();
			
			$dir = dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']));
			if ($dir == '.') {
				$dir = '/';
			} else {
				// add a slash at the beginning and end
				if (substr($dir, -1) != '/') $dir .= '/';
				if (substr($dir, 0, 1) != '/') $dir = '/' . $dir;
			}
			$this->root = $dir;
		}
		
		
		/** I AM THE DESTRUCTOR! **/
		public function  __destruct() {
			$this->logRequest();
		}
		
		
		public function addClass($class, $basePath = '') {
			$this->loadMap(__DIR__ . "/../../cache/{$class}map.cache");
			
			// Check for a helper file before we load the class
			if (file_exists(__DIR__ . "/../../helpers/$class.helper.php"))
				require_once __DIR__ . "/../../helpers/$class.helper.php";
			// Load the cache
			if (file_exists(__DIR__ . "/../../api/$class.api.php"))
				require_once __DIR__ . "/../../api/$class.api.php";
			else {
				// Class does not exist
				$this->handleError(404);
			}
				
			if (!$this->cachedMap) {
				if (is_string($class) && !class_exists($class)){
					throw new Exception('Invalid method or class');
				} elseif (!is_string($class) && !is_object($class)) {
					throw new Exception('Invalid method or class; must be a classname or object');
				}
				
				if (substr($basePath, 0, 1) == '/') {
					$basePath = substr($basePath, 1);
				}
				if ($basePath && substr($basePath, -1) != '/') {
					$basePath .= '/';
				}
				$this->generateMap($class, $basePath);
			}
		}
		
		
		public function addErrorClass($class) {
			$this->errorClasses[] = $class;
		}
		
		
		protected function checkAccess() {
			if (!in_array($_SERVER['REMOTE_ADDR'], $this->allowedIP)) {
				// die($_SERVER['REMOTE_ADDR']);
				$this->handleError('401');
			}
		}
		
		
		protected function checkMode() {
			if ($this->localScript()) {
				$this->mode = "development";
				error_reporting(E_ALL);
				ini_set("display_errors", "On");
			} elseif (isset($_GET['debug']) && $_GET['debug']) {
				error_reporting(E_ALL);
				ini_set("display_errors", "On");
			}
		}
		
		
		public function debug($text) {
			$this->debugLines[] = $this->timer().": $text";
		}
		
		
		protected function findUrl() {
			$urls = $this->map[$this->method];
			if (!$urls) return null;
			foreach ($urls as $url => $call) {
				$args = $call[2];
				if (!strstr($url, '$')) {
					if ($url == $this->url) {
						if (isset($args['data'])) {
							$params = array_fill(0, $args['data'] + 1, null);
							$params[$args['data']] = $this->data;   //@todo data is not a property of this class
							$call[2] = $params;
						} else {
							$call[2] = array();
						}
						return $call;
					}
				} else {
					$regex = preg_replace('/\\\\\$([\w\d]+)\.\.\./', '(?P<$1>.+)', str_replace('\.\.\.', '...', preg_quote($url)));
					$regex = preg_replace('/\\\\\$([\w\d]+)/', '(?P<$1>[^\/]+)', $regex);
					if (preg_match(":^$regex$:", urldecode($this->url), $matches)) {
						$params = array();
						$paramMap = array();
						if (isset($args['data'])) {
							$params[$args['data']] = $this->data;
						}
						
						foreach ($matches as $arg => $match) {
							if (is_numeric($arg)) continue;
							$paramMap[$arg] = $match;
							
							if (isset($args[$arg])) {
								$params[$args[$arg]] = $match;
							}
						}
						ksort($params);
						// make sure we have all the params we need
						end($params);
						$max = key($params);
						for ($i = 0; $i < $max; $i++) {
							if (!array_key_exists($i, $params)) {
								$params[$i] = null;
							}
						}
						ksort($params);
						$call[2] = $params;
						$call[3] = $paramMap;
						// print_r($call); exit;
						return $call;
					}
				}
			}
		}
		
		
		protected function generateMap($class, $basePath) {
			if (is_object($class)) {
				$reflection = new ReflectionObject($class);
			} elseif (class_exists($class)) {
				$reflection = new ReflectionClass($class);
			}
			
			$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);    //@todo $reflection might not be instantiated
			
			foreach ($methods as $method) {
				$doc = $method->getDocComment();
				$cacheable = strpos($doc, '@cacheable') == true;
				$cachetime = 0;
				if ($cacheable && strpos($doc, '@cachetime'))
					$cachetime = preg_replace("/(.*)cachetime ([0-9]+) (((?i)hour|minute(?-i))(s*))(.*)/s", "$2 $3", $doc);
				if (preg_match_all('/@url[ \t]+(GET|POST|PUT|DELETE|HEAD|OPTIONS)[ \t]+\/?(\S*)/s', $doc, $matches, PREG_SET_ORDER)) {
					$params = $method->getParameters();
					foreach ($matches as $match) {
						$httpMethod = $match[1];
						$url = $basePath . $match[2];
						if ($url && $url[strlen($url) - 1] == '/') {
							$url = substr($url, 0, -1);
						}
						$call = array($class, $method->getName());
						$args = array();
						foreach ($params as $param) {
							$args[$param->getName()] = $param->getPosition();
						}
						$call[] = $args;
						$call[] = null;
						$call[] = $cacheable;
						$call[] = $cachetime;
						
						$this->map[$httpMethod][$url] = $call;
					}
				}
			}
			
			// echo print_r($this->map, true);
		}
		
		
		public function getData() {
			$data = $this->forcedPut ? (object) $this->forcedPut : json_decode(file_get_contents('php://input'));
			return $data;
		}
		
		
		public function getExternalRequest($url, $data = false, $method = false) {
			$previousRequest = array(
				$this->cached,
				$this->cacheable,
				$this->cacheTime,
				$this->cachedMap,
				$this->forcedMethod,
				$this->forcedPut,
				$this->forcedUrl,
				$this->function,
				$this->hasError,
				$this->map,
				$this->method,
				$this->model,
				$this->paramStore,
				$this->path,
				$this->url
			);
			// set for this request
			$this->forcedUrl = $url;
			$this->forcedMethod = $method ? $method : ($data ? "PUT" : "GET");
			$this->forcedPut = $data;
			//Debug
			$this->debugSpace .= "    ";
			$this->debugArray[] = "{$this->debugSpace}START -- $url, bfu = {$previousRequest[2]}";
			// Make the request
			$external = json_decode($this->handle());
			// Log the request to save it later once we have them all
			$this->externalRequests[] = array('url' => $url, 'method' => $method, 'data' => $data, 'response' => $external);
			// Debug
			$this->debugArray[] = "{$this->debugSpace}    Data length = ".strlen(serialize($external));
			$this->debugArray[] = "{$this->debugSpace}END    -- $url, bfu = {$previousRequest[2]}";
			$this->debugSpace = substr($this->debugSpace, 0, -4);
			// Restore data from the previous request
			list($this->cached, $this->cacheable, $this->cacheTime, $this->cachedMap, $this->forcedMethod, $this->forcedPut, $this->forcedUrl, $this->function, $this->hasError, $this->map, $this->method, $this->model, $this->paramStore, $this->path, $this->url) = $previousRequest;
			// Return
			return $external;
		}
		
		
		public function getFormat() {
			$format = RestFormat::PLAIN;
			$accept_mod = @preg_replace('/\s+/i', '', $_SERVER['HTTP_ACCEPT']); // ensures that exploding the HTTP_ACCEPT string does not get confused by whitespaces
			$accept = explode(',', $accept_mod);
			$override = '';
	
			if (isset($_REQUEST['format']) || isset($_SERVER['HTTP_FORMAT'])) {
				// Give GET/POST precedence over HTTP request headers
				$override = isset($_SERVER['HTTP_FORMAT']) ? $_SERVER['HTTP_FORMAT'] : '';
				$override = isset($_REQUEST['format']) ? $_REQUEST['format'] : $override;
				$override = trim($override);
			}
			
			// Check for trailing dot-format syntax like /controller/action.format -> action.json
			if(preg_match('/\.(\w+)$/i', $_SERVER['REQUEST_URI'], $matches)) {
				$override = $matches[1];
			}
	
			// Give GET parameters precedence before all other options to alter the format
			$override = isset($_GET['format']) ? $_GET['format'] : $override;
			if (isset(RestFormat::$formats[$override])) {
				$format = RestFormat::$formats[$override];
			} elseif (in_array(RestFormat::JSON, $accept)) {
				$format = RestFormat::JSON;
			}
			return $format;
		}
		
		
		public function getMethod() {
			if ($this->forcedMethod)
				return $this->forcedMethod;
			$method = $_SERVER['REQUEST_METHOD'];
			$override = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : (isset($_GET['method']) ? $_GET['method'] : '');
			if ($method == 'POST' && strtoupper($override) == 'PUT') {
				$method = 'PUT';
			} elseif ($method == 'POST' && strtoupper($override) == 'DELETE') {
				$method = 'DELETE';
			}
			return $method;
		}
		
		
		public function getPath() {
			$path = preg_replace('/\?.*$/', '', $this->forcedUrl ? $this->forcedUrl : $_SERVER['REQUEST_URI']);
			// remove root from path
			if ($this->root) {
				$path = preg_replace('/^' . preg_quote($this->root, '/') . '/', '', $path);
			}
			// remove trailing format definition, like /controller/action.json -> /controller/action
			if (substr($path, 0, 1) == "/") {
				$path = substr($path, 1);
			}
			$path = preg_replace('/\.(\w+)$/i', '', $path);
			$this->path = $path;
			$path = explode("/", $path, 2);
			$this->model = $path[0];
			if (!isset($path[1])) {
				$this->handleError('405');
			}
			return $path[1];
		}
		
		
		public function handle() {
			$this->url = $this->getPath();
			$this->method = $this->getMethod();
			$this->format = $this->getFormat();
			
			if ($this->model) {
				$this->addClass($this->model);
			}
			
			if ($this->method == 'PUT' || $this->method == 'POST') {
				$this->data = $this->getData();
			}

			list($obj, $method, $params, $this->params, $this->cacheable, $this->cacheTime) = $this->findUrl();
			$this->debugArray[] = "{$this->debugSpace}    Requesting {$this->model}->$method";
			$this->debugArray[] = "{$this->debugSpace}    Caching = $this->cacheable";
			$this->debugArray[] = "{$this->debugSpace}    Cachetime = $this->cacheTime";
			$this->function = $method;
			$this->paramStore = $params;
			
			$cache = $this->loadCache(__DIR__ . "/../../cache/{$this->model}/{$this->url}", $this->forcedUrl);
			if ($cache && $this->forcedUrl) {
				return $this->sendData($cache, true);
			}
			
			if ($obj) {
				if (is_string($obj)) {
					if (class_exists($obj)) {
						$obj = new $obj();
					} else {
						throw new Exception("Class $obj does not exist");
					}
				}
				
				$obj->server = $this;
				try {
					if (method_exists($obj, 'init')) {
						$obj->init();
					}
					
					$result = call_user_func_array(array($obj, $method), $params);
					if ($this->forcedUrl) { // internalrequest
						return $this->sendData($result, true);
					} else if ($result !== null) { // final result
						if ($this->debugger && $this->localScript())
							echo implode("\n", $this->debugArray);
						$this->sendData($result);
					}
				} catch (RestException $e) {
					$this->handleError($e->getCode(), $e->getMessage());
				}			
			
			} else {
				$this->handleError(406);
			}
		}
		
		
		public function handleError($statusCode, $errorMessage = null) {
			$method = "handle$statusCode";
			foreach ($this->errorClasses as $class) {
				if (is_object($class)) {
					$reflection = new ReflectionObject($class);
				} elseif (class_exists($class)) {
					$reflection = new ReflectionClass($class);
				}
	
				if (isset($reflection)) {
					if ($reflection->hasMethod($method)) {
						$obj = is_string($class) ? new $class() : $class;
						$obj->$method();
						return;
					}
				}
			}
			// Creating the message to show
			$message = $errorMessage ? $errorMessage : (isset($this->codes[$statusCode]) ? $this->codes[$statusCode] : "Error");
			$this->setStatus($statusCode, $message);
			$this->sendData(array('error' => array('code' => $statusCode, 'message' => $message)));
			$this->hasError = true;
			exit;
		}
		
		
		public function loadCache($file, $return = false) {
			$file = "$file.cache";
			if ($this->mode == 'production' && file_exists($file) && preg_match("/([0-9]+) (((?i)hour|minute(?-i))(s*))/", $this->cacheTime)) {
				$filemtime = filemtime($file);

				if ($filemtime > date("U", strtotime("-{$this->cacheTime}"))) {
					$this->cached = true;
					if ($return) {
						$this->debugArray[] = "{$this->debugSpace}    LOADING FROM CACHE!";
						return file_get_contents($file);
					} else {
						$this->output = file_get_contents($file);
						echo $this->output;
						exit;
					}
				} else {
					@unlink($file);
				}
			}
			
			$this->cached = false;
		}
		
		
		protected function loadMap($file) {
			if ($this->mode == "production" && file_exists($file)) {
				$filemtime = filemtime($file);
				if ($filemtime > date("U", strtotime("-6 hours"))) {
					$map = unserialize(file_get_contents($file));
					if (isset($map) && is_array($map)) {
						if (count($map)) {
							// set the cache
							$this->map = $map;
							$this->cachedMap = true;
						} else {
							// mapcache exists but it looks like it could be empty
							@unlink($file);
						}
					}
				} else {
					// cache file is too old
					@unlink($file);
				}
			}
		}
		
		
		public function logRequest() {
			// Let's log them all, we can sort them out at a later date
			$this->insert("`web2_db`.`rest_logs`", array(
				'api' 			=> $this->model,
				'build_time'	=> $this->timer(),
				'cached' 		=> $this->cached,
				'cacheable'		=> $this->cacheable ? "1" : "0",
				'cachetime'		=> $this->cacheable ? $this->cacheTime : "",
				'debug'			=> implode("\n", $this->debugLines),
				'external'		=> json_encode($this->externalRequests),
				'function'		=> $this->function,
				'has_error'		=> $this->hasError,
				'header'		=> $this->header[0],
				'header_msg'	=> $this->header[1],
				'method' 		=> $this->method,
				'output'		=> $this->output,
				'params'		=> json_encode($this->paramStore),
				'queries'		=> $this->queryCount,
				'request_ip'	=> $_SERVER['REMOTE_ADDR'],
				'today'			=> date("Ymd"),
				'url' 			=> $this->path
			));
		}
		
		
		public function refreshCache() {
			$this->map = array();
			$this->cached = false;
		}
		
		
		protected function saveCache() {
			$dir = __DIR__ . "/../../cache";
			$url = "{$this->model}/{$this->url}";
			$fullpath = "$dir/$url.cache";
			$parts = explode('/', $url);
			$file = array_pop($parts);
			
			foreach($parts as $part) {
				$dir .= "/$part";
				if(!is_dir($dir)) {
					$result = mkdir($dir);
				}
			}
			$save = file_put_contents($fullpath, $this->output);
			$this->debugArray[] = "{$this->debugSpace}    Saving cache = $save";
		}
		
		
		protected function saveMap() {
			file_put_contents(__DIR__ . "/../../cache/{$this->model}map.cache", serialize($this->map));
		}
		
		
		public function sendData($data, $forced = false) {
			if (!$forced && !$this->debugger) {
				header("Cache-Control: no-cache, must-revalidate");
				header("Expires: 0");
				header('Content-Type: ' . $this->format);
			}
	
			if (is_object($data) && method_exists($data, '__keepOut')) {
				$data = clone $data;
				foreach ($data->__keepOut() as $prop) {
					unset($data->$prop);
				}
			}
			
			// $this->output = json_encode($data);
			$this->output = $this->cached ? $data : json_encode($data);
			
			if (!$this->hasError && $this->mode == "production") {
				if (!$this->cached && $this->cacheable)
					$this->saveCache();
					
				if (!$this->cachedMap) 
					$this->saveMap();
			}
			
			if ($forced)
				return $this->output;
			
			if ($this->debugger && $this->localScript())
				echo "\n\nFINAL OUTPUT:\n";
				
			echo $this->output;
		}
		
		public function setStatus($code, $errorMessage = null) {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			$errorMessage = isset($this->codes[strval($code)]) ? $this->codes[strval($code)] : ($errorMessage ? $errorMessage : "Error");
			$header = $code.' '.$errorMessage;
			if (!preg_match("/^4(1([8-9])|2([0-6]))$/", $code))
				header("$protocol $header");
			$this->header = array($code, $errorMessage);
		}
		
		
		public function unauthorized($ask = false) {
			if ($ask) {
				header("WWW-Authenticate: Basic realm=\"$this->realm\"");
			}
			$this->header = "401 {$this->codes[401]}";
			throw new RestException(401, "You are not authorized to access this resource.");
		
		}
		
		
		private $allowedIP = array(
			'185.21.132.44',	// The British Chocolate Box IP
		);
	
	
		private $codes = array(
			'100' => 'Continue',
			'200' => 'OK',
			'201' => 'Created',
			'202' => 'Accepted',
			'203' => 'Non-Authoritative Information',
			'204' => 'No Content',
			'205' => 'Reset Content',
			'206' => 'Partial Content',
			'300' => 'Multiple Choices',
			'301' => 'Moved Permanently',
			'302' => 'Found',
			'303' => 'See Other',
			'304' => 'Not Modified',
			'305' => 'Use Proxy',
			'307' => 'Temporary Redirect',
			'400' => 'Bad Request',
			'401' => 'Unauthorized',
			'402' => 'Payment Required',
			'403' => 'Forbidden',
			'404' => 'Not Found',
			'405' => 'Method Not Allowed',
			'406' => 'Not Acceptable',
			'409' => 'Conflict',
			'410' => 'Gone',
			'411' => 'Length Required',
			'412' => 'Precondition Failed',
			'413' => 'Request Entity Too Large',
			'414' => 'Request-URI Too Long',
			'415' => 'Unsupported Media Type',
			'416' => 'Requested Range Not Satisfiable',
			'417' => 'Expectation Failed',
			'418' => 'Client Error',
			'419' => 'Client Error',
			'420' => 'Client Error',
			'421' => 'Client Error',
			'422' => 'Client Error',
			'423' => 'Client Error',
			'424' => 'Client Error',
			'425' => 'Client Error',
			'426' => 'Client Error',
			'500' => 'Internal Server Error',
			'501' => 'Not Implemented',
			'503' => 'Service Unavailable'
		);
	}

/* ?> These are removed to stop header issues */