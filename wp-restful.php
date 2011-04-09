<?php
/*
Plugin Name: WP-RESTful
Plugin URI: http://www.joseairosa.com/2010/06/29/wp-restful-wordpress-plugin/
Description: Installs and configures a full-fledged REST API on your wordpress blog
Author: Jos&eacute; P. Airosa
Version: 0.1
Author URI: http://www.joseairosa.com/

Copyright 2010  José P. Airosa  (email : me@joseairosa.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $wpdb,$wpr,$wp_query,$message;
$wpr = array();
//========================================
// Plugin Settings
//========================================
$wpr['reserved_requests'] = array("request-token","auth","access-token","register");
$wpr['reserved_requests_without_template'] = array("request-token","access-token");
$wpr['reserved_requests_with_template'] = array("auth","register");

// Fields maping that we allow to be returned on the API
$wpr['fields'] = array(
	'Posts' => array(
		'post_title' => 'Post Title',
		'post_content' => 'Post Content',
		'guid' => 'Post Link',
		'post_type' => 'Post Type'
	),
	'Comments' => array(
		'comment_ID' => 'Comment ID',
		'comment_author' => 'Comment Author',
		'comment_author_url' => 'Comment Author URL',
		'comment_date' => 'Comment Date',
		'comment_date_gmt' => 'Comment Date (GMT)',
		'comment_content' => 'Comment Content'
	)
);

// Pluarlization array.
$wpr['pluralization'] = array('status' => 'statuses');

$wpr['requiring_auth'] = array();

define("WPR_DB_VERISON","1.0.0");
define("WPR_VERISON","0.1");
define("WPR_DB_PREFIX","wpr_");
define("WPR_REQUIRED_PHP_VERSION","5.1.3");
define("WPR_HAS_MOD_HEADERS",in_array("mod_headers",wpr_apache_get_modules()));

// Database tables
define("WPR_DB_TABLE_OAUTH_LOG",WPR_DB_PREFIX . "oauth_log");
define("WPR_DB_TABLE_OAUTH_CONSUMER_REGISTRY",WPR_DB_PREFIX . "oauth_consumer_registry");
define("WPR_DB_TABLE_OAUTH_CONSUMER_TOKEN",WPR_DB_PREFIX . "oauth_consumer_token");
define("WPR_DB_TABLE_OAUTH_SERVER_REGISTRY",WPR_DB_PREFIX . "oauth_server_registry");
define("WPR_DB_TABLE_OAUTH_SERVER_NONCE",WPR_DB_PREFIX . "oauth_server_nonce");
define("WPR_DB_TABLE_OAUTH_SERVER_TOKEN",WPR_DB_PREFIX . "oauth_server_token");
// Database tables - END

define("WPR_PLUGIN_FOLDER_NAME","wp-restful");
define("WPR_PLUGIN_FOLDER_PATH",wpr_check_for_trailing_slash(WP_PLUGIN_DIR).wpr_check_for_trailing_slash(WPR_PLUGIN_FOLDER_NAME));
define("WPR_XMLWRAPPER_PATH",WPR_PLUGIN_FOLDER_PATH."lib/xmlwrapper/xmlwrapper.php");
define("WPR_SCRIPT_MAIN",wpr_check_for_trailing_slash(WP_PLUGIN_URL).wpr_check_for_trailing_slash(WPR_PLUGIN_FOLDER_NAME)."js/main.js");
define("WPR_ALLOWED_REGEX",'/\bposts?\b|\busers?\b|\bstatus\b|\bcomments\b|\bcategories\b|\btags\b/i');
define("WPR_REQUIRES_OAUTH_REGEX",wpr_build_requires_auth_regex());
//define("WPR_REQUIRES_OAUTH_REGEX",'/\bnone\b/i');

require_once(WPR_PLUGIN_FOLDER_PATH."wp-restful-widgets.php");

//========================================
// Install / Uninstall Plugin
//========================================
function wpr_install() {
	global $wpdb;
	
	$db_installed_version = get_option('wpr_db_version');
	
	// Update/Install database in case it doesn't exist or it needs to be updated
	if($db_installed_version != WPR_DB_VERISON) {
		require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
		
		$sql = file_get_contents(WPR_PLUGIN_FOLDER_PATH . 'lib/store/mysql/mysql.sql');
		$ps  = explode('#--SPLIT--', $sql);
		
		foreach ($ps as $p) {
			$p = preg_replace('/^\s*#.*$/m', '', $p);
			dbDelta($p);
		}
		
		update_option("wpr_db_version", WPR_DB_VERISON);
		
		if(!get_option('wpr_user_allowed_requests'))
			update_option('wpr_user_allowed_requests',array());
		if(!get_option('wpr_post_allowed_requests'))
			update_option('wpr_post_allowed_requests',array());
	}
	
	// Activate server by default
	$server_status = get_option('wpr_server_active');
	if(empty($server_status))
		update_option('wpr_server_active',1);
	
	$consumer_allowed_requests = get_option('wpr_consumer_allowed_requests');
	if(empty($consumer_allowed_requests))
		update_option('wpr_consumer_allowed_requests',250);
		
	// Initialize our plugin repository
	if(!is_array(get_option("wpr_plugins"))) {
		update_option("wpr_plugins",array());
	}
}

function wpr_uninstall() {
	global $wpdb;
	
}
add_action('activate_'.plugin_basename(__FILE__), 'wpr_install');
add_action('deactivate_'.plugin_basename(__FILE__), 'wpr_uninstall');

//========================================
// Base function to build plugin array 
//========================================
function wpr_add_plugin($function) {
	global $wpr;
	if(function_exists($function)) {
		$array_to_add = call_user_func($function);
		$wpr['fields'] = array_merge($wpr['fields'],$array_to_add);
	}
}

//========================================
// Get pluralization exerpts 
//========================================
function wpr_get_pluralization_exerpts() {
	global $wpr;
	return $wpr['pluralization']; 
}

//========================================
// Pluralize a given string
//========================================
function wpr_pluralize($string) {
	$exerpts = wpr_get_pluralization_exerpts();
	foreach($exerpts as $singular => $plural) {
		if($string == $singular || $string == $plural)
			return $plural;
	}
	if(substr($string,-1) == "s") {
		return $string;
	}
	return $string."s";
}

//========================================
// Check if a given string is pluralized
//========================================
function wpr_is_pluralized($string) {
	$exerpts = wpr_get_pluralization_exerpts();
	foreach($exerpts as $singular => $plural) {
		if($string == $plural)
			return true;
		elseif($string == $singular)
			return false;
	}
	if(substr($string,-1) == "s") {
		return true;
	}
	return false;
}

//========================================
// Unpluralize a given string
//========================================
function wpr_unpluralize($string) {
	$exerpts = wpr_get_pluralization_exerpts();
	foreach($exerpts as $singular => $plural) {
		if($string == $singular || $string == $plural)
			return $singular;
	}
	if(substr($string,-1) == "s") {
		return substr($string,0,-1);
	}
	return $string;
}

//========================================
// Base function to build pluralization array 
//========================================
function wpr_add_pluralization($function) {
	global $wpr;
	if(function_exists($function)) {
		$array_to_add = call_user_func($function);
		$wpr['pluralization'] = array_merge($wpr['pluralization'],$array_to_add);
	}
}

//========================================
// Checks for apache modules (the safe way)
//========================================
// Added on version 0.1.3
function wpr_apache_get_modules() {
	if(function_exists("apache_get_modules"))
		return apache_get_modules();
	else
		return array('mod_rewrite','mod_headers','core');
}

//========================================
// Checks whether a given string has a trailer slash and if not, add it. 
//========================================
function wpr_build_requires_auth_regex() {
	global $wpr;
	$requires_auth = get_option('wpr_requires_auth');
	if(empty($requires_auth))
		return '/\bnone\b/i';
	$regex = array();
	foreach($requires_auth as $field) {
		// Default to lowercase
		$field_lower = strtolower($field);
		// Check on our pluralization array to see if we can find a match to our field
		foreach($wpr['pluralization'] as $singular => $plural) {
			if($singular == $field || $plural == $field) {
				$regex[] = '\b'.$field_lower.'\b|\b'.$field_lower.'\b';		
			} else {
				if(wpr_is_pluralized($field_lower))
					$regex[] = '\b'.wpr_unpluralize($field_lower).'\b|\b'.$field_lower.'\b';
				else
					$regex[] = '\b'.$field_lower.'s?\b';
			}	
		}
	}
	return '/'.implode("|",$regex).'/i';
}

//========================================
// Checks whether a given string has a trailer slash and if not, add it. 
//========================================
function wpr_check_for_trailing_slash($path) {
	if(substr($path,-1) != "/")
		return $path."/";
	else
		return $path;
}

//========================================
// Messaging system
//========================================
function wpr_add_message($content,$error = false) {
	global $message;
	if(!is_array($message)) {
		$message = array();	
	}
	$message[] = array("message" => $content, "error" => $error);
}

//========================================
// Renders Admin pages 
//========================================
// Render Main Admin Page
function wpr_admin_main() {
?>
	<div class="wrap">
    	<h2>WP-RESTful - <?php echo WPR_VERISON;?></h2>
    	<p>Welcome to WP-RESTful main page.</p>
    	<p>Using this page you can keep a track on what might be broken with you Plugin.<br/>If for some reason the plugin ceases to work please check this page.</p>
    	<p>If you like this Plugin please donate for the continuous development.<br/><form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHVwYJKoZIhvcNAQcEoIIHSDCCB0QCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYA6ETNzDKTcz5My7JJu12UMW78ZzGR/Om0hHznOl6yW5TEXgoSeIN9erLeooDuq/KHyXoGZuHZlSiMZsdB5TzaDtVR88fakEvcT8rvfe4S1eqqdQbSoFtjfYtDMFPyxaDnKHXhFYaaz/co8BAWQPdFwGgSq9bSnDQabB8I46spzPzELMAkGBSsOAwIaBQAwgdQGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQICtzForaDCreAgbAylXX+zGDEYYaoUZyRACJ2939gZuT8DKd/jBokR/CXPWwKzYkN6qBwm4GWCL3L5etOQ5sRlmf426CzkkQ7ppKPpJxllNzQpWhmJe+i/1u2S+2JgKrQRnWHMFJ5xdxc1McRQ22ZF14a/KQhf1bJKG0B+R36wMqtoLF5Dco/fa21shGuSkW/5C6UK6iyrnA1Vd/TekMc54KfaZLtj8dLH1DdT+1dGmJ5WA5urvSfhZVUMqCCA4cwggODMIIC7KADAgECAgEAMA0GCSqGSIb3DQEBBQUAMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTAeFw0wNDAyMTMxMDEzMTVaFw0zNTAyMTMxMDEzMTVaMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTCBnzANBgkqhkiG9w0BAQEFAAOBjQAwgYkCgYEAwUdO3fxEzEtcnI7ZKZL412XvZPugoni7i7D7prCe0AtaHTc97CYgm7NsAtJyxNLixmhLV8pyIEaiHXWAh8fPKW+R017+EmXrr9EaquPmsVvTywAAE1PMNOKqo2kl4Gxiz9zZqIajOm1fZGWcGS0f5JQ2kBqNbvbg2/Za+GJ/qwUCAwEAAaOB7jCB6zAdBgNVHQ4EFgQUlp98u8ZvF71ZP1LXChvsENZklGswgbsGA1UdIwSBszCBsIAUlp98u8ZvF71ZP1LXChvsENZklGuhgZSkgZEwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tggEAMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADgYEAgV86VpqAWuXvX6Oro4qJ1tYVIT5DgWpE692Ag422H7yRIr/9j/iKG4Thia/Oflx4TdL+IFJBAyPK9v6zZNZtBgPBynXb048hsP16l2vi0k5Q2JKiPDsEfBhGI+HnxLXEaUWAcVfCsQFvd2A1sxRr67ip5y2wwBelUecP3AjJ+YcxggGaMIIBlgIBATCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwCQYFKw4DAhoFAKBdMBgGCSqGSIb3DQEJAzELBgkqhkiG9w0BBwEwHAYJKoZIhvcNAQkFMQ8XDTEwMDYyNzE3NTIyNVowIwYJKoZIhvcNAQkEMRYEFF2c6Shg7aIbE63sybzaKLj8qWHIMA0GCSqGSIb3DQEBAQUABIGAm9DZAM43IMkaapwEOEUbY0mWNXMJQT9QMonXAByI3I9MP1uleM4Th0m139MbNT4QPuAGWyZDIYK9dwfOLSjHfiBQSq0MKCNXCa/G9YuTukQ4VIBurI2C4/jb7WozjfARkERY+gd4wjutT6Z/X26nUElRYOBflDt7EvAXouZ6zho=-----END PKCS7-----
">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
</p>
    	<?php if(!empty($message)) : ?>
	    	<?php foreach($message as $message_single) :?>
    			<?php if(!$message_single['error']) :?>
    	<div class="updated fade" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
    			<?php else: ?>	
    	<div class="error" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
		    	<?php endif;?>
    		<?php endforeach;?>
    	<?php endif; ?>
    	
    	<div style="margin-top:30px">
    		<div style="float:left;"><object width="400" height="315"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=12940958&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=12940958&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="400" height="315"></embed></object><p><a href="http://vimeo.com/12940958">WP-RESTful - Starting and setting up</a> from <a href="http://vimeo.com/joseairosa">Jos&eacute; Airosa</a> on <a href="http://vimeo.com">Vimeo</a>.</p></div>
			<div style="float:right;"><object width="400" height="315"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://vimeo.com/moogaloop.swf?clip_id=12941005&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" /><embed src="http://vimeo.com/moogaloop.swf?clip_id=12941005&amp;server=vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=00ADEF&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="400" height="315"></embed></object><p><a href="http://vimeo.com/12941005">WP-RESTful - Working with Plugins</a> from <a href="http://vimeo.com/joseairosa">Jos&eacute; Airosa</a> on <a href="http://vimeo.com">Vimeo</a>.</p></div>
		</div>
    	
    	<div class="wrap" style="margin-top:50px;clear:both;">
    	<h2>Compatibility:</h2>
    	<table cellspacing="0" class="widefat">
			<tbody>
				<?php if(version_compare(PHP_VERSION, WPR_REQUIRED_PHP_VERSION) >= 0):?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: green;width:200px;">PHP Version</td>
					<td class="desc">Great! You're using PHP version <?php echo PHP_VERSION?>.</td>
				</tr>
				<?php else:?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: red;width:200px;">PHP Version</td>
					<td class="desc">Damn! I require you to have at least version <?php echo PLS_REQUIRED_PHP_VERSION?> of PHP. You're using version <?php echo PHP_VERSION?>.</td>
				</tr>
				<?php endif;?>
				<?php if(WPR_HAS_MOD_HEADERS):?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: green;">Apache mod_headers</td>
					<td class="desc">Great! Your apache instalation has mod_headers module loaded.</td>
				</tr>
				<?php else:?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: red;">Apache mod_headers</td>
					<td class="desc">Damn! I might require your server to have mod_headers module loaded on your apache instalation.</td>
				</tr>
				<?php endif;?>
				<?php if(function_exists('curl_init')) :?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: green;">cURL functions</td>
					<td class="desc">Great! Your php instalation has cURL support.</td>
				</tr>
				<?php else:?>
				<tr class="alternate">
					<td class="import-system row-title" style="color: red;">cURL functions</td>
					<td class="desc">Damn! I require your php installation to have cURL support.</td>
				</tr>
				<?php endif;?>
			</tbody>
		</table>
    </div>
    <div class="wrap" style="margin-top:50px;">
    	<h2>Status:</h2>
    	<table cellspacing="0" class="widefat">
			<tbody>
				<tr class="alternate">
				<?php if(get_option('wpr_server_active')): ?>
					<td class="import-system row-title" style="color:green;width:200px;">Server</td>
					<td class="desc" style="color:green;">Online</td>
				<?php else:?>
					<td class="import-system row-title" style="color:green;">Server</td>
					<td class="desc" style="color:red;">Offline</td>
				<?php endif;?>
				</tr>
				<tr class="alternate">
					<td class="import-system row-title" style="color: green;">Active Plugins</td>
					<td class="desc"><?php echo count(get_option("wpr_plugins"))?></td>
				</tr>
			</tbody>
		</table>
    </div>
    </div>
<?php
}

// Render Admin Client Page
function wpr_admin_client() {
	global $wpdb,$wp_query;
	
	// Initiallize loop arrays
	$array_stages = array('stage1' => array(),'stage2' => array(), 'stage3' => array());
	
	require_once 'lib/REST.inc.php';
	if(isset($_POST['add_server'])) {
		$WPREST = new WPRESTConsumer (null,array('server_uri' => wpr_check_for_trailing_slash($_POST['api_url'])));
	}
	
	$sql = "SELECT * FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_REGISTRY." WHERE `ocr_id` > 0";
	$consumers = $wpdb->get_results ($sql);
	foreach($consumers as $consumer) {
		if(isset($_POST['save-continue-stage1-'.$consumer->ocr_id])) {
			$WPREST = new WPRESTConsumer ('register',array('id' => $consumer->ocr_id,'consumer_key' => $_POST['consumer-stage1-key-'.$consumer->ocr_id],'consumer_secret' => $_POST['consumer-stage1-secret-'.$consumer->ocr_id]));
		}
	}
	
	if(isset($_GET['action']) && $_GET['action'] == "delete" && isset($_GET['consumer']) && !empty($_GET['consumer'])) {
		$WPREST = new WPRESTConsumer ('delete',array('id' => $_GET['consumer']));
		wpr_add_message("Consumer deleted successfully!");
	}
?>
	<div class="wrap">
    	<h2>WP-RESTful - Servers Management</h2>
    	<p>In this section you can manage your connections, as a consumer, to other blogs (servers).</p>
    	<?php if(!empty($message)) : ?>
	    	<?php foreach($message as $message_single) :?>
    			<?php if(!$message_single['error']) :?>
    	<div class="updated fade" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
    			<?php else: ?>	
    	<div class="error" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
		    	<?php endif;?>
    		<?php endforeach;?>
    	<?php endif; ?>
    	<!-- CONTENT HERE -->
    	<div class="wrap">
    		<h2>Add a new API Server</h2>
    		<form action="admin.php?page=wpr_admin_client" method="post">
	    		<table class="form-table">
	    			<tr>
				        <th><label for="domain"><?php _e('Client API URL:'); ?></label></th>
				        <td>
				        	<input type="text" class="regular-text code" name="api_url" id="api_url" value="<?php echo ((isset($_POST['api_url'])) ? @$_POST['api_url'] : 'http://')?>" />
				        	<span class="description" style="display:block;">The client that you're connecting to also needs to have this plugin installed and the Server component active. (Example: http://www.someblog.com/api/).</span>
				        </td>
				    </tr>
	    		</table>
				<p class="submit">
					<input type="submit" value="Add Server" name="add_server">
				</p>
	    	</form>
	    </div>
    	<div class="wrap">
			<?php 
			$sql = "SELECT * FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_REGISTRY." WHERE `ocr_id` > 0";
			$consumers = $wpdb->get_results ($sql);
			foreach($consumers as $consumer) {
				if(empty($consumer->ocr_consumer_key) || empty($consumer->ocr_consumer_secret)) {
					$array_stages['stage1'][] ='
					<tr class="alternate" id="consumer-stage1-'.$consumer->ocr_id.'">
						<form action="" method="post">
							<th class="check-column" scope="row">
								<input type="checkbox" disabled="disabled" value="'.$consumer->ocr_id.'" id="consumer-stage1_'.$consumer->ocr_id.'" name="consumers-stage1[]">
							</th>
							<td class="consumer-stage1-key column-consumer-stage1-key">
								<input type="text" aria-required="true" value="" id="consumer-stage1-key-'.$consumer->ocr_id.'" name="consumer-stage1-key-'.$consumer->ocr_id.'" onmouseout="wpr_check_register_step_1('.$consumer->ocr_id.')" onblur="wpr_check_register_step_1('.$consumer->ocr_id.')" onfocus="wpr_check_register_step_1('.$consumer->ocr_id.')" style="width: 280px;">
								<div class="row-actions">
									<span class="delete"><a href="admin.php?page=wpr_admin_client&action=delete&consumer='.$consumer->ocr_id.'" class="submitdelete">Delete</a></span>
								</div>
							</td>
							<td class="consumer-stage1-secret column-consumer-stage1-secret">
								<input type="text" aria-required="true" value="" id="consumer-stage1-secret-'.$consumer->ocr_id.'" name="consumer-stage1-secret-'.$consumer->ocr_id.'" onmouseout="wpr_check_register_step_1('.$consumer->ocr_id.')" onblur="wpr_check_register_step_1('.$consumer->ocr_id.')" onfocus="wpr_check_register_step_1('.$consumer->ocr_id.')" style="width: 240px;">
							</td>
							<td class="title column-uri">'.$consumer->ocr_server_uri.'</td>
							<td class="title column-status">
								<input type="button" style="display:block;" class="button-secondary action" id="register-'.$consumer->ocr_id.'" name="register-'.$consumer->ocr_id.'" value="Step 1 - Register" onclick="window.open(\''.wpr_check_for_trailing_slash($consumer->ocr_server_uri).'register/\')">
								<input type="submit" style="display:none;" class="button-secondary action" id="save-continue-stage1-'.$consumer->ocr_id.'" name="save-continue-stage1-'.$consumer->ocr_id.'" value="Save &amp; Continue">
							</td>
						<td class="added column-added">'.$consumer->ocr_timestamp.'</td>
						</form>
					</tr>';
				} else {
					$sql = "SELECT oct_id,oct_token_type FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_TOKEN." WHERE `oct_ocr_id_ref` = ".$consumer->ocr_id;
					$consumer_token = $wpdb->get_row ($sql);
					
					if(!isset($consumer_token->oct_token_type) || $consumer_token->oct_token_type == "request") {
						// Call Consumer OAuth processor  
						require_once WPR_PLUGIN_FOLDER_PATH.'lib/consumer/WP-API.php';
						$auth_link = wpapi_oauth($consumer->ocr_consumer_key,$consumer->ocr_consumer_secret,$consumer->ocr_server_uri);	
						
						$sql = "SELECT oct_id,oct_token_type FROM ".WPR_DB_TABLE_OAUTH_CONSUMER_TOKEN." WHERE `oct_ocr_id_ref` = ".$consumer->ocr_id;
						$consumer_token_temp = $wpdb->get_row ($sql);
						
						if(isset($consumer_token_temp->oct_token_type) && $consumer_token_temp->oct_token_type == "access") {
							$array_stages['stage3'][] ='
							<tr class="alternate" id="consumer-stage3-'.$consumer->ocr_id.'">
								<form action="" method="post">
									<th class="check-column" scope="row">
										<input type="checkbox" disabled="disabled" value="'.$consumer->ocr_id.'" id="consumer-stage3_'.$consumer->ocr_id.'" name="consumers-stage3[]">
									</th>
									<td class="title column-uri">
										'.$consumer->ocr_server_uri.'
										<div class="row-actions">
											<span class="delete"><a href="admin.php?page=wpr_admin_client&action=delete&consumer='.$consumer->ocr_id.'" class="submitdelete">Delete</a></span>
										</div>
									</td>
									<td class="title column-status">
										<input type="button" style="display:block;" class="button-secondary action" id="status-'.$consumer->ocr_id.'" name="status-'.$consumer->ocr_id.'" value="Check Status" onclick="window.open(\''.wpr_check_for_trailing_slash($consumer->ocr_server_uri).'status.xml/\')">
									</td>
								<td class="added column-added">'.$consumer->ocr_timestamp.'</td>
								</form>
							</tr>';
						} else {
							$array_stages['stage2'][] ='
							<tr class="alternate" id="consumer-stage2-'.$consumer->ocr_id.'">
								<form action="" method="post">
									<th class="check-column" scope="row">
										<input type="checkbox" disabled="disabled" value="'.$consumer->ocr_id.'" id="consumer-stage2_'.$consumer->ocr_id.'" name="consumers-stage2[]">
									</th>
									<td class="consumer-stage2-key column-consumer-stage2-key">
										<span id="consumer-stage2-key-'.$consumer->ocr_id.'">'.$consumer->ocr_consumer_key.'</span>
										<div class="row-actions">
											<span class="delete"><a href="admin.php?page=wpr_admin_client&action=delete&consumer='.$consumer->ocr_id.'" class="submitdelete">Delete</a></span>
										</div>
									</td>
									<td class="consumer-stage2-secret column-consumer-stage2-secret">
										<span id="consumer-stage2-secret-'.$consumer->ocr_id.'">'.$consumer->ocr_consumer_secret.'</span>
									</td>
									<td class="title column-uri">'.$consumer->ocr_server_uri.'</td>
									<td class="title column-status">
										<input type="button" style="display:block;" class="button-secondary action" id="auth-'.$consumer->ocr_id.'" name="auth-'.$consumer->ocr_id.'" value="Step 2 - Authorize" onclick="location.href=\''.$auth_link.'\';">
									</td>
								<td class="added column-added">'.$consumer->ocr_timestamp.'</td>
								</form>
							</tr>';
						}
					} else {
						$array_stages['stage3'][] ='
						<tr class="alternate" id="consumer-stage3-'.$consumer->ocr_id.'">
							<form action="" method="post">
								<th class="check-column" scope="row">
									<input type="checkbox" disabled="disabled" value="'.$consumer->ocr_id.'" id="consumer-stage3_'.$consumer->ocr_id.'" name="consumers-stage3[]">
								</th>
								<td class="title column-uri">
									'.$consumer->ocr_server_uri.'
									<div class="row-actions">
										<span class="delete"><a href="admin.php?page=wpr_admin_client&action=delete&consumer='.$consumer->ocr_id.'" class="submitdelete">Delete</a></span>
									</div>
								</td>
								<td class="title column-status">
									<input type="button" style="display:block;" class="button-secondary action" id="status-'.$consumer->ocr_id.'" name="status-'.$consumer->ocr_id.'" value="Check Status" onclick="window.open(\''.wpr_check_for_trailing_slash($consumer->ocr_server_uri).'status.xml/\')">
								</td>
							<td class="added column-added">'.$consumer->ocr_timestamp.'</td>
							</form>
						</tr>';
					}
				}
			}
			
			if(count($array_stages['stage1']) > 0) {
				?>
    			<h2>Pending Registration API Servers (Step 1)</h2>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
						<label for="new_role" class="screen-reader-text">Change role to...</label><select id="new_role" name="new_role"><option value="">Change role to...</option>
							<option value="administrator">Administrator</option>
							<option value="editor">Editor</option>
							<option value="author">Author</option>
							<option value="contributor">Contributor</option>
							<option value="subscriber">Subscriber</option></select>
						<input type="submit" class="button-secondary" name="changeit" value="Change">
						<input type="hidden" value="f3304501ad" name="_wpnonce" id="_wpnonce"><input type="hidden" value="/wp-debug/wp-admin/users.php" name="_wp_http_referer">
					</div>
					<br class="clear">
				</div>
				<table cellspacing="0" class="widefat fixed">
					<thead>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
							<th style="width:280px" class="manage-column column-consumer-stage1-key" id="consumer-stage1-key" scope="col">Consumer Key</th>
							<th style="width:240px" class="manage-column column-consumer-stage1-secret" id="consumer-stage1-secret" scope="col">Consumer Secret</th>
							<th style="" class="manage-column column-uri" id="uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" id="status" scope="col">Status</th>
							<th style="" class="manage-column column-added" id="added" scope="col">Updated</th>
						</tr>
					</thead>
					
					<tfoot>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-consumer-stage1-key" scope="col">Consumer Key</th>
							<th style="" class="manage-column column-consumer-stage1-secret" scope="col">Consumer Secret</th>
							<th style="" class="manage-column column-uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" scope="col">Status</th>
							<th style="" class="manage-column column-added" scope="col">Updated</th>
						</tr>
					</tfoot>
					
					<tbody class="list:consumers-stage1 consumers-stage1-list" id="consumers-stage1">
					<?php
					foreach($array_stages['stage1'] as $stage1_single) {
						echo $stage1_single;
					}
					?>
					</tbody>
				</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action2">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="Apply">
					</div>
					<br class="clear">
				</div>
				<?php
			}
			if(count($array_stages['stage2']) > 0) {
				?>
				<h2>Pending Authentication API Servers (Step 2)</h2>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
						<label for="new_role" class="screen-reader-text">Change role to...</label><select id="new_role" name="new_role"><option value="">Change role to...</option>
							<option value="administrator">Administrator</option>
							<option value="editor">Editor</option>
							<option value="author">Author</option>
							<option value="contributor">Contributor</option>
							<option value="subscriber">Subscriber</option></select>
						<input type="submit" class="button-secondary" name="changeit" value="Change">
						<input type="hidden" value="f3304501ad" name="_wpnonce" id="_wpnonce"><input type="hidden" value="/wp-debug/wp-admin/users.php" name="_wp_http_referer">
					</div>
					<br class="clear">
				</div>
				<table cellspacing="0" class="widefat fixed">
					<thead>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
							<th style="width:280px" class="manage-column column-consumer-stage2-key" id="consumer-stage2-key" scope="col">Consumer Key</th>
							<th style="width:240px" class="manage-column column-consumer-stage2-secret" id="consumer-stage2-secret" scope="col">Consumer Secret</th>
							<th style="" class="manage-column column-uri" id="uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" id="status" scope="col">Status</th>
							<th style="" class="manage-column column-added" id="added" scope="col">Updated</th>
						</tr>
					</thead>
					
					<tfoot>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-consumer-stage2-key" scope="col">Consumer Key</th>
							<th style="" class="manage-column column-consumer-stage2-secret" scope="col">Consumer Secret</th>
							<th style="" class="manage-column column-uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" scope="col">Status</th>
							<th style="" class="manage-column column-added" scope="col">Updated</th>
						</tr>
					</tfoot>
					
					<tbody class="list:consumers-stage2 consumers-stage2-list" id="consumers-stage2">
					<?php
					foreach($array_stages['stage2'] as $stage2_single) {
						echo $stage2_single;
					}
					?>	
					</tbody>
				</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action2">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="Apply">
					</div>
					<br class="clear">
				</div>
				<?php
			}
			if(count($array_stages['stage3']) > 0) {
				?>
				<h2>Active API Servers</h2>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
						<label for="new_role" class="screen-reader-text">Change role to...</label><select id="new_role" name="new_role"><option value="">Change role to...</option>
							<option value="administrator">Administrator</option>
							<option value="editor">Editor</option>
							<option value="author">Author</option>
							<option value="contributor">Contributor</option>
							<option value="subscriber">Subscriber</option></select>
						<input type="submit" class="button-secondary" name="changeit" value="Change">
						<input type="hidden" value="f3304501ad" name="_wpnonce" id="_wpnonce"><input type="hidden" value="/wp-debug/wp-admin/users.php" name="_wp_http_referer">
					</div>
					<br class="clear">
				</div>
				<table cellspacing="0" class="widefat fixed">
					<thead>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-uri" id="uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" id="status" scope="col">Status</th>
							<th style="" class="manage-column column-added" id="added" scope="col">Updated</th>
						</tr>
					</thead>
					
					<tfoot>
						<tr class="thead">
							<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
							<th style="" class="manage-column column-uri" scope="col">URI</th>
							<th style="" class="manage-column column-status" scope="col">Status</th>
							<th style="" class="manage-column column-added" scope="col">Updated</th>
						</tr>
					</tfoot>
					
					<tbody class="list:consumers-stage3 consumers-stage3-list" id="consumers-stage3">
					<?php
					foreach($array_stages['stage3'] as $stage3_single) {
						echo $stage3_single;
					}
					?>	
					</tbody>
				</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action2">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="Apply">
					</div>
					<br class="clear">
				</div>
				<?php
			}
			?>
	    </div>
    </div>
<?php
}

// Render Admin Server Page
function wpr_admin_server() {
	global $message,$wpdb,$wpr;
	// Save options
	
	require_once 'lib/REST.inc.php';
	if(isset($_POST['save_options'])) {
		
		update_option('wpr_server_active',$_POST['server_active']);
		
		update_option('wpr_consumer_allowed_requests',$_POST['consumer_allowed_requests']);
		
		if(isset($_POST['allowed_return_type_xml']) && $_POST['allowed_return_type_xml'])
			update_option('wpr_allowed_return_type_xml',1);
		else
			update_option('wpr_allowed_return_type_xml',0);
			
		if(isset($_POST['allowed_return_type_json']) && $_POST['allowed_return_type_json'])
			update_option('wpr_allowed_return_type_json',1);
		else
			update_option('wpr_allowed_return_type_json',0);
		wpr_add_message("Options saved successfully!");
	}
	// Save allowed information
	if(isset($_POST['save_allowed_information'])) {
		
		$requires_auth = array();
				
		foreach($wpr['fields'] as $field_title => $field_array) {
			$save_array = array();
			
			if(isset($_POST[$field_title.'_requires_auth'])) {
				array_push($requires_auth,$field_title);
			}
			
			foreach($field_array as $field_name => $field_preety_name) {
				if(isset($_POST[$field_title.'_'.$field_name])) {
					$save_array[] = $field_name;
				}
			}
			update_option('wpr_'.$field_title.'_allowed_requests',implode(",",$save_array));
		}
		
		update_option('wpr_requires_auth',$requires_auth);
		
		wpr_add_message("Allowed Information saved successfully!");
	}
	
	if(isset($_GET['action']) && $_GET['action'] == "delete" && isset($_GET['consumer']) && !empty($_GET['consumer'])) {
		$WPREST = new WPRESTServer ('delete',array('id' => $_GET['consumer']));
		wpr_add_message("Consumer deleted successfully!");
	}
	
	if(isset($_GET['id'])) {
		$do_edit = true;
		
		require_once 'lib/store/OAuthStoreMySQL.php';
		$store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		
		if(isset($_POST['consumer-remove-'.$_GET['id']])) {
			$store->deleteConsumer ( $_POST['consumer-token-'.$_GET['id']], $_POST['consumer-user-id-'.$_GET['id']], true );
			$do_edit = false;
			wpr_add_message("Removed successfully!");
		} elseif(isset($_POST['consumer-ban-'.$_GET['id']])) {
			// Future implementation
			$do_edit = false;
			wpr_add_message("This functionality is not yet implemented");
		} elseif(isset($_POST['consumer-reauth-'.$_GET['id']])) {
			$sql = "SELECT ost_token FROM ".WPR_DB_TABLE_OAUTH_SERVER_TOKEN." WHERE `ost_osr_id_ref` = ".$_POST['consumer-user-id-'.$_GET['id']];
			$token = $wpdb->get_var ($sql);
			$store->deleteConsumerAccessToken ( $token, $_POST['consumer-user-id-'.$_GET['id']], true );
			wpr_add_message("The consumer will have to reauthenticate with the server!");
		}
		
		if($do_edit) {
			$args = array('consumer_key' => $_POST['consumer-token-'.$_GET['id']],'standby' => ((isset($_POST['consumer-standby-'.$_GET['id']])) ? 1 : 0 ),'application_title' => $_POST['consumer-title-'.$_GET['id']],'application_type' => $_POST['consumer-type-'.$_GET['id']],'allowed_calls' => $_POST['consumer-allowed-requests-'.$_GET['id']],'calls' => $_POST['consumer-requests-'.$_GET['id']],'id' => $_GET['id'],'is_admin' => true);
			$store->updateConsumerRegistry($args);
			wpr_add_message("Consumer saved!");
		}
	}
	
?>
	<div class="wrap">
    	<h2>WP-RESTful - Server Configuration &amp; Consumer Management</h2>
		<p>This is where you can manage your own server.</p>
    	<?php if(!empty($message)) : ?>
	    	<?php foreach($message as $message_single) :?>
    			<?php if(!$message_single['error']) :?>
    	<div class="updated fade" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
    			<?php else: ?>	
    	<div class="error" id="message">
    		<p><strong><?php echo $message_single['message'];?></strong></p>
    	</div>
		    	<?php endif;?>
    		<?php endforeach;?>
    	<?php endif; ?>
    	<!-- CONTENT HERE -->
    	<div class="wrap" style="margin-top:50px;">
    	<h2>Options:</h2>
    	<div style="margin:10px 0 40px;">
			<form action="" method="post">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row"><?php echo __('Server active')?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><span><?php echo __('API Server status')?></span></legend>
										<label for="server_active_on"><input type="radio" <?php echo ((get_option('wpr_server_active')) ? 'checked="checked"' : '' )?> value="1" id="server_active_on" name="server_active"> ON</label><br>
										<label for="server_active_off"><input type="radio" <?php echo ((!get_option('wpr_server_active')) ? 'checked="checked"' : '' )?> value="0" id="server_active_off" name="server_active"> OFF</label><br>
										<span style="display: block;" class="description">Activate or deactivate your API Server.</span>
									</fieldset>
								</td>
							</th>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo __('Allowed Return Types')?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><span><?php echo __('Allowed Return Types')?></span></legend>
										<label for="allowed_return_type_xml"><input type="checkbox" <?php echo ((get_option('wpr_allowed_return_type_xml')) ? 'checked="checked"' : '' )?> value="1" id="allowed_return_type_xml" name="allowed_return_type_xml"> XML</label><br>
										<label for="allowed_return_type_json"><input type="checkbox" <?php echo ((get_option('wpr_allowed_return_type_json')) ? 'checked="checked"' : '' )?> value="1" id="allowed_return_type_json" name="allowed_return_type_json"> JSON</label><br>
										<span style="display: block;" class="description">Return types supported are XML and JSON.</span>
									</fieldset>
								</td>
							</th>
						</tr>
						<tr valign="top">
							<th scope="row"><?php echo __('Default Consumer Allowed Requests')?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><span><?php echo __('Default Consumer Allowed Requests')?></span></legend>
										<label for="consumer_allowed_requests"><input type="text" value="<?php echo ((get_option('wpr_consumer_allowed_requests') >= 0) ? get_option('wpr_consumer_allowed_requests') : '250' )?>" style="width: 90px;text-align:right;" id="consumer_allowed_requests" name="consumer_allowed_requests"> per hour</label><br>
										<span style="display: block;" class="description">Amount of requests that a consumer can perform before being negated access.</span>
									</fieldset>
								</td>
							</th>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" name="save_options" value="Save Options &uarr;">
				</p>
			</form>
		</div>
    	<h2>Allowed information:</h2>
    	<div style="margin:10px 0 40px;">
			<form action="" method="post">
				<table class="form-table">
					<tbody>
						<?php 
							$requires_auth = get_option('wpr_requires_auth');
							if(empty($requires_auth))
								$requires_auth = array();
						?>
						<?php foreach($wpr['fields'] as $field_title => $field_array) : ?>
						<?php $allowed_requests_array = explode(",",get_option('wpr_'.$field_title.'_allowed_requests'));?>
						<tr valign="top">
							<th scope="row">
								<?php echo $field_title?>
								<br><label for="<?php echo $field_title?>_requires_auth" style="font-size:11px;"><input type="checkbox" name="<?php echo $field_title?>_requires_auth" id="<?php echo $field_title?>_requires_auth" value="1" <?php echo ((in_array($field_title,$requires_auth)) ? 'checked="checked"' : '' )?>> Requires Authentication</label>
							</th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><span><?php echo __($field_title.' Allowed Return Fields')?></span></legend>
										<?php foreach($field_array as $field_name => $field_preety_name) :?>
										<label for="<?php echo $field_title?>_<?php echo $field_name?>">
											<input type="checkbox" <?php echo ((in_array($field_name,$allowed_requests_array)) ? 'checked="checked"' : '' )?> value="1" id="<?php echo $field_title?>_<?php echo $field_name?>" name="<?php echo $field_title?>_<?php echo $field_name?>"> <?php echo $field_preety_name?>
										</label><br>
										<?php endforeach;?>
										<span style="display: block;" class="description">These are the fields, belonging to <?php echo strtolower($field_title)?>, that you allow to be fetched through the API.</span>
									</fieldset>
								</td>
							</th>
						</tr>
						<?php endforeach;?>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" name="save_allowed_information" value="Save Options &uarr;">
				</p>
			</form>
		</div>
    	<h2>Registered Consumers:</h2>
    	<div style="margin:10px 0 40px;">
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
						<label for="new_role" class="screen-reader-text">Change role to...</label><select id="new_role" name="new_role"><option value="">Change role to...</option>
							<option value="administrator">Administrator</option>
							<option value="editor">Editor</option>
							<option value="author">Author</option>
							<option value="contributor">Contributor</option>
							<option value="subscriber">Subscriber</option></select>
						<input type="submit" class="button-secondary" name="changeit" value="Change">
						<input type="hidden" value="f3304501ad" name="_wpnonce" id="_wpnonce"><input type="hidden" value="/wp-debug/wp-admin/users.php" name="_wp_http_referer">
					</div>
					<br class="clear">
				</div>
				
					<table cellspacing="0" class="widefat fixed">
						<thead>
							<tr class="thead">
								<th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox"></th>
								<th style="" class="manage-column column-username" id="username" scope="col">Username</th>
								<th style="" class="manage-column column-title" id="title" scope="col">Title</th>
								<th style="" class="manage-column column-email" id="email" scope="col">E-mail</th>
								<th style="" class="manage-column column-type" id="type" scope="col">Type</th>
								<th style="" class="manage-column column-added" id="added" scope="col">Added</th>
								<th style="" class="manage-column column-added" id="requests" scope="col">Requests</th>
							</tr>
						</thead>
						
						<tfoot>
							<tr class="thead">
								<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
								<th style="" class="manage-column column-username" scope="col">Username</th>
								<th style="" class="manage-column column-title" scope="col">Title</th>
								<th style="" class="manage-column column-email" scope="col">E-mail</th>
								<th style="" class="manage-column column-type" scope="col">Type</th>
								<th style="" class="manage-column column-added" scope="col">Added</th>
								<th style="" class="manage-column column-requests" scope="col">Requests</th>
							</tr>
						</tfoot>
						
						<tbody class="list:user consumers-list" id="consumers">
							<?php 
								$sql = "SELECT * FROM ".WPR_DB_TABLE_OAUTH_SERVER_REGISTRY." WHERE `osr_id` > 0 AND `osr_status` LIKE 'active' AND `osr_enabled` = 1";
								$registers = $wpdb->get_results ($sql);
								foreach($registers as $register) :
									$user = get_userdata($register->osr_usa_id_ref);
									if(isset($user->ID)):
							?>
							<tr class="alternate" id="consumer-<?php echo $user->ID?>">
								<th class="check-column" scope="row">
									<input type="checkbox" value="<?php echo $user->ID?>" class="administrator" id="user_<?php echo $user->ID?>" name="users[]">
								</th>
								<td class="username column-username">
									<?php echo get_avatar( $user->user_email, $size = '36')?>
									<strong>
										<a href="user-edit.php?user_id=<?php echo $user->ID?>"><?php echo $user->display_name?></a>
									</strong>
									<div class="row-actions">
										<span class="edit"><a style="cursor:pointer;" onclick="jQuery('#edit-<?php echo $register->osr_id?>').fadeIn('fast')" class="editbutton-<?php echo $register->osr_id?>">Edit</a></span>
									</div>
								</td>
								<td class="title column-title"><?php echo $register->osr_application_title?></td>
								<td class="email column-email">
									<a title="e-mail: <?php echo $user->user_email?>" href="mailto:<?php echo $user->user_email?>"><?php echo $user->user_email?></a>
								</td>
								<td class="type column-type"><?php echo $register->osr_application_type?></td>
								<td class="added column-added"><?php echo $register->osr_issue_date?></td>
								<td class="added column-requests"><span style="font-family:Georgia;font-size:20px"><b><?php echo $register->osr_calls?></b>/<?php echo $register->osr_allowed_calls?></span></td>
							</tr>
							<tr style="display: none;" id="edit-<?php echo $register->osr_id?>">
								<td colspan="7">
									<form action="admin.php?page=wpr_admin_server&id=<?php echo $register->osr_id?>" method="post">
									<div style="margin-bottom:15px;" id="replyhead">Edit Consumer <?php echo $user->display_name?></div>
									<input type="hidden" name="consumer-user-id-<?php echo $register->osr_id?>" value="<?php echo $register->osr_usa_id_ref?>" />
									<table cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-title-<?php echo $register->osr_id?>">Title</label>
												<input type="text" id="consumer-title-<?php echo $register->osr_id?>" value="<?php echo $register->osr_application_title?>" size="50" name="consumer-title-<?php echo $register->osr_id?>">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-type-<?php echo $register->osr_id?>">Type</label>
												<select id="consumer-type-<?php echo $register->osr_id?>" name="consumer-type-<?php echo $register->osr_id?>">
													<option value="blog" <?php echo (($register->osr_application_type == "blog") ? 'selected="selected"' : '')?>>blog</option>
													<option value="application" <?php echo (($register->osr_application_type == "application") ? 'selected="selected"' : '')?>>application</option>
													<option value="website" <?php echo (($register->osr_application_type == "website") ? 'selected="selected"' : '')?>>website</option>
												</select>
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-requests-<?php echo $register->osr_id?>">Requests</label>
												<input type="text" id="consumer-requests-<?php echo $register->osr_id?>" value="<?php echo $register->osr_calls?>" name="consumer-requests-<?php echo $register->osr_id?>" style="width: 60px;text-align:right">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-allowed-requests-<?php echo $register->osr_id?>">Allowed Requests</label>
												<input type="text" id="consumer-allowed-requests-<?php echo $register->osr_id?>" value="<?php echo $register->osr_allowed_calls?>" name="consumer-allowed-requests-<?php echo $register->osr_id?>" style="width: 60px;text-align:right">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-token-<?php echo $register->osr_id?>">Consumer Token</label>
												<input type="text" id="consumer-token-<?php echo $register->osr_id?>" value="<?php echo $register->osr_consumer_key?>" name="consumer-token-<?php echo $register->osr_id?>" style="width: 310px;">
												</div>
											</td>
										</tr>
										<tr style="margin-top:20px">
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-remove-<?php echo $register->osr_id?>">Remove?</label>
												<input type="checkbox" id="consumer-remove-<?php echo $register->osr_id?>" value="1" name="consumer-remove-<?php echo $register->osr_id?>">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-ban-<?php echo $register->osr_id?>">Ban?</label>
												<input type="checkbox" id="consumer-ban-<?php echo $register->osr_id?>" value="1" name="consumer-ban-<?php echo $register->osr_id?>">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-reauth-<?php echo $register->osr_id?>">Force Re-Auth?</label>
												<input type="checkbox" id="consumer-reauth-<?php echo $register->osr_id?>" value="1" name="consumer-reauth-<?php echo $register->osr_id?>">
												</div>
											</td>
										</tr>
										<tr>
											<td style="border-bottom:0 none;">
										
												<div class="inside">
												<label for="consumer-standby-<?php echo $register->osr_id?>">Standby?</label>
												<input type="checkbox" id="consumer-standby-<?php echo $register->osr_id?>" value="1" <?php echo (($register->osr_standby) ? 'checked="checked"' : '' )?> name="consumer-standby-<?php echo $register->osr_id?>">
												</div>
											</td>
										</tr>
									</table>
									<div style="clear: both;"></div>
									
																
									<p class="submit" id="replysubmit" style="margin-top:15px;">
									<a class="cancel button-secondary alignleft" onclick="jQuery('#edit-<?php echo $register->osr_id?>').fadeOut('fast')">Cancel</a>
									<input type="submit" class="save button-primary alignright" value="Save">
									<img alt="" src="images/wpspin_light.gif" style="display: none;" class="waiting">
									<span style="display: none;" class="error"></span>
									<br class="clear">
									</p>
									</form>
								</td>
							</tr>
							<?php endif;endforeach;?>
						</tbody>
					</table>
				<div class="tablenav">
					<div class="alignleft actions">
						<select name="action2">
							<option selected="selected" value="">Bulk Actions</option>
							<option value="delete">Delete</option>
						</select>
						<input type="submit" class="button-secondary action" id="doaction2" name="doaction2" value="Apply">
					</div>
					<br class="clear">
				</div>
		</div>
    </div>
<?php
}

//========================================
// Render User Pages / Return Requests
//========================================
function wpr_return() {
	global $wpdb,$wpr,$wp_query;

	if(isset($wpr['request']) && in_array($wpr['request'],$wpr['reserved_requests']))
		$wpr['call'] = $wpr['request'];
	else
		$wpr['call'] = "";
	//print_r($wpr);
	require_once 'lib/REST.inc.php';
	$WPREST = new WPREST ();
}

//========================================
// Check URL query to see if we need to jump straight to API Core or not
//========================================
function wpr_check_query($wp_query) {
	global $wpr;
	if(isset($wp_query->query_vars['request']))
		$wpr['request'] = $wp_query->query_vars['request'];
	else
		$wpr['request'] = "";	
	if(!empty($wpr['request']) && !in_array($wpr['request'],$wpr['reserved_requests_with_template'])) {
		wpr_return();
		exit();
	}
}

//========================================
// Remember to flush_rules() when adding rules
//========================================
function flushRules(){
	global $wp_rewrite;
   	$wp_rewrite->flush_rules();
}

//========================================
// Adding a new rule to WordPress rewrite system
//========================================
function insert_rewrite_rule($rules)
{
	$newrules = array();
	$newrules['(api)/request-token(.*)$'] = 'index.php?pagename=$matches[1]&request=request-token';
	$newrules['(api)/auth(.*)$'] = 'index.php?pagename=$matches[1]&request=auth';
	$newrules['(api)/access-token(.*)$'] = 'index.php?pagename=$matches[1]&request=access-token';
	$newrules['(api)/register(.*)$'] = 'index.php?pagename=$matches[1]&request=register';
	$newrules['(api)/(.*)$'] = 'index.php?pagename=$matches[1]&request=$matches[2]';
	return $newrules + $rules;
}

//========================================
// Ensure this plugin always loads before the plugins (wpr plugins at least)
//========================================
function wpr_load_first() {
	// ensure path to this file is via main wp plugin path
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);
	if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
		array_splice($active_plugins, $this_plugin_key, 1);
		array_unshift($active_plugins, $this_plugin);
		update_option('active_plugins', $active_plugins);
	}
}

//========================================
// Adding the request var so that WP recognizes it
//========================================
function add_query_var($vars)
{
	global $wpr,$wp_query;
    array_push($vars, 'request');
    return $vars;
}

//========================================
// Enable sessions on admin pages
//========================================
function wpr_start_sessions() {
	@session_start();
}

//========================================
// Add scripts to admin header
//========================================
function wpr_add_js() {
	wp_enqueue_script('wpr_main_js', WPR_SCRIPT_MAIN, array('jquery'), '1.0.0' );
}

//========================================
// Create left admin menu
//========================================
function wpr_menu(){
   add_menu_page ('WP-RESTful', 'WP-RESTful' , 8 , WPR_PLUGIN_FOLDER_PATH.WPR_PLUGIN_FOLDER_NAME , 'wpr_admin_main' );
   add_submenu_page (WPR_PLUGIN_FOLDER_PATH.WPR_PLUGIN_FOLDER_NAME, 'Server', 'Server', 8 , 'wpr_admin_server', 'wpr_admin_server' );
   add_submenu_page (WPR_PLUGIN_FOLDER_PATH.WPR_PLUGIN_FOLDER_NAME, 'Client', 'Client', 8 , 'wpr_admin_client', 'wpr_admin_client' );
}

//========================================
// Create shortcodes
//========================================
add_shortcode('REST_return','wpr_return');

//========================================
// Add Filters
//========================================
add_filter('rewrite_rules_array','insert_rewrite_rule');
add_filter('query_vars','add_query_var');
add_filter('init','flushRules');
add_filter('admin_print_scripts', 'wpr_add_js');

//========================================
// Add Actions
//========================================
add_action('admin_init', 'wpr_start_sessions');
add_action('admin_menu','wpr_menu',1);
add_action("parse_query","wpr_check_query");
add_action("activated_plugin", "wpr_load_first");
?>