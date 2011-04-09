<?php
class wpr_widget_posts {
	
	function activate() {
		// Instructions to run when the plugin is activated
		$data = array( 'return_type' => 'xml', 'max_posts' => 3);
	    if ( ! get_option('wpr_widget_posts')){
	      add_option('wpr_widget_posts' , $data);
	    } else {
	      update_option('wpr_widget_posts' , $data);
	    }
	}
	
	function deactivate() {
		// Instructions to run when the plugin is activated
		delete_option('wpr_widget_posts');
	}
	
	function control() {
		global $wpdb;
		require_once check_for_trailing_slash(dirname(__FILE__)).'lib/OAuthStore.php';
		// Init the database connection
		$store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
		$servers = $store->listServerTokens();
		
		$data = get_option('wpr_widget_posts'); ?>
		<p>
			<label>Return Type 
				<select name="wpr_widget_posts_return_type" id="wpr_widget_posts_return_type">
					<option value="xml" <?php echo ($data ['return_type'] == "xml" ? 'selected="selected"' : '' )?>>XML</option>
					<option value="json" <?php echo ($data ['return_type'] == "json" ? 'selected="selected"' : '' )?>>JSON</option>
				</select>
			</label>
		</p>
		<p>
			<label>Max Posts
				<input style="width: 35px; text-align: right;" type="text" name="wpr_widget_posts_max" id="wpr_widget_posts_max" value="<?php echo $data ['max_posts'] ?>" />
			</label>
		</p>
		<p>
			<label>Used API Servers
				<select multiple="multiple" size="5" name="wpr_widget_posts_server[]" style="height: 100px;overflow: auto;">
					<?php 
						foreach($servers as $server): 
						$server_url = str_replace(array("/api"),array(""),$server['server_uri']);
					?>
						<option <?php echo ((!isset($data ['servers']) || !is_array($data ['servers'])) ? '' : ((in_array($server ['consumer_key'],$data ['servers'])) ? 'selected="selected"' : '' ) )?> value="<?php echo $server ['consumer_key']?>"><?php echo $server_url?></option>
					<?php endforeach;?>
				</select>
			</label>
		</p>
		<?php
		if (isset ( $_POST ['wpr_widget_posts_return_type'] )) {
			$data ['return_type'] = attribute_escape ( $_POST ['wpr_widget_posts_return_type'] );
			$data ['max_posts'] = attribute_escape ( $_POST ['wpr_widget_posts_max'] );
			$data ['servers'] = $_POST ['wpr_widget_posts_server'];
			update_option ( 'wpr_widget_posts', $data );
		}
	}
	
	function widget($args) {
		global $wpdb,$wp_query;
		$reserved_requests = array("request-token","auth","access-token","register");
		
		if(!isset($wp_query->query_vars['request']))
			$wp_query->query_vars['request'] = "";
			
		if(!in_array($wp_query->query_vars['request'],$reserved_requests)) {
			$data = get_option('wpr_widget_posts');
			echo $args ['before_widget'];
			echo $args ['before_title'] . 'Network Posts' . $args ['after_title'];
			require_once check_for_trailing_slash(dirname(__FILE__)).'lib/OAuthStore.php';
			require_once check_for_trailing_slash(dirname(__FILE__)).'lib/consumer/WP-API.php';
			require_once check_for_trailing_slash(dirname(__FILE__)).'lib/consumer/OAuth.php';
			require_once check_for_trailing_slash(dirname(__FILE__)).'lib/jsonwrapper/jsonwrapper.php';
			// Init the database connection
			$store = OAuthStore::instance ( 'MySQL', array ('conn' => $wpdb->dbh ) );
			$servers = $store->listServerTokens();
			
			echo '<div id="wpr-posts-wrapper">';
			
			foreach($servers as $server) {
				if(in_array($server ['consumer_key'],$data ['servers'])) {
					$server_url = str_replace(array("/api"),array(""),$server['server_uri']);
					$response = "";
					$to = new WPOAuth ( $server ['consumer_key'], $server ['consumer_secret'], $server ['token'], $server ['token_secret'], $server ['server_uri'] );
	
					if($data ['return_type'] == "json") {
						$response = json_decode($to->OAuthRequest ( $to->TO_API_ROOT.'posts.json', array (), 'POST' ));
					} elseif($data ['return_type'] == "xml") {
						$response = json_decode($to->OAuthRequest ( $to->TO_API_ROOT.'posts.xml', array (), 'POST' ));
					}
					
					if(is_array($response)) {
						$response = array_reverse($response);
						
						if(count($response) > 0) {
							echo '<p>'.$server_url.'</p>';
							$x = 1;
							echo '<ul>';
							foreach($response as $single_post) {
								if($x <= $data['max_posts']) {
									echo '<li><a href="'.$single_post->guid.'">'.$single_post->post_title.'</a></li>';
								}
								$x++;
							}
							echo '</ul>';
						}
					}
				}
			}
			
			echo '</div>';
			
			
			echo $args ['after_widget'];
		}
	}
	
	function register() {
		register_sidebar_widget ( 'Get API Posts', array ('wpr_widget_posts', 'widget' ) );
		register_widget_control ( 'Get API Posts', array ('wpr_widget_posts', 'control' ) );
	}
}

//========================================
// Register Widgets
//========================================
add_action ( "widgets_init", array ('wpr_widget_posts', 'register' ) );
register_activation_hook ( __FILE__, array ('wpr_widget_posts', 'activate' ) );
register_deactivation_hook ( __FILE__, array ('wpr_widget_posts', 'deactivate' ) );
?>