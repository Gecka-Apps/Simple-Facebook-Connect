<?php

// if you want people to be unable to disconnect their WP and FB accounts, set this to false in wp-config
if (!defined('SFC_ALLOW_DISCONNECT'))
	define('SFC_ALLOW_DISCONNECT',true);

// fix the reauth redirect problem
add_action('login_form_login','sfc_login_reauth_disable');
function sfc_login_reauth_disable() {
	$_REQUEST['reauth'] = false;
}

// add the section on the user profile page
add_action('profile_personal_options','sfc_login_profile_page');

function sfc_login_profile_page($profile) {
	$options = get_option('sfc_options');
?>
	<table class="form-table">
		<tr>
			<th><label><?php _e('Facebook Connect', 'sfc'); ?></label></th>
<?php
	$fbuid = get_user_meta($profile->ID, 'fbuid', true);
	if (empty($fbuid)) {
		?>
			<td><p><fb:login-button perms="email" v="2" size="large" onlogin="sfc_login_update_fbuid(0);"><fb:intl><?php _e('Connect this WordPress account to Facebook', 'sfc'); ?></fb:intl></fb:login-button></p></td>
		</tr>
	</table>
	<?php
	} else { ?>
		<td><p><?php _e('Connected as', 'sfc'); ?>
		<fb:profile-pic size="square" width="32" height="32" uid="<?php echo $fbuid; ?>" linked="true"></fb:profile-pic>
		<fb:name useyou="false" uid="<?php echo $fbuid; ?>"></fb:name>.
<?php if (SFC_ALLOW_DISCONNECT) { ?>
		<input type="button" class="button-primary" value="<?php _e('Disconnect this account from WordPress', 'sfc'); ?>" onclick="sfc_login_update_fbuid(1); return false;" />
<?php } ?>
		</p></td>
	<?php } ?>
	</tr>
	</table>
	<?php
}

add_action('admin_footer','sfc_login_update_js',30);
function sfc_login_update_js() {
	if (IS_PROFILE_PAGE) {
		?>
		<script type="text/javascript">
		function sfc_login_update_fbuid(disconnect) {
			var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
			if (disconnect == 1) {
				var fbuid = 0;
			} else {
				var fbuid = 1; // it gets it from the cookie
			}
			var data = {
				action: 'update_fbuid',
				fbuid: fbuid
			}
			jQuery.post(ajax_url, data, function(response) {
				if (response == '1') {
					location.reload(true);
				}
			});
		}
		</script>
		<?php
	}
}

add_action('wp_ajax_update_fbuid', 'sfc_login_ajax_update_fbuid');
function sfc_login_ajax_update_fbuid() {
	$options = get_option('sfc_options');
	$user = wp_get_current_user();

	$fbuid = (int)($_POST['fbuid']);

	if ($fbuid) {
		// get the id from the cookie
		$cookie = sfc_cookie_parse();
		if (empty($cookie)) { echo 1; exit; }
		$fbuid = $cookie['uid'];
	} else {
		if (!SFC_ALLOW_DISCONNECT) { echo 1; exit(); }
		$fbuid = 0;
	}

	update_usermeta($user->ID, 'fbuid', $fbuid);
	echo 1;
	exit();
}

add_action('login_form','sfc_login_add_login_button');
function sfc_login_add_login_button() {
	global $action;
	if ($action == 'login') echo '<p><fb:login-button v="2" perms="email,user_website" onlogin="window.location.reload();" /></p><br />';
}

// add the fb icon to the admin bar, showing you're connected via FB login
add_filter('admin_user_info_links','sfc_login_admin_header');
function sfc_login_admin_header($links) {
	$user = wp_get_current_user();
	$fbuid = get_user_meta($user->ID, 'fbuid', true);
	$icon = plugins_url('/images/fb-icon.png', __FILE__);
	if ($fbuid) $links[6]="<a href='http://www.facebook.com/profile.php?id=$fbuid'><img src='$icon' /> Facebook</a>";
	return $links;
}

// do the actual authentication
//
// note: Because of the way auth works in WP, sometimes you may appear to login
// with an incorrect username and password. This is because FB authentication
// worked even though normal auth didn't.
add_filter('authenticate','sfc_login_check',90);
function sfc_login_check($user) {
	if ( is_a($user, 'WP_User') ) { return $user; } // check if user is already logged in, skip FB stuff

	// check for the valid cookie
	$cookie = sfc_cookie_parse();
	if (empty($cookie)) return $user;

	// the cookie is signed using our secret, so if we get it back from sfc_cookie_parse, then it's authenticated. So just log the user in.
	$fbuid=$cookie['uid'];
	if($fbuid) {
		global $wpdb;
		$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND meta_value = %s", $fbuid) );

		if ($user_id) {
			$user = new WP_User($user_id);
		} else {
			$data = sfc_remote($fbuid, '', array(
				'fields'=>'email',
				'access_token'=>$cookie['access_token'],
			));

			if (!empty($data['email'])) {
				$user_id = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_email = %s", $data['email']) );
			}

			if ($user_id) {
				$user = new WP_User($user_id);
				update_usermeta($user->ID, 'fbuid', $fbuid); // connect the account so we don't have to query this again
			}

			if (!$user_id) {
				do_action('sfc_login_new_fb_user'); // TODO hook for creating new users if desired
				global $error;
				$error = '<strong>'.__('ERROR', 'sfc').'</strong>: '.__('Cannot log you in. There is no account on this site connected to that Facebook user identity.', 'sfc');
			}
		}
	}

	return $user;
}

// we have to change the logout to use a javascript redirect. No other way to make FB log out properly and stop giving us the cookie.
add_action('wp_logout','sfc_login_logout');
function sfc_login_logout() {
	// check for FB cookies, if not found, do nothing
	$cookie = sfc_cookie_parse();
	if (empty($cookie)) return;
	
	// we have an FB login, log them out with a redirect
	add_action('sfc_async_init','sfc_login_logout_js');
?>
	<html><head></head><body>
	<?php sfc_add_base_js(); ?>
	</body></html>
<?php
exit;
}

// add logout code to async init
function sfc_login_logout_js() {
	$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : 'wp-login.php?loggedout=true';
?>
FB.logout(function(response) {
	window.location.href = '<?php echo $redirect_to; ?>';
});
<?php
}

add_action('login_footer','sfc_add_base_js',20);

/*
// generate facebook avatar code for users who login with Facebook
// NOTE: This overrides Gravatar if you use it.
//
add_filter('get_avatar','sfc_login_avatar', 10, 5);
function sfc_login_avatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
	// check to be sure this is for a user id
	if ( !is_numeric($id_or_email) ) return $avatar;
	$fbuid = get_user_meta( $id_or_email, 'fbuid', true);
	if ($fbuid) {
		// return the avatar code
		return "<img width='{$size}' height='{$size}' class='avatar avatar-{$size} fbavatar' src='http://graph.facebook.com/{$fbuid}/picture?type=square' />";
	}
	return $avatar;
}
*/
