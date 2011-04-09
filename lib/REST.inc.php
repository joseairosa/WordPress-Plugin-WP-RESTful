<?php

function __autoload($class) {
	$found = false;
	@include_once ($class . '.php');
	// Check to see whether the include declared the class
	if (! class_exists ( $class, false )) {
		// Lets try diferent combinations of classes
		@include_once ( "lib/".$class . '.php');
		if (! class_exists ( $class, false )) {
			// Try to find it on our API plugins
			$wpr_plugins = get_option("wpr_plugins");
			foreach($wpr_plugins as $wpr_plugin_name => $wpr_plugin_folder) {
				@include_once ( WP_PLUGIN_DIR."/".$wpr_plugin_folder."/lib/".$class . '.php');
				if (class_exists ( $class, false )) {
					$found = true;	
				}
			}
		} else
			$found = true;
	} else
		$found = true;
	if(!$found)
		trigger_error ( "Unable to load class: $class", E_USER_WARNING );
}

/**
 * Given an array it will populate that array with default values on $defaults
 * 
 * @since 0.1
 *
 * @param array $array the array to populate
 * @param array $defaults the array with default values
 * @return array an array with default values
 */
function wpr_set_defaults(&$array,$defaults) {
	if(!is_array($array))
		$array = array();
	foreach($defaults as $key => $default_value) {
		if(!array_key_exists($key,$array))
	    	$array[$key] = $default_value;
	}
}

/**
 * Filter the contents
 * 
 * @since 0.1
 *
 * @param (mixed) $content the content to filter
 * @param (array) $filter the filter given by the function wpr_get_filter
 * @return (mixed) The filtered content 
 */
function wpr_filter_content($content,$filter = false) {
	if(is_object($content)) {
		foreach($content as $key => $value) {
        	if(is_object($content->$key) || is_array($content->$key)) {
        		_wpr_filter_content($content->$key,$filter);
        		if(empty($content->$key))
        			unset($content->$key);
        	} else {
        		if($filter && !in_array($key,$filter,true)) {
        			unset($content->$key);
        		}
        	}
    	}
	}
	if(is_array($content)) {
		foreach($content as $key => $value) {
			if(is_object($content[$key]) || is_array($content[$key])) {
        		_wpr_filter_content($content[$key],$filter);
        		if(empty($content[$key]))
        			unset($content[$key]);
        	} else {
        		if($filter && !in_array($key,$filter,true)) {
        			unset($content[$key]);
        		}
        	}
        }
	}
	return $content;
}
function _wpr_filter_content(&$content,$filter) {
	if(is_object($content)) {
		foreach($content as $key => $value) {
        	if(is_object($content->$key) || is_array($content->$key)) {
        		_wpr_filter_content($content->$key,$filter);
        		if(empty($content->$key))
        			unset($content->$key);
        	} else {
        		if($filter && !in_array($key,$filter,true)) {
        			unset($content->$key);
        		}
        	}
    	}
	}
	if(is_array($content)) {
		foreach($content as $key => $value) {
			if(is_object($content[$key]) || is_array($content[$key])) {
        		_wpr_filter_content($content[$key],$filter);
        		if(empty($content[$key]))
        			unset($content[$key]);
        	} else {
        		if($filter && !in_array($key,$filter,true)) {
        			unset($content[$key]);
        		} 
        	}
        }
	}
}

/**
 * Get the filter of a given request
 * 
 * @since 0.1
 * 
 * @param (string) $filter_type the filter you'd like to apply
 * @return (array) With the filter information
 */
function wpr_get_filter($filter_type) {
	$filter = array();
	$filter = explode(",",get_option('wpr_'.$filter_type.'_allowed_requests'));
	if(!is_array($filter))
		return array();
	else
		return $filter;
}

/**
 * Get current page URL
 * 
 * @since 0.1
 * 
 * @return string with paeg name
 */
function curPageURL() {
	$pageURL = 'http';
	if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
		$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

abstract class WPAPIRESTActionsController {
	protected $action;
	protected $action_request;
	protected $action_request_type;
	protected $action_name;
	protected $action_obj;
	protected $datarequest;
	public $store;
	
	protected function __construct($action_obj) {
		$this->datarequest = WPRESTUtils::processRequest ();
		$this->action_name = get_class ( $action_obj );
		$this->action_obj = $action_obj;
		//echo $this->getAction () . '<br/>' . $this->getActionRequest () . '<br/>' . $this->getActionRequestType ();
		switch ($this->datarequest->getMethod ()) {
			// In case we have a GET request
			case 'get' :
				$this->reply ( $this->getResult () );
				break;
			// In case we have a POST request
			case 'post' :
				$this->reply ( $this->getResult () );
				break;
			default :
				break;
		}
	}
	protected function reply($content) {
		global $wpdb;
		
		if (! is_object ( $content ) && ! is_array ( $content ) || count ( $content ) == 0) {
			die ( WPRESTUtils::sendResponse ( 404 ) );
		}
		if ($this->datarequest->getHttpAccept () == 'json') {
			if(get_option("wpr_allowed_return_type_json")) {
				if(@function_exists("json_encode")) {
					WPRESTUtils::sendResponse ( 200, json_encode ( $content ) );
				} else {
					WPRESTUtils::sendResponse ( 200, 'JSON format is not supported by this API Server' );
				}
			} else {
				WPRESTUtils::sendResponse ( 200, 'JSON format is not supported by this API Server' );
			}
		} else if ($this->datarequest->getHttpAccept () == 'xml') {
			
			if(get_option("wpr_allowed_return_type_xml")) {
				// Using the XML_SERIALIZER Pear Package
				if(@class_exists("XML_Serializer")) {
					$options = array ('indent' => '     ', 'addDecl' => false, 'rootName' => 'WPAPI', XML_SERIALIZER_OPTION_RETURN_RESULT => true );
					$serializer = new XML_Serializer ( $options );
					WPRESTUtils::sendResponse ( 200, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$serializer->serialize ( $content ), 'application/xml' );
				} elseif(@class_exists("DOMDocument")) {
					include(WPR_XMLWRAPPER_PATH);
					// Build our XML Wrapper
					$xmlwrapper = new XMLWrapper('1.0', 'utf-8');
					// We want our output nice and tidy
					$xmlwrapper->formatOutput = true;
					$xmlwrapper->tag = wpr_unpluralize($this->getAction ());
					
					// Initialize our root element tag
					$root = $xmlwrapper->createElement(wpr_pluralize($this->getAction ()));
					$root = $xmlwrapper->appendChild($root);
					
					$xmlwrapper->fromMixed($content,$root);
					
					WPRESTUtils::sendResponse ( 200, $xmlwrapper->saveXML(), 'application/xml' );
				} else {
					WPRESTUtils::sendResponse ( 200, 'XML format is not supported by this API Server' );
				}
			} else {
				WPRESTUtils::sendResponse ( 200, 'XML format is not supported by this API Server' );
			}
		}
	}
	protected function getResult() {
		$method = "";
		$parameter = array ();
		try {
			if(!ctype_digit($this->getActionRequest ()) && $this->getActionRequest () != "all") {
				if(is_callable ( array ($this->action_name, strtolower($this->getActionRequest ()) ) )) {
					$method = strtolower($this->getActionRequest ());
					$parameter = $_POST;
				} else {
					//throw new InvalidArgumentException ( 'Method was not found in class ' . $this->action_name . '.' );
					die ( WPRESTUtils::sendResponse ( 404 ) );
				}	
			} else {
				/*print_r(preg_match("/[A-Za-z\_]+/i",$this->action_name,$matches));
				foreach($matches as $match) {
					print_r(preg_match("/[A-Za-z\_]+/i",$this->action,$matches2));
				}
				print_r($matches);*/
				if (is_callable ( array ($this->action_name, 'get' . ucwords($this->action) ) )) {
					$method = 'get' . ucwords($this->action);
					
				} elseif (is_callable ( array ($this->action_name, 'get' . wpr_pluralize ( ucwords($this->action) ) ) )) {
					$method = 'get' . wpr_pluralize ( ucwords($this->action) );
					
				} elseif (is_callable ( array (wpr_pluralize ( $this->action_name ), 'get' . ucwords($this->action) ) )) {
					$method = 'get' . ucwords($this->action);
					
				} elseif (is_callable ( array ($this->action_name . "s", 'get' . wpr_pluralize ( ucwords($this->action) ) ) )) {
					$method = 'get' . wpr_pluralize ( ucwords($this->action) );
					
				} elseif (is_callable ( array ($this->action_name, 'get' . wpr_unpluralize ( ucwords($this->action) ) ) )) {
					$method = 'get' . wpr_unpluralize ( ucwords($this->action) );
					
				} else {
					//throw new InvalidArgumentException ( 'Method was not found in class ' . $this->action_name . '.' );
					die ( WPRESTUtils::sendResponse ( 404 ) );
				}
			}
			//echo $method;
			if ($this->action_request == "all") {
				$parameter = "";
				$method = wpr_pluralize($method);
			} else {
				if (ctype_digit ( $this->action_request )) {
					$parameter = $this->action_request;
				} else {
					// Implement Later!!
				}
			}
			$class = new $this->action_name ( );
			// Add Get and Post variables to our class call
			return call_user_func ( array ($class, $method ), $parameter );
			// Exit with 404
			die ( WPRESTUtils::sendResponse ( 404 ) );
		} catch ( InvalidArgumentException $e ) {
			throw $e;
		}
	}
	protected function setAction($action) {
		$this->action = $action;
	}
	protected function setActionRequest($action_request) {
		$this->action_request = $action_request;
	}
	protected function setActionRequestType($action_request_type) {
		$this->action_request_type = $action_request_type;
	}
	protected function getAction() {
		return $this->action;
	}
	protected function getActionRequest() {
		return $this->action_request;
	}
	protected function getActionRequestType() {
		return $this->action_request_type;
	}
}

abstract class WPAPIRESTController extends WPAPIRESTActionsController {
	protected $action_controller;
	protected $action_model;
	protected function init() {
		//echo $this->getAction().'<br/>'.$this->getActionRequest().'<br/>'.$this->getActionRequestType();
		$action_controller_name = ucwords ( wpr_unpluralize ( $this->getAction () ) ) . "RESTController";
		$action_model_name = ucwords ( wpr_unpluralize ( $this->getAction () ) );
		// Check if this class is allowed to be accessed by the API externaly
		if (preg_match ( WPR_ALLOWED_REGEX, $this->getAction (), $matches )) {
			// Check if the class exists. If it does we'll try to autoload and initilize it.
			if (class_exists ( $action_controller_name )) {				
				$this->action_controller = new $action_controller_name ( );
				
			} elseif (class_exists ( $action_model_name )) {
				// Check if the model exists (Task.ins.php). If it does we'll try to autoload and initilize it.
				$this->action_model = new $action_model_name ( );
				
			} elseif (class_exists ( wpr_pluralize ( $action_model_name ) )) {
				// Check if the model exists (Task.ins.php). If it does we'll try to autoload and initilize it.
				$action_model_name = wpr_pluralize ( $action_model_name );
				$this->action_model = new $action_model_name ( );
				
			} else {
				// Do something cool and standard :)
				die ( WPRESTUtils::sendResponse ( 404 ) );
			}
		} else {
			die ( WPRESTUtils::sendResponse ( 404 ) );
		}
		// Lets send the data to our action controler
		$this->dispatch ();
	}
	protected function dispatch() {
		// Check if we have a controller
		if (is_object ( $this->action_controller )) {
			parent::__construct ( $this->action_controller );
		} elseif (is_object ( $this->action_model )) {
			// Check if we have a model 
			parent::__construct ( $this->action_model );
		} else {
			die ( WPRESTUtils::sendResponse ( 404 ) );
		}
	}
	protected function setAction($action) {
		parent::setAction ( $action );
	}
	protected function setActionRequest($action_request) {
		parent::setActionRequest ( $action_request );
	}
	protected function setActionRequestType($action_request_type) {
		parent::setActionRequestType ( $action_request_type );
	}
	protected function getAction() {
		return parent::getAction ();
	}
	protected function getActionRequest() {
		return parent::getActionRequest ();
	}
	protected function getActionRequestType() {
		return parent::getActionRequestType ();
	}
}

global $do_return;
$do_return = false;

class WPRESTConsumer {
	public $oauth;
	public $store;
	public function __construct($action = null,$args = null) {
		global $wpdb;
		
		// Init the database connection
		$this->store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		switch($action) {
			case 'register':
				return $this->saveServer($args);
				break;
			case 'store-request-token':
				return $this->saveRequestToken($args);
				break;
			case 'store-access-token':
				return $this->saveAccessToken($args);
				break;
			case 'store-authorized-token':
				return $this->saveAuthorized($args);
				break;
			case 'delete':
				return $this->delete($args);
				break;
		}
		return $this->initServer($args);
	}
	
	protected function initServer($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$user_id = $current_user->ID;
		else
			$user_id = null;
		$consumer_id = $this->store->getServerStatic ($user_id,$args);
		return $consumer_id;
	}
	
	protected function saveServer($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		$sql = "SELECT * FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_REGISTRY." WHERE `ocr_id` = ".$args['id'];
		$consumer = $wpdb->get_row ($sql);
		
		wpr_set_defaults ( $args, 
			array (
				'consumer_key' => $consumer->ocr_consumer_key, 
				'consumer_secret' => $consumer->ocr_consumer_secret, 
				'server_uri' => wpr_check_for_trailing_slash($consumer->ocr_server_uri), 
				'request_token_uri' => $consumer->ocr_request_token_uri, 
				'authorize_uri' => $consumer->ocr_authorize_uri, 
				'signature_methods' => $consumer->ocr_signature_methods,
				'id' => $args['id'],
				'access_token_uri' => $consumer->ocr_access_token_uri ) 
		);
		
		// Register the server
		$consumer_key = $this->store->updateServer ( $args, $current_user->ID, true );
	}
	
	protected function saveRequestToken($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$user_id = $current_user->ID;
		else
			$user_id = null;
			
		$this->store->addServerToken($args['consumer_key'],'request',$args['token'],$args['token_secret'],$user_id);
	}
	
	protected function saveAccessToken($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$user_id = $current_user->ID;
		else
			$user_id = null;
			
		$this->store->addServerToken($args['consumer_key'],'access',$args ['token'],$args ['token_secret'],$user_id);
	}
	
	protected function saveAuthorized($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$user_id = $current_user->ID;
		else
			$user_id = null;
		$this->store->addServerToken($args['consumer_key'],'authorized',$args['token'],$args['token_secret'],$user_id);
		return;
	}
	
	protected function delete($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$args['user_id'] = $current_user->ID;
		else
			$args['user_id'] = null;
		
		$sql = "SELECT ocr_consumer_key as consumer_key FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_REGISTRY." WHERE `ocr_id` = ".intval($args['id']);
		$args['consumer_key'] = $wpdb->get_var ($sql);
		
		// Check if the user is admin
		$args['is_admin'] = current_user_can('manage_options');
		
		$this->store->deleteServerRegistry($args);
		return;
	}
}

class WPRESTServer {
	public $oauth;
	public $store;
	public function __construct($action = null,$args = null) {
		global $wpdb;
		
		// Init the database connection
		$this->store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		switch($action) {
			case 'delete':
				return $this->delete($args);
				break;
		}
	}
	
	protected function delete($args) {
		global $wpdb,$current_user;
		wp_get_current_user();
		
		// Initialize and allocate a server slot
		if(isset($current_user->ID))
			$args['user_id'] = $current_user->ID;
		else
			$args['user_id'] = null;
		
		// Check if the user is admin
		$args['is_admin'] = current_user_can('manage_options');
		
		$this->store->deleteConsumerRegistry($args);
		return;
	}
}

class WPREST extends WPAPIRESTController {
	public $oauth;
	public $store;
	public $req;
	
	public function __construct() {
		global $wpr,$do_return,$wpdb;
		// Check if the server is active
		if(get_option('wpr_server_active')) {
			// Initialize OAuth Controller
			$this->oauth = new OAuthController ( );
			if($do_return)
				return;
				
			$this->store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
			$this->req = new OAuthRequestVerifier ( );	
			
			// Start our main action, or in this case, request
			$this->setAction ( $wpr['request'] );
			// Check if for specific calls we need to have an oauth signed
			$basename = explode ( ".", $wpr['request'] );
			switch (count ( $basename )) {
				case 0 :
					$this->setActionRequest ( "all" );
					$this->setActionRequestType ( "xml" );
					break;
				case 1 :
					$this->setActionRequest ( "all" );
					$this->setActionRequestType ( "xml" );
					break;
				case 2 :
					if (wpr_is_pluralized ( current ( $basename ) ) && count(explode ( "/", current ( $basename ) )) <= 1) {
						$this->setAction ( current ( $basename ) );
						$this->setActionRequest ( "all" );
					} else {
						if (strpos ( current ( $basename ), "/" )) {
							$array = explode ( "/", current ( $basename ) );
							$this->setAction ( current ( $array ) );
							$this->setActionRequest ( end ( $array ) );
						} else {
							$this->setAction ( current ( $basename ) );
							$this->setActionRequest ( "all" );
						}
					}
					$this->setActionRequestType ( end ( $basename ) );
					break;
				default :
					die ( WPRESTUtils::sendResponse ( 404 ) );
			}
			//echo $this->getAction ().", ".$this->getActionRequest ().", ".$this->getActionRequestType ()."<br/><br/>";
			//print_r($_SESSION);
			//print_r($_POST);
			//print_r($_GET);
			$this->requires_oauth ();
		} else {
			$this->setAction ( "status" );
			$this->setActionRequest ( "all" );
			$this->setActionRequestType ( "xml" );
		}
		parent::init ( $this->getAction (), $this->getActionRequest (), $this->getActionRequestType () );
	}
	protected function requires_oauth() {
		
		// Check if the call is in the restricted list
		preg_match ( WPR_REQUIRES_OAUTH_REGEX, $this->getAction (), $matches );
		if (count($matches) > 0) {
			if (! $this->oauth->is_signed) {
				// Return Forbidden HTTP error
				die ( WPRESTUtils::sendResponse ( 401 ) );
			} else {
				// Make sure this consumer is active
				if(!$this->req->consumerIsActive ( $this->req->getParam('oauth_consumer_key')))
					die ( WPRESTUtils::sendResponse ( 200, "You account is on standby" ) );
				
				// Check if the consumer already exceeded his API requests limit	
				if(!$this->store->addConsumerRequestCount(array('consumer_key' => $this->req->getParam('oauth_consumer_key')))) {
					die ( WPRESTUtils::sendResponse ( 200, "You've exceeded your API requests limit for the hour." ) );
				}
			}
		}
	}
	protected function setAction($action) {
		parent::setAction ( $action );
	}
	protected function setActionRequest($action_request) {
		parent::setActionRequest ( $action_request );
	}
	protected function setActionRequestType($action_request_type) {
		parent::setActionRequestType ( $action_request_type );
	}
	protected function getAction() {
		return parent::getAction ();
	}
	protected function getActionRequest() {
		return parent::getActionRequest ();
	}
	protected function getActionRequestType() {
		return parent::getActionRequestType ();
	}
}

class WPRESTUtils {
	public static function processRequest() {
		// get our verb
		$request_method = strtolower ( $_SERVER ['REQUEST_METHOD'] );
		$return_obj = new WPRESTRequest ( );
		// we'll store our data here
		$data = array ();
		
		switch ($request_method) {
			// gets are easy...
			case 'get' :
				$data = $_GET;
				break;
			// so are posts
			case 'post' :
				$data = $_POST;
				break;
			// here's the tricky bit...
			case 'put' :
				// basically, we read a string from PHP's special input location,
				// and then parse it out into an array via parse_str... per the PHP docs:
				// Parses str  as if it were the query string passed via a URL and sets
				// variables in the current scope.
				parse_str ( file_get_contents ( 'php://input' ), $put_vars );
				$data = $put_vars;
				break;
		}
		
		// store the method
		$return_obj->setMethod ( $request_method );
		
		// set the raw data, so we can access it if needed (there may be
		// other pieces to your requests)
		$return_obj->setRequestVars ( $data );
		
		if (isset ( $data ['data'] )) {
			// translate the JSON to an Object for use however you want
			$return_obj->setData ( json_decode ( $data ['data'] ) );
		}
		return $return_obj;
	}
	
	public static function sendResponse($status = 200, $body = '', $content_type = 'text/html') {
		$status_header = 'HTTP/1.1 ' . $status . ' ' . self::getStatusCodeMessage ( $status );
		// set the status
		header ( $status_header );
		// set the content type
		header ( 'Content-Type: ' . $content_type );
		// pages with body are easy
		if ($body != '') {
			// send the body
			echo $body;
			exit ();
		} else {
			// we need to create the body if none is passed
			// create some body messages
			$message = '';
			
			// this is purely optional, but makes the pages a little nicer to read
			// for your users.  Since you won't likely send a lot of different status codes,
			// this also shouldn't be too ponderous to maintain
			switch ($status) {
				case 401 :
					$message = 'You must be authorized to view this page.';
					break;
				case 404 :
					$message = 'The requested URL ' . $_SERVER ['REQUEST_URI'] . ' was not found.';
					break;
				case 500 :
					$message = 'The server encountered an error processing your request.';
					break;
				case 501 :
					$message = 'The requested method is not implemented.';
					break;
			}
			
			// servers don't always have a signature turned on (this is an apache directive "ServerSignature On")
			$signature = ($_SERVER ['SERVER_SIGNATURE'] == '') ? $_SERVER ['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER ['SERVER_NAME'] . ' Port ' . $_SERVER ['SERVER_PORT'] : $_SERVER ['SERVER_SIGNATURE'];
			
			// this should be templatized in a real-world solution
			$body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
						<html>
							<head>
								<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
								<title>' . $status . ' ' . self::getStatusCodeMessage ( $status ) . '</title>
							</head>
							<body>
								<h1>' . self::getStatusCodeMessage ( $status ) . '</h1>
								<p>' . $message . '</p>
								<hr />
								<address>' . $signature . '</address>
							</body>
						</html>';
			echo $body;
			exit ();
		}
	}
	
	public static function getStatusCodeMessage($status) {
		// these could be stored in a .ini file and loaded
		// via parse_ini_file()... however, this will suffice
		// for an example
		$codes = Array (100 => 'Continue', 101 => 'Switching Protocols', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 306 => '(Unused)', 307 => 'Temporary Redirect', 400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Requested Range Not Satisfiable', 417 => 'Expectation Failed', 500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported' );
		
		return (isset ( $codes [$status] )) ? $codes [$status] : '';
	}
}

class WPRESTRequest {
	private $request_vars;
	private $data;
	private $http_accept;
	private $method;
	
	public function __construct() {
		$this->request_vars = array ();
		$this->data = '';
		$page_name = explode ( ".", substr ( parse_url ( curPageURL (), PHP_URL_PATH ), 1 ) );
		$this->http_accept = ((count ( $page_name ) == 2) ? (($page_name [1] == "json") ? 'json' : 'xml') : (strpos ( $_SERVER ['HTTP_ACCEPT'], 'json' ) ? 'json' : 'xml'));
		$this->method = 'get';
	}
	
	public function setData($data) {
		$this->data = $data;
	}
	
	public function setMethod($method) {
		$this->method = $method;
	}
	
	public function setRequestVars($request_vars) {
		$this->request_vars = $request_vars;
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function getMethod() {
		return $this->method;
	}
	
	public function getHttpAccept() {
		return $this->http_accept;
	}
	
	public function getRequestVars() {
		return $this->request_vars;
	}
}

class OAuthController {
	
	public $is_signed;
	public $consumer_key;
	public $consumer_secret;
	public $consumer_id;
	protected $store;
	public function __construct() {
		global $do_return;
		//print_r($_SESSION);
		// First check if we are working with one of oauth main methods (register, request_token, auth or access-token)
		$this->dispatch ();
		// Check if the request comes signed with oauth protocol
		$this->isSigned ();
	}
	public function dispatch() {
		global $wpr;
		switch ($wpr['call']) {
			case 'register' :
				self::doRegister ();
				break;
			case 'request-token' :
				self::doRequestToken ();
				break;
			case 'auth' :
				self::doAuthorize ();
				break;
			case 'access-token' :
				self::doAccessToken ();
				break;
		}
	}
	public function isSigned() {
		global $wpdb;
		OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		if (OAuthRequestVerifier::requestIsSigned ()) {
			try {
				$req = new OAuthRequestVerifier ( );
				$user_id = $req->verify ();
				// If we have an user_id, then login as that user (for this request)
				if ($user_id) {
					$this->is_signed = true;
				}
			} catch ( OAuthException $e ) {
				// The request was signed, but failed verification
				header ( 'HTTP/1.1 401 Unauthorized' );
				header ( 'WWW-Authenticate: OAuth realm=""' );
				header ( 'Content-Type: text/plain; charset=utf8' );
				
				echo $e->getMessage ();
				exit ();
			}
		} else {
			$this->is_signed = false;
		}
	}
	protected static function header() {
		header ( 'X-XRDS-Location: http://' . $_SERVER ['SERVER_NAME'] . '/services.xrds' );
	}
	protected function doRegister($args = array()) {
		
		global $wpdb,$current_user,$do_return;
		wp_get_current_user();
		// Future check for only registred users to sign to API
		if (0 != $current_user->ID) {
			self::header ();
			if (isset ( $_POST ['submit_application'] )) {
				wpr_set_defaults ( $args, array ('requester_name' => $current_user->user_login, 'requester_email' => $current_user->user_email, 'callback_uri' => @$_POST ['callback_uri'], 'application_uri' => @$_POST ['application_uri'], 'application_title' => @$_POST ['application_title'], 'application_descr' => @$_POST ['application_descr'], 'application_notes' => @$_POST ['application_notes'], 'application_type' => @$_POST ['application_type'], 'application_commercial' => @$_POST ['application_commercial'] ) );
				unset ( $_POST );
				// Register the consumer
				$this->store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
				$key = $this->store->updateConsumer ( $args, $current_user->ID );
				
				// Get the complete consumer from the store
				$consumer = $this->store->getConsumer ( $key, $current_user->ID );
				
				// Some interesting fields, the user will need the key and secret
				$this->consumer_id = $consumer ['id'];
				$this->consumer_key = $consumer ['consumer_key'];
				$this->consumer_secret = $consumer ['consumer_secret'];
			}
			require_once WPR_PLUGIN_FOLDER_PATH.'html_api_register.php';
			// Safely break back to WordPress
			$do_return = true;
			return;
		} else {
			header('location:'.wp_login_url(curPageURL()));
			exit ();
		}
	}
	protected static function doRequestToken() {
		global $wpdb,$do_return;
		
		self::header ();
		OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		$server = new OAuthServer ( );
		
		$token = $server->requestToken ();
		// Safely break back to WordPress
		//$do_return = true;
		//return;
		exit();
	}
	protected static function doAuthorize() {
		global $wpdb,$current_user,$do_return;
		wp_get_current_user();
		self::header ();
		// Future check for only registred users to sign to API
		
		if (0 != $current_user->ID) {
			OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
			$server = new OAuthServer ( );
			try {
				// Check if there is a valid request token in the current request
				// Returns an array with the consumer key, consumer secret, token, token secret and token type.
				$rs = $server->authorizeVerify ();
				if ($_SERVER ['REQUEST_METHOD'] == 'POST') {
					// See if the user clicked the 'allow' submit button (or whatever you choose)
					$authorized = array_key_exists ( 'allow', $_POST );
					//$authorized = true;
					// Set the request token to be authorized or not authorized
					// When there was a oauth_callback then this will redirect to the consumer
					$server->authorizeFinish ( $authorized, $current_user->ID );
					
					// No oauth_callback, show the user the result of the authorization
					// ** your code here **
					//echo 'Authorized';
				} elseif ($_SERVER ['REQUEST_METHOD'] == 'GET') {
					//$authorized = true;
					require_once WPR_PLUGIN_FOLDER_PATH.'html_api_authorize.php';
					// Safely break back to WordPress
					$do_return = true;
					return;
					//$server->authorizeFinish ( $authorized, $_SESSION ['user_id'] );
					//echo 'Authorized';
				} else {
					echo 'No recognized request. Only POST or GET.';
				}
			} catch ( OAuthException $e ) {
				// No token to be verified in the request, show a page where the user can enter the token to be verified
				echo 'No Token found!';
			}
		} else {
			//WPRESTUtils::sendResponse ( 401 );
			header('location:'.wp_login_url( curPageURL() ));
			exit ();
		}
		// Safely break back to WordPress
		$do_return = true;
		return;
	}
	protected static function doAccessToken() {
		global $wpdb,$do_return;
		
		self::header ();
		OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		$server = new OAuthServer ( );
		$server->accessToken ();
		// Safely break back to WordPress
		//$do_return = true;
		//return;
		exit();
	}
}
?>