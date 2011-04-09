<h2>WP-RESTful REQUEST API CREDENTIALS</h2>
<p>In order to use our API you need to register your application on our server.<br/>Some of the methods require authentication, more specifically, <a href="http://www.oauth.com" target="_blank">OAuth</a>.<br/><u>Note:</u> None of the fields below are mandatory, but we recommend that you do.</p>
<div style="margin-top: 40px;">
<form action="" method="post" style="text-align: left;">
	<?php if(isset($consumer['consumer_key']) && isset($consumer['consumer_secret'])) : ?>
	<p>
		<label>Consumer Token</label>              
		<span style="font-size:18px; color: #999"><?php echo $consumer['consumer_key']?></span>
	</p>
	<p>
		<label>Consumer Secret Token</label>              
		<span style="font-size:18px; color: #999"><?php echo $consumer['consumer_secret']?></span>
	</p>
	<?php else: ?>
		<p>
			<label>Callback URL</label>              
				<input class="text-input large-input" type="text" style="width: 200px;" id="callback_uri" name="callback_uri" value="" />
				<small>The URL to be called after we process your request. (Example, http://www.example.com/api/success)</small>
		</p>
		<p>
			<label>Application URL</label>              
				<input class="text-input large-input" type="text" style="width: 200px;" id="application_uri" name="application_uri" value="" />
				<small>The URL to your application website. It's always good to provide this.</small>
		</p>
		<p>
			<label>Application Title</label>              
				<input class="text-input large-input" type="text" style="width: 200px;" id="application_title" name="application_title" value="" />
				<small>The name we should give to your application. (Example, "My first Flocks app")</small>
		</p>
		<p style="margin-top: 20px;">
			<label>Application Description</label>
			<textarea class="text-input textarea wysiwyg" id="textarea" name="application_descr" cols="70" rows="15"></textarea>
			<small>Tell us your app or website does or is meant for.</small>
		</p>
		<p>
			<label>Application Type</label>              
			<select class="small-input" name="application_type">
				<option selected="selected" value="website">Website</option>
				<option value="iphone">iPhone Application</option>
				<option value="mobile">Window Mobile Application</option>
			</select> 
			<br/><small>What kind of application are you building?</small>
		</p>
		<p>
			<label>Is your application for commercial use?</label>              
			<select class="small-input" name="application_commercial">
				<option selected="selected" value="0">No</option>
				<option value="1">Yes</option>
			</select> 
			<br/>
		</p>
		<input type="submit" value="Finish" name="submit_application" class="submit"/>
	<?php endif;?>
</form>
</div>