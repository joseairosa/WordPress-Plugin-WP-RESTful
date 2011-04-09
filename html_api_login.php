<h2>FLOCKS LOGIN</h2>
<p>You need to login in order to request access to our API.</p>
<div style="margin-top: 40px;">
<form action="http://login.flocks.biz/" method="post">
	<fieldset>
		<input type="hidden" name="api" value="1" />
		<input type="hidden" name="oauth_token" value="<?php echo @$_GET['oauth_token_temp']?>" />
		<input type="hidden" name="oauth_callback" value="<?php echo @$_GET['oauth_callback_temp']?>" />
		<p>
			<label>E-Mail</label>              
				<input class="text-input large-input" type="text" style="width: 200px;" id="login_email" name="login_email" value="" />
				<small>The e-mail whith which you registred with Flocks.</small>
		</p>
		<p>
			<label>Password</label>              
				<input class="text-input large-input" type="password" style="width: 200px;height: 20px; line-height: 20px;" id="login_password" name="login_password" value="" />
				<small>Your password.</small>
		</p>
		<input type="submit" value="" name="login_submit" class="submit"/>
	</fieldset>
</form>
</div>