<?php
class CommentRESTController extends WPAPIRESTController {
	protected function __construct() {}
	
	protected function getComments() {
		global $wpdb;
		$array = array();
		wpr_set_defaults($_POST,array('post_id'=>false,'comment_id'=>false));
		// Get all comments
		if($_POST['post_id'])
			$comments = $wpdb->get_results($wpdb->prepare( "SELECT * FROM ".$wpdb->comments." WHERE comment_post_ID = %d AND comment_approved = 1 ORDER BY comment_date ASC",$_POST['post_id']));
		elseif($_POST['comment_id']) 
			$comments = $wpdb->get_results($wpdb->prepare( "SELECT * FROM ".$wpdb->comments." WHERE comment_ID = %d AND comment_approved = 1 ORDER BY comment_date ASC",$_POST['comment_id']));
		else
			$comments = $wpdb->get_results("SELECT * FROM ".$wpdb->comments." WHERE comment_ID > 0 AND comment_approved = 1 ORDER BY comment_date ASC");
			
		if(count($comments) == 1) {
			return $this->_return($comments[0]);
		}
		foreach($comments as $comment) {
			$array[] = $comment;
		}
		return $this->_return($array);
	}
	
	protected function getComment($post) {
		// Get requested posts
		return $this->_return(get_comment($post));
	}
	
	protected function add_comment($commentdata) {
		$comment_id = wp_insert_comment($commentdata);
		if($comment_id > 0)
			return array('success' => 'Comment added with ID: '.$comment_id);
		else
			return new WP_Error('error', __('Error adding your comment.'));
	}
	
	/**
	 * Apply request filter
	 * 
	 * @since 0.1
	 * 
	 * @return (mixed) filtered content
	 */
	private function _return($content) {
		return wpr_filter_content($content,wpr_get_filter("Comments"));
	}
}
?>