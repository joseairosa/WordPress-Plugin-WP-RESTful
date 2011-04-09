<?php
// Copyright 2010 WP-API by José P. Airosa. All Rights Reserved.
//
// Based on Abraham Williams work (abraham@abrah.am) http://abrah.am
//
// +---------------------------------------------------------------------------+
// | WP-API Platform PHP5 client                                               |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2010 José P. Airosa                                         |
// | All rights reserved.                                                      |
// |                                                                           |
// | Redistribution and use in source and binary forms, with or without        |
// | modification, are permitted provided that the following conditions        |
// | are met:                                                                  |
// |                                                                           |
// | 1. Redistributions of source code must retain the above copyright         |
// |    notice, this list of conditions and the following disclaimer.          |
// | 2. Redistributions in binary form must reproduce the above copyright      |
// |    notice, this list of conditions and the following disclaimer in the    |
// |    documentation and/or other materials provided with the distribution.   |
// |                                                                           |
// | THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR      |
// | IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES |
// | OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.   |
// | IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT  |
// | NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY     |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT       |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF  |
// | THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.         |
// +---------------------------------------------------------------------------+
// | For help with this library, contact me@joseairosa.com                     |
// +---------------------------------------------------------------------------+

if(!function_exists("curPageURL")) {
	function curPageURL() {
		$pageURL = 'http';
		if (isset($_SERVER ["HTTPS"]) && $_SERVER ["HTTPS"] == "on") {
			$pageURL .= "s";
		}
		$pageURL .= "://";
		if ($_SERVER ["SERVER_PORT"] != "80") {
			$pageURL .= $_SERVER ["SERVER_NAME"] . ":" . $_SERVER ["SERVER_PORT"] . $_SERVER ["REQUEST_URI"];
		} else {
			$pageURL .= $_SERVER ["SERVER_NAME"] . $_SERVER ["REQUEST_URI"];
		}
		return $pageURL;
	}
}

function wpapi_oauth($consumer_key = null,$consumer_secret = null,$api_root = null) {
	global $wpdb,$current_user;
	wp_get_current_user();
	
	/* Load OAuth lib. You can find it at http://oauth.net */
	require_once ('OAuth.php');
	
	/* Set up placeholder */
	$content = NULL;
	
	/* Set state if previous session */
	$state = @$_SESSION ['oauth_state'];
	
	/* Checks if oauth_token is set from returning from twitter */
	$session_token = @$_SESSION ['oauth_request_token'];
	
	/* Checks if oauth_token is set from returning from twitter */
	$oauth_token = @$_GET ['oauth_token'];
	
	/* Set section var */
	$section = @$_REQUEST ['section'];
	
	/* If oauth_token is missing get it */
	if (@$_GET ['oauth_token'] != NULL && $_SESSION ['oauth_state'] === 'start') { 
		$_SESSION ['oauth_state'] = $state = 'returned';
	} else {
		$_SESSION ['oauth_state'] = $state = 'start';
	}
	
	/*
	 * Switch based on where in the process you are
	 *
	 * 'default': Get a request token from twitter for new user
	 * 'returned': The user has authorize the app on twitter
	 */
	switch ($state) { 
		default:
		    /* Create WPAPIOAuth object with app key/secret */
		    $to = new WPOAuth ( $consumer_key, $consumer_secret, null, null, $api_root);
			/* Request tokens from twitter */
			$tok = $to->getRequestToken ();
			//echo $to->lastAPICall();
			/* Save tokens for later. Clean token that might cuase conflicts */
			unset($_SESSION['oauth_access_token']);
			unset($_SESSION['oauth_access_token_secret']);
			$_SESSION ['oauth_request_token'] = $token = $tok ['oauth_token'];
			$_SESSION ['oauth_request_token_secret'] = $tok ['oauth_token_secret'];
			$_SESSION ['oauth_state'] = "start";
			
			/* Callback url to be directed after authentication */
			$callback = curPageURL();
			
			/* Build the authorization URL */
			$request_link = $to->getAuthorizeURL ( $token, $callback );
			
			/* Build link that gets user to flocks to authorize the app */
			$content = $request_link;
			
			$WPREST = new WPRESTConsumer ('store-request-token',array('consumer_key' => $consumer_key,'token' => $tok ['oauth_token'],'token_secret' => $tok ['oauth_token_secret']));
			break;
		case 'returned':
		    /* If the access tokens are already set skip to the API call */
		    global $wpdb,$current_user;
		    if (empty($_SESSION ['oauth_access_token']) && empty($_SESSION ['oauth_access_token_secret'])) {
				/* Create FlocksOAuth object with app key/secret and token key/secret from default phase */
				$to = new WPOAuth ( $consumer_key, $consumer_secret, $_SESSION ['oauth_request_token'], $_SESSION ['oauth_request_token_secret'],$api_root );
				/* Request access tokens from twitter */
				$tok = $to->getAccessToken ();
				$WPREST = new WPRESTConsumer ('store-access-token',array('consumer_key' => $consumer_key,'token' => $tok ['oauth_token'],'token_secret' => $tok ['oauth_token_secret']));
				
				$_SESSION ['oauth_access_token'] = $tok ['oauth_token'];
				$_SESSION ['oauth_access_token_secret'] = $tok ['oauth_token_secret'];
			}
			/* You could have some code here to store both oauth_access_token and oauth_access_token_secret on the database and associate it with the requesting user. This way you can load the tokens to a $_SESSION when the user logs in and never worrie about the user having to press the "Sign with Flocks" button. */
			if(isset($_GET['oauth_token'])) {
				//header('location:http://community.flocks.biz/social');
				//exit();
			}
			break;
	}
	return $content;
}

/**
 * WP OAuth class
 */
class WPOAuth {
	/* Contains the last HTTP status code returned */
	private $http_status;
	
	/* Contains the last API call */
	private $last_api_call;
	
	/* Set up the API root URL */
	public $TO_API_ROOT;
	
	/**
	 * Set API URLS
	 */
	function requestTokenURL() {
		return $this->TO_API_ROOT . 'request-token/';
	}
	function authorizeURL() {
		return $this->TO_API_ROOT . 'auth/';
	}
	function accessTokenURL() {
		return $this->TO_API_ROOT . 'access-token/';
	}
	
	/**
	 * Debug helpers
	 */
	function lastStatusCode() {
		return $this->http_status;
	}
	function lastAPICall() {
		return $this->last_api_call;
	}
	
	/**
	 * construct WPOAuth object
	 */
	function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL, $api_root = NULL) {
		if(!is_null($api_root))
			$this->TO_API_ROOT = $api_root;
		$this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1 ( );
		$this->consumer = new OAuthConsumer ( $consumer_key, $consumer_secret );
		if (! empty ( $oauth_token ) && ! empty ( $oauth_token_secret )) {
			$this->token = new OAuthConsumer ( $oauth_token, $oauth_token_secret );
		} else {
			$this->token = NULL;
		}
	}
	
	/**
	 * Get a request_token from WP-API
	 *
	 * @returns a key/value array containing oauth_token and oauth_token_secret
	 */
	function getRequestToken() {
		$r = $this->oAuthRequest ( $this->requestTokenURL () );
		$token = $this->oAuthParseResponse ( $r );
		$this->token = new OAuthConsumer ( $token ['oauth_token'], $token ['oauth_token_secret'] );
		return $token;
	}
	
	/**
	 * Parse a URL-encoded OAuth response
	 *
	 * @return a key/value array
	 */
	function oAuthParseResponse($responseString) {
		$r = array ();
		foreach ( explode ( '&', $responseString ) as $param ) {
			$pair = explode ( '=', $param, 2 );
			if (count ( $pair ) != 2)
				continue;
			$r [urldecode ( $pair [0] )] = urldecode ( $pair [1] );
		}
		return $r;
	}
	
	/**
	 * Get the authorize URL
	 *
	 * @returns a string
	 */
	function getAuthorizeURL($token,$callback = '') {
		if (is_array ( $token ))
			$token = $token ['oauth_token'];
		if(!empty($callback)) {
			$callback = "&oauth_callback=".urlencode($callback);
		}
		return $this->authorizeURL () . '?oauth_token=' . $token . $callback;
	}
	
	/**
	 * Exchange the request token and secret for an access token and
	 * secret, to sign API calls.
	 *
	 * @returns array("oauth_token" => the access token,
	 *                "oauth_token_secret" => the access secret)
	 */
	function getAccessToken($token = NULL) {
		$r = $this->oAuthRequest ( $this->accessTokenURL () );
		$token = $this->oAuthParseResponse ( $r );
		$this->token = new OAuthConsumer ( $token ['oauth_token'], $token ['oauth_token_secret'] );
		return $token;
	}
	
	/**
	 * Format and sign an OAuth / API request
	 */
	function oAuthRequest($url, $args = array(), $method = NULL) {
		if (empty ( $method ))
			$method = empty ( $args ) ? "GET" : "POST";
		$req = OAuthRequest::from_consumer_and_token ( $this->consumer, $this->token, $method, $url, $args );
		$req->sign_request ( $this->sha1_method, $this->consumer, $this->token );
		switch ($method) {
			case 'GET' :
				return $this->http ( $req->to_url () );
			case 'POST' :
				return $this->http ( $req->get_normalized_http_url (), $req->to_postdata () );
		}
	}
	
	/**
	 * Make an HTTP request
	 *
	 * @return API results
	 */
	function http($url, $post_data = null) {
		$ch = curl_init ();
		if (defined ( "CURL_CA_BUNDLE_PATH" ))
			curl_setopt ( $ch, CURLOPT_CAINFO, CURL_CA_BUNDLE_PATH );
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		//////////////////////////////////////////////////
		///// Set to 1 to verify Hots SSL Cert ///////
		//////////////////////////////////////////////////
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		if (isset ( $post_data )) {
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post_data );
		}
		$response = curl_exec ( $ch );
		$this->http_status = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
		$this->last_api_call = $url;
		curl_close ( $ch );
		if(empty($response)) {
			return 'WP-API might be down or unresponsive. Please go to http://flocks.biz and check if the main website is working. Send us an email to hello@flocks.biz in case you have more doubts.';
		}
		if(preg_match("/request\-token/i",$response) || preg_match("/access\-token/i",$response)) {
			//echo "<br/><br/>".preg_replace(array("/.*oauth\_version\=1\.0/i"),array(""),urldecode($response))."<br/><br/>";
			return preg_replace(array("/.*oauth\_version\=1\.0/i"),array(""),urldecode($response));
		} else {
			//echo "<br/><br/>".$response."<br/><br/>";
			return $response;
		}
	}
}
?>