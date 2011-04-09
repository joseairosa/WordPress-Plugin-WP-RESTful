function wpr_check_register_step_1(id) {
	var consumer_key = jQuery("#consumer-stage1-key-"+id);
	var consumer_secret = jQuery("#consumer-stage1-secret-"+id);
	if(consumer_key.val() != "" && consumer_secret.val() != "") {
		jQuery("#register-"+id).hide();
		jQuery("#save-continue-stage1-"+id).show();
	} else {
		jQuery("#register-"+id).show();
		jQuery("#save-continue-stage1-"+id).hide();
	}
}

jQuery(document).ready(function(){
	
});