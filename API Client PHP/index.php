<?php
session_start();
require_once ('WP-API.php');
global $configdata;
$configdata ['consumer_key'] = "2758f6bdbbb899a7f2f85c463d0f87d604bf96dad";
$configdata ['consumer_secret'] = "eea28bb743854fb5b7162542391c10bb";
function wpapi_oauth() {
	global $configdata;
	// require twitterOAuth lib
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
	} 
	/*
	 * Switch based on where in the process you are
	 *
	 * 'default': Get a request token from twitter for new user
	 * 'returned': The user has authorize the app on twitter
	 */
	switch ($state) { 
		default:
		    /* Create FlocksOAuth object with app key/secret */
		    $to = new WPOAuth ( $configdata ['consumer_key'], $configdata ['consumer_secret'] );
			/* Request tokens from twitter */
			$tok = $to->getRequestToken ();
			
			/* Save tokens for later */
			$_SESSION ['oauth_request_token'] = $token = $tok ['oauth_token'];
			$_SESSION ['oauth_request_token_secret'] = $tok ['oauth_token_secret'];
			$_SESSION ['oauth_state'] = "start";
			
			/* Callback url to be directed after authentication */
			$callback = "http://www.joseairosa.com/wpapi";
			
			/* Build the authorization URL */
			$request_link = $to->getAuthorizeURL ( $token, $callback );
			
			/* Build link that gets user to flocks to authorize the app */
			$content = 'To view your twitter updates please <a href="' . $request_link . '"><img src="http://flocks.biz/img/flocks_signin.png" alt="" style="vertical-align: middle;" /></a>';
			//$content .= '<br /><a href="' . $request_link . '">' . $request_link . '</a>';
			break;
		case 'returned':
		    /* If the access tokens are already set skip to the API call */
		    if (empty($_SESSION ['oauth_access_token']) && empty($_SESSION ['oauth_access_token_secret'])) {
				/* Create FlocksOAuth object with app key/secret and token key/secret from default phase */
				$to = new WPOAuth ( $configdata ['consumer_key'], $configdata ['consumer_secret'], $_SESSION ['oauth_request_token'], $_SESSION ['oauth_request_token_secret'] );
				/* Request access tokens from twitter */
				$tok = $to->getAccessToken ();
				print_r($tok);
				/* Save the access tokens. Normally these would be saved in a database for future use. */
				$_SESSION ['oauth_access_token'] = $tok ['oauth_token'];
				$_SESSION ['oauth_access_token_secret'] = $tok ['oauth_token_secret'];
			}
			//$Users = new Users();
			//$Users->setUserTwitterCredentials(array('user_id' => $_SESSION['user_id'],'twitter_access_token' => $_SESSION ['oauth_access_token'], 'twitter_access_token_secret' => $_SESSION ['oauth_access_token_secret']));
			if(isset($_GET['oauth_token'])) {
				//header('location:http://community.flocks.biz/social');
				//exit();
			}
			/* Random copy */
			//$content = 'You\'re logged in';
			//$content .= '<a href="https://twitter.com/account/connections">https://twitter.com/account/connections</a>';
			
			/* Create TwitterOAuth with app key/secret and user access key/secret */
			//$to = new TwitterOAuth ( $consumer_key, $consumer_secret, $_SESSION ['oauth_access_token'], $_SESSION ['oauth_access_token_secret'] );
			/* Run request on twitter API as user. */
			//$content = $to->OAuthRequest ( 'https://twitter.com/account/verify_credentials.xml', array (), 'GET' );
			//$content = $to->OAuthRequest('https://twitter.com/statuses/update.xml', array('status' => 'Test OAuth update. #testoauth'), 'POST');
			//$content = $to->OAuthRequest('https://twitter.com/statuses/replies.xml', array(), 'POST');
			break;
	}
	//print_r($_SESSION);
	return $content;
}
?>
<?php echo ((!empty($_SESSION['oauth_access_token']) && !empty($_SESSION['oauth_access_token_secret'])) ? '' : wpapi_oauth() ) ?>
<?php
if(!empty($_SESSION['oauth_access_token']) && !empty($_SESSION['oauth_access_token_secret'])) {
	$to = new WPOAuth ( $configdata ['consumer_key'], $configdata ['consumer_secret'], $_SESSION ['oauth_access_token'], $_SESSION ['oauth_access_token_secret'] );	
	$xml_pure = $to->OAuthRequest ( 'http://api.flocks.biz/tasks.json', array (), 'POST' ) ;
	echo $xml_pure;
}

?>