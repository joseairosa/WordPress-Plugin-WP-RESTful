<?php
class StatusRESTController extends WPAPIRESTController {
	protected $statuses_array;

	protected function __construct() {
		$this->statuses_array = array(
				'api-status' => ((get_option('wpr_server_active')) ? 'Running' : 'Not Running' ), 
				'return-types' => array(
					'XML' => ((get_option('wpr_allowed_return_type_xml')) ? 'Yes' : 'No' ), 
					'JSON' => ((get_option('wpr_allowed_return_type_json')) ? 'Yes' : 'No' )
					)
				);	
	}
	
	protected function getStatus($status = null) {
		if(!isset($_POST['status_index']) || empty($_POST['status_index'])) {
			return $this->statuses_array;
		} else {
			if(isset($this->statuses_array[$_POST['status_index']])) {
				if(!is_array($this->statuses_array[$_POST['status_index']]) && !is_object($this->statuses_array[$_POST['status_index']]))
					return array($this->statuses_array[$_POST['status_index']]);
				else
					return $this->statuses_array[$_POST['status_index']];
			} else
				return $this->statuses_array;
		}
	}
}
?>