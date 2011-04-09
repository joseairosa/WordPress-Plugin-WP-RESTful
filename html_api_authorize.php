<h2>WP-RESTful API AUTHORIZE</h2>
<p>Do you give permission to <?php echo ((isset($_SESSION['verify_oauth_app_title']) && !empty($_SESSION['verify_oauth_app_title'])) ? '<a href="'.$_SESSION['verify_oauth_app_url'].'">'.$_SESSION['verify_oauth_app_title'].'</a>' : urldecode($_GET['oauth_callback']) )?>.</p>
<div style="margin-top: 40px;">
<form action="" name="auth_form" method="post">
	<input type="submit" name="allow" value="Allow" />
	<input type="submit" name="deny" value="Deny" />
</form>
</div>