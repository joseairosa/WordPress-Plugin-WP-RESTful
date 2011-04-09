<?php
class PostRESTController extends WPAPIRESTController {
	protected function __construct() {}
	
	protected function getPosts() {
		global $wpdb;
		// Get all posts
		return $this->_return($wpdb->get_results("SELECT * FROM ".$wpdb->posts." WHERE ID > 0 AND post_type LIKE 'post'"));
	}
	
	protected function getPost($post) {
		// Get requested posts
		return $this->_return(get_post($post));
	}
	
	/**
	 * Apply request filter
	 * 
	 * @since 0.1
	 * 
	 * @return (mixed) filtered content
	 */
	private function _return($content) {
		return wpr_filter_content($content,wpr_get_filter("Posts"));
	}
}
?>