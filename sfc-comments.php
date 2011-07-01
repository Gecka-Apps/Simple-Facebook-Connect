<?php

// force load jQuery (we need it later anyway)
add_action('wp_enqueue_scripts','sfc_comm_jquery');
function sfc_comm_jquery() {
	wp_enqueue_script('jquery');
}

// set a variable to know when we are showing comments (no point in adding js to other pages)
add_action('comment_form','sfc_comm_comments_enable');
function sfc_comm_comments_enable() {
	global $sfc_comm_comments_form;
	$sfc_comm_comments_form = true;
}

// add placeholder for sending comment to Facebook checkbox
add_action('comment_form','sfc_comm_send_place');
function sfc_comm_send_place() {
	?><p id="sfc_comm_send"></p><?php
}

// I wish wp_trim_excerpt was easier to use separately...
function sfc_comm_make_excerpt($text) {
	$text = strip_shortcodes( $text );
	remove_filter( 'the_content', 'wptexturize' );
	$text = apply_filters('the_content', $text);
	add_filter( 'the_content', 'wptexturize' );
	$text = str_replace(']]>', ']]&gt;', $text);
	$text = wp_strip_all_tags($text);
	$text = str_replace(array("\r\n","\r","\n"),' ',$text);
	$excerpt_length = apply_filters('excerpt_length', 55);
	$excerpt_more = apply_filters('excerpt_more', '[...]');
	$words = explode(' ', $text, $excerpt_length + 1);
	if (count($words) > $excerpt_length) {
		array_pop($words);
		array_push($words, $excerpt_more);
		$text = implode(' ', $words);
	}
	return $text;
}

// this bit is to allow the user to add the relevant comments login button to the comments form easily
// user need only stick a do_action('alt_comment_login'); wherever he wants the button to display
add_action('alt_comment_login','sfc_comm_login_button');
add_action('comment_form_before_fields', 'sfc_comm_login_button',10,0); // WP 3.0 support

function sfc_comm_login_button() {
	echo '<p><fb:login-button v="2" perms="email,user_website,publish_stream" onlogin="sfc_update_user_details();"><fb:intl>'.__('Connect with Facebook', 'sfc').'</fb:intl></fb:login-button></p>';
}

// this exists so that other plugins (simple twitter connect) can hook into the same place to add their login buttons
if (!function_exists('alt_login_method_div')) {

add_action('alt_comment_login','alt_login_method_div',5,0);
add_action('comment_form_before_fields', 'alt_login_method_div',5,0); // WP 3.0 support

function alt_login_method_div() { echo '<div id="alt-login-methods">'; }

add_action('alt_comment_login','alt_login_method_div_close',20,0);
add_action('comment_form_before_fields', 'alt_login_method_div_close',20,0); // WP 3.0 support

function alt_login_method_div_close() { echo '</div>'; }

}

// WP 3.0 support
if (!function_exists('comment_user_details_begin')) {

add_action('comment_form_before_fields', 'comment_user_details_begin',1,0);
function comment_user_details_begin() { echo '<div id="comment-user-details">'; }

add_action('comment_form_after_fields', 'comment_user_details_end',20,0);
function comment_user_details_end() { echo '</div>'; }

}

// generate facebook avatar code for FB user comments
add_filter('get_avatar','sfc_comm_avatar', 10, 5);
function sfc_comm_avatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
	// check to be sure this is for a comment
	if ( !is_object($id_or_email) || !isset($id_or_email->comment_ID) || $id_or_email->user_id)
		 return $avatar;

	// check for fbuid comment meta
	$fbuid = get_comment_meta($id_or_email->comment_ID, 'fbuid', true);
	if ($fbuid) {
		// return the avatar code
		return "<img width='{$size}' height='{$size}' class='avatar avatar-{$size} fbavatar' src='http://graph.facebook.com/{$fbuid}/picture?type=square' />";
	}

	return $avatar;
}

// store the FB user ID as comment meta data ('fbuid')
add_action('comment_post','sfc_comm_add_meta', 10, 1);
function sfc_comm_add_meta($comment_id) {
	$cookie = sfc_cookie_parse();
	if (empty($cookie)) return;

	$uid = $cookie['uid'];
	$token = $cookie['access_token'];

	if (!empty($uid)) {
		update_comment_meta($comment_id, 'fbuid', $cookie['uid']);
	}

	// did the user select to share the post on FB?
	if (!empty($_POST['sfc_comm_share']) && !empty($uid) && !empty($token)) {

		$comment = get_comment($comment_id);
		$postid = $comment->comment_post_ID;
		$permalink = get_comment_link($comment_id);
		$attachment['name'] = get_the_title($postid);
		$attachment['link'] = $permalink;
		$attachment['description'] = sfc_comm_make_excerpt($post->post_content);
		$attachment['caption'] = '{*actor*} left a comment on '.get_the_title($postid);
		$attachment['message'] = get_comment_text($comment_id);
		$attachment['comments_xid'] = urlencode($permalink);

		//$action_links[0]['text'] = 'Read Post';
		//$action_links[0]['href'] = get_permalink();

		$url = "https://graph.facebook.com/{$uid}/feed";
		$attachment['access_token'] = $token;

		$data = wp_remote_post($url, array('body'=>http_build_query($attachment)));

		if (!is_wp_error($data)) {
			$resp = json_decode($data['body'],true);
			if ($resp['id']) update_comment_meta($comment_id,'_fb_post_id',$resp['id']);
		}
	}
}

// Add user fields for FB commenters
add_filter('pre_comment_on_post','sfc_comm_fill_in_fields');
function sfc_comm_fill_in_fields($comment_post_ID) {
	if (is_user_logged_in()) return; // do nothing to WP users

	$cookie = sfc_cookie_parse();
	if (empty($cookie)) return;

	$uid = $cookie['uid'];
	$token = $cookie['access_token'];

	if (empty($uid) || empty($token)) return; // need both of these to get the data from FB

	$url = "https://graph.facebook.com/{$uid}/?fields=name,email,website&access_token={$token}";

	$data = wp_remote_get($url);

	if (!is_wp_error($data)) {
		$json = json_decode($data['body'],true);
		if ($json) {
			$_POST['author'] = $json['name'];
			$_POST['url'] = $json['website'];
			$_POST['email'] = $json['email'];
		}
	}
}

// hook to the footer to add our scripting
add_action('wp_footer','sfc_comm_footer_script',30); // 30 to ensure we happen after sfc base
function sfc_comm_footer_script() {
	global $sfc_comm_comments_form;
	if ($sfc_comm_comments_form != true) return; // nothing to do, not showing comments

	if ( is_user_logged_in() ) return; // don't bother with this stuff for logged in users

	$options = get_option('sfc_options');
?>
<style type="text/css">
#fb-user { border: 1px dotted #C0C0C0; padding: 5px; display: block; }
#fb-user .fb_profile_pic_rendered { margin-right: 5px; float:left; }
#fb-user .end { display:block; height:0px; clear:left; }
</style>

<script type="text/javascript">
function sfc_update_user_details() {
	// Show their FB details TODO this should be configurable, or at least prettier...
	if (!jQuery('#fb-user').length) {
		jQuery('#comment-user-details').hide().after("<span id='fb-user'>" +
		"<fb:profile-pic uid='loggedinuser' facebook-logo='true' size='s'></fb:profile-pic>" +
		"<span id='fb-msg'><strong><fb:intl><?php echo addslashes(__('Hi', 'sfc')); ?></fb:intl> <fb:name uid='loggedinuser' useyou='false'></fb:name>!</strong><br /><fb:intl><?php echo addslashes(__('You are connected with your Facebook account.', 'sfc')); ?></fb:intl>" +
		"<a href='#' onclick='FB.Connect.logoutAndRedirect(\"<?php the_permalink() ?>\"); return false;'> <?php echo addslashes(__('Logout', 'sfc')); ?></a>" +
		"</span><span class='end'></span></span>");
		jQuery('#sfc_comm_send').html('<input style="width: auto;" type="checkbox" id="sfc_comm_share" name="sfc_comm_share" /><label for="sfc_comm_share"><fb:intl><?php echo addslashes(__('Share Comment on Facebook', 'sfc')); ?></fb:intl></label>');
	}

	// Refresh the DOM
	FB.XFBML.parse();
}
</script>
<?php
}