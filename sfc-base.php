<?php
/*
 * This is the main code for the SFC Base system. It's included by the main "Simple Facebook Connect" plugin.
 */

// Load the textdomain
load_plugin_textdomain('sfc', false, dirname(plugin_basename(__FILE__)) . '/languages');

global $sfc_plugin_list;
$sfc_plugin_list = array(
	'plugin_login'=>'sfc-login.php',
	'plugin_like'=>'sfc-like.php',
	'plugin_publish'=>'sfc-publish.php',
	'plugin_widgets'=>'sfc-widgets.php',
	'plugin_comments'=>'sfc-comments.php',
	'plugin_register'=>'sfc-register.php',
	'plugin_share'=>'sfc-share.php',
	'plugin_photos'=>'sfc-photos.php',
);

// load all the subplugins
add_action('plugins_loaded','sfc_plugin_loader');
function sfc_plugin_loader() {
	global $sfc_plugin_list;
	$options = get_option('sfc_options');
	if (!empty($options)) foreach ($options as $key=>$value) {
		if ($value === 'enable' && array_key_exists($key, $sfc_plugin_list)) {
			include_once($sfc_plugin_list[$key]);
		}
	}
}

// fix up the html tag to have the FBML extensions
add_filter('language_attributes','sfc_lang_atts');
function sfc_lang_atts($lang) {
    return ' xmlns:fb="http://www.facebook.com/2008/fbml" xmlns:og="http://opengraphprotocol.org/schema/" '.$lang;
}

// basic XFBML load into footer
add_action('wp_footer','sfc_add_base_js',20); // 20, to put it at the end of the footer insertions. sub-plugins should use 30 for their code
function sfc_add_base_js() {
	$options = get_option('sfc_options');
	sfc_load_api($options['appid']);
};

/* TODO
private static $allFbLocales = array(
		'ca_ES', 'cs_CZ', 'cy_GB', 'da_DK', 'de_DE', 'eu_ES', 'en_PI', 'en_UD', 'ck_US', 'en_US', 'es_LA', 'es_CL', 'es_CO', 'es_ES', 'es_MX',
		'es_VE', 'fb_FI', 'fi_FI', 'fr_FR', 'gl_ES', 'hu_HU', 'it_IT', 'ja_JP', 'ko_KR', 'nb_NO', 'nn_NO', 'nl_NL', 'pl_PL', 'pt_BR', 'pt_PT',
		'ro_RO', 'ru_RU', 'sk_SK', 'sl_SI', 'sv_SE', 'th_TH', 'tr_TR', 'ku_TR', 'zh_CN', 'zh_HK', 'zh_TW', 'fb_LT', 'af_ZA', 'sq_AL', 'hy_AM',
		'az_AZ', 'be_BY', 'bn_IN', 'bs_BA', 'bg_BG', 'hr_HR', 'nl_BE', 'en_GB', 'eo_EO', 'et_EE', 'fo_FO', 'fr_CA', 'ka_GE', 'el_GR', 'gu_IN',
		'hi_IN', 'is_IS', 'id_ID', 'ga_IE', 'jv_ID', 'kn_IN', 'kk_KZ', 'la_VA', 'lv_LV', 'li_NL', 'lt_LT', 'mk_MK', 'mg_MG', 'ms_MY', 'mt_MT',
		'mr_IN', 'mn_MN', 'ne_NP', 'pa_IN', 'rm_CH', 'sa_IN', 'sr_RS', 'so_SO', 'sw_KE', 'tl_PH', 'ta_IN', 'tt_RU', 'te_IN', 'ml_IN', 'uk_UA',
		'uz_UZ', 'vi_VN', 'xh_ZA', 'zu_ZA', 'km_KH', 'tg_TJ', 'ar_AR', 'he_IL', 'ur_PK', 'fa_IR', 'sy_SY', 'yi_DE', 'gn_PY', 'qu_PE', 'ay_BO',
		'se_NO', 'ps_AF', 'tl_ST'
	);
*/

function sfc_load_api($appid) {
?>
<div id="fb-root"></div>
<script type="text/javascript">
  window.fbAsyncInit = function() {
    FB.init({appId: '<?php echo $appid; ?>', status: true, cookie: true, xfbml: true });
    <?php do_action('sfc_async_init'); // do any other actions sub-plugins might need to do here ?>
  };
  (function() {
    var e = document.createElement('script'); e.async = true;
    e.src = document.location.protocol +
      '//connect.facebook.net/<?php echo get_locale(); ?>/all.js';
    document.getElementById('fb-root').appendChild(e);
  }());
</script>
<?php
}

// plugin row links
add_filter('plugin_row_meta', 'sfc_donate_link', 10, 2);
function sfc_donate_link($links, $file) {
	if ($file == plugin_basename(__FILE__)) {
		$links[] = '<a href="'.admin_url('options-general.php?page=sfc').'">'.__('Settings', 'sfc').'</a>';
		$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=otto%40ottodestruct%2ecom">'.__('Donate', 'sfc').'</a>';
	}
	return $links;
}

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'sfc_settings_link', 10, 1);
function sfc_settings_link($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=sfc').'">'.__('Settings', 'sfc').'</a>';
	return $links;
}

// add the admin settings and such
add_action('admin_init', 'sfc_admin_init',9); // 9 to force it first, subplugins should use default
function sfc_admin_init(){
	$options = get_option('sfc_options');
	if (empty($options['app_secret']) || empty($options['appid'])) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf(__('Simple Facebook Connect needs configuration information on its <a href="%s">settings</a> page.', 'sfc'), admin_url('options-general.php?page=sfc'))."</p></div>';" ) );
	} else {
		add_action('admin_print_footer_scripts','sfc_add_base_js',20);
	}
	wp_enqueue_script('jquery');
	register_setting( 'sfc_options', 'sfc_options', 'sfc_options_validate' );
	add_settings_section('sfc_main', __('Main Settings', 'sfc'), 'sfc_section_text', 'sfc');
	if (!defined('SFC_APP_SECRET')) add_settings_field('sfc_app_secret', __('Facebook Application Secret', 'sfc'), 'sfc_setting_app_secret', 'sfc', 'sfc_main');
	if (!defined('SFC_APP_ID')) add_settings_field('sfc_appid', __('Facebook Application ID', 'sfc'), 'sfc_setting_appid', 'sfc', 'sfc_main');
	if (!defined('SFC_FANPAGE')) add_settings_field('sfc_fanpage', __('Facebook Fan Page', 'sfc'), 'sfc_setting_fanpage', 'sfc', 'sfc_main');
	add_settings_section('sfc_plugins', __('SFC Plugins', 'sfc'), 'sfc_plugins_text', 'sfc');
	add_settings_field('sfc_subplugins', __('Plugins', 'sfc'), 'sfc_subplugins', 'sfc', 'sfc_plugins');
}

// add the admin options page
add_action('admin_menu', 'sfc_admin_add_page');
function sfc_admin_add_page() {
	global $sfc_options_page;
	$sfc_options_page = add_options_page(__('Simple Facebook Connect', 'sfc'), __('Simple Facebook Connect', 'sfc'), 'manage_options', 'sfc', 'sfc_options_page');
}

function sfc_plugin_help($contextual_help, $screen_id, $screen) {
	global $sfc_options_page;
	if ($screen_id == $sfc_options_page) {
		$home = home_url('/');
		$contextual_help = <<< END
<p>To connect your site to Facebook, you will need a Facebook Application.
If you have already created one, please insert your API key, Application Secret, and Application ID below.</p>
<p><strong>Can't find your key?</strong></p>
<ol>
<li>Get a list of your applications from here: <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a></li>
<li>Select the application you want, then copy and paste the API key, Application Secret, and Application ID from there.</li>
</ol>

<p><strong>Haven't created an application yet?</strong> Don't worry, it's easy!</p>
<ol>
<li>Go to this link to create your application: <a target="_blank" href="http://www.facebook.com/developers/createapp.php">Facebook Application Setup</a></li>
<li>After creating the application, put <strong>{$home}</strong> in as the Connect URL on the Connect Tab.</li>
<li>You can get the API information from the application on the
<a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a> page.</li>
<li>Select the application you created, then copy and paste the API key, Application Secret, and Application ID from there.</li>
<li>You can find a walkthrough guide to configuring your Facebook application here: <a href="http://ottopress.com/2010/how-to-setup-your-facebook-connect-application/">How to Setup Your Facebook Application</a></li>
</ol>
END;
	}
	return $contextual_help;
}
add_action('contextual_help', 'sfc_plugin_help', 10, 3);

// display the admin options page
function sfc_options_page() {
?>
	<div class="wrap">
	<h2><?php _e('Simple Facebook Connect', 'sfc'); ?></h2>
	<p><?php _e('Options relating to the Simple Facebook Connect plugins.', 'sfc'); ?> </p>
	<form method="post" action="options.php">
	<?php settings_fields('sfc_options'); ?>
	<table><tr><td>
	<?php do_settings_sections('sfc'); ?>
	</td><td style='vertical-align:top;'>
	<div style='width:20em; float:right; background: #ffc; border: 1px solid #333; margin: 2px; padding: 5px'>
			<h3 align='center'><?php _e('About the Author', 'sfc'); ?></h3>
		<p><a href="http://ottopress.com/blog/wordpress-plugins/simple-facebook-connect/">Simple Facebook Connect</a> is developed and maintained by <a href="http://ottodestruct.com">Otto</a>.</p>
			<p>He blogs at <a href="http://ottodestruct.com">Nothing To See Here</a> and <a href="http://ottopress.com">Otto on WordPress</a>, posts photos on <a href="http://www.flickr.com/photos/otto42/">Flickr</a>, and chats on <a href="http://twitter.com/otto42">Twitter</a>.</p>
			<p>You can follow his site on either <a href="http://www.facebook.com/apps/application.php?id=116002660893">Facebook</a> or <a href="http://twitter.com/ottodestruct">Twitter</a>, if you like.</p>
			<p>If you'd like to <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=otto%40ottodestruct%2ecom">buy him a beer</a>, then he'd be perfectly happy to drink it.</p>
		</div>

	<div style='width:20em; float:right; background: #fff; border: 1px solid #333; margin: 2px; padding: 5px'>
		<h3 align='center'><?php _e('Facebook Platform Status', 'sfc'); ?></h3>
		<?php @wp_widget_rss_output('http://www.facebook.com/feeds/api_messages.php',array('show_date' => 1, 'items' => 10) ); ?>
	</div>
	</td></tr></table>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>
	</form>
	</div>

<?php
}

function sfc_section_text() {
	$options = get_option('sfc_options');
	if (empty($options['app_secret']) || empty($options['appid'])) {
?>
<p><?php _e('To connect your site to Facebook, you will need a Facebook Application.
If you have already created one, please insert your Application Secret, and Application ID below.', 'sfc'); ?></p>
<p><strong><?php _e('Can\'t find your Application Secret and ID?', 'sfc'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a>', 'sfc'); ?></li>
<li><?php _e('Select the application you want, then copy and paste the Application Secret, and Application ID from there.', 'sfc'); ?></li>
</ol>

<p><strong><?php _e('Haven\'t created an application yet?', 'sfc'); ?></strong> <?php _e('Don\'t worry, it\'s easy!', 'sfc'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="http://www.facebook.com/developers/createapp.php">Facebook Connect Setup</a>', 'sfc'); ?></li>
<li><?php $home = home_url('/'); _e("After creating the application, put <strong>{$home}</strong> in as the Connect URL on the Connect Tab.", 'sfc'); ?></li>
<li><?php _e('You can get the API information from the application on the
<a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a> page.', 'sfc'); ?></li>
<li><?php _e('Select the application you created, then copy and paste the API key, Application Secret, and Application ID from there.', 'sfc'); ?></li>
<li><?php _e('You can find a walkthrough guide to configuring your Facebook application here: <a href="http://ottopress.com/2010/how-to-setup-your-facebook-connect-application/">How to Setup Your Facebook Application</a>', 'sfc'); ?></li>
</ol>
<?php
	} 
}

// this will override all the main options if they are pre-defined
function sfc_override_options($options) {
	if (defined('SFC_APP_SECRET')) $options['app_secret'] = $options['api_key'] = SFC_APP_SECRET;
	if (defined('SFC_APP_ID')) $options['appid'] = SFC_APP_ID;
	if (defined('SFC_FANPAGE')) $options['fanpage'] = SFC_FANPAGE;
	return $options;
}
add_filter('option_sfc_options', 'sfc_override_options');

function sfc_setting_app_secret() {
	if (defined('SFC_APP_SECRET')) return;
	$options = get_option('sfc_options');
	echo "<input type='text' id='sfcappsecret' name='sfc_options[app_secret]' value='{$options['app_secret']}' size='40' /> ";
	_e('(required)', 'sfc');
}
function sfc_setting_appid() {
	if (defined('SFC_APP_ID')) return;
	$options = get_option('sfc_options');
	echo "<input type='text' id='sfcappid' name='sfc_options[appid]' value='{$options['appid']}' size='40' /> ";
	_e('(required)', 'sfc');
	if (!empty($options['appid'])) printf(__('<p>Here is a <a href=\'http://www.facebook.com/apps/application.php?id=%s&amp;v=wall\'>link to your applications wall</a>. There you can give it a name, upload a profile picture, things like that. Look for the "Edit Application" link to modify the application.</p>', 'sfc'), $options['appid']);
}
function sfc_setting_fanpage() {
	if (defined('SFC_FANPAGE')) return;
	$options = get_option('sfc_options'); ?>

<p><?php _e('Some sites use Fan Pages on Facebook to connect with their users. The Application wall acts as a
Fan Page in all respects, however some sites have been using Fan Pages previously, and already have
communities and content built around them. Facebook offers no way to migrate these, so the option to
use an existing Fan Page is offered for people with this situation. Note that this doesn\'t <em>replace</em>
the application, as that is not optional. However, you can use a Fan Page for specific parts of the
SFC plugin, such as the Fan Box, the Publisher, and the Chicklet.', 'sfc'); ?></p>

<p><?php _e('If you have a <a href="http://www.facebook.com/pages/manage/">Fan Page</a> that you want to use for
your site, enter the ID of the page here. Most users should leave this blank.', 'sfc'); ?></p>

<?php
	echo "<input type='text' id='sfcfanpage' name='sfc_options[fanpage]' value='{$options['fanpage']}' size='40' />";
}

function sfc_plugins_text() {
?>
<p><?php _e('SFC is a modular system. Click the checkboxes by the sub-plugins of SFC that you want to use. All of these are optional.', 'sfc'); ?></p>
<?php
}

function sfc_subplugins() {
	$options = get_option('sfc_options');
	if ($options['appid']) {
	?>
	<p><label><input type="checkbox" name="sfc_options[plugin_login]" value="enable" <?php checked('enable', $options['plugin_login']); ?> /> <?php _e('Login with Facebook','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_register]" value="enable" <?php checked('enable', $options['plugin_register']); ?> /> <?php _e('User registration (must also enable Login)','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_like]" value="enable" <?php checked('enable', $options['plugin_like']); ?> /> <?php _e('Like Button','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_share]" value="enable" <?php checked('enable', $options['plugin_share']); ?> /> <?php _e('Share Button','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_publish]" value="enable" <?php checked('enable', $options['plugin_publish']); ?> /> <?php _e('Publisher (send posts to Facebook)','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_widgets]" value="enable" <?php checked('enable', $options['plugin_widgets']); ?> /> <?php _e('Sidebar widgets (enables all widgets, use the ones you want)','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_comments]" value="enable" <?php checked('enable', $options['plugin_comments']); ?> /> <?php _e('Comments (for non-registered users)','sfc'); ?></label></p>
	<p><label><input type="checkbox" name="sfc_options[plugin_photos]" value="enable" <?php checked('enable', $options['plugin_photos']); ?> /> <?php _e('Photo Integration','sfc'); ?></label></p>
	<?php
	do_action('sfc_subplugins');
	}
}

// validate our options
function sfc_options_validate($input) {
	
	if (!defined('SFC_APP_SECRET')) {
		// api keys are 32 bytes long and made of hex values
		$input['app_secret'] = trim($input['app_secret']);
		if(! preg_match('/^[a-f0-9]{32}$/i', $input['app_secret'])) {
		  $input['app_secret'] = '';
		}
	}

	if (!defined('SFC_APP_ID')) {
		// app ids are big integers
		$input['appid'] = trim($input['appid']);
		if(! preg_match('/^[0-9]+$/i', $input['appid'])) {
		  $input['appid'] = '';
		}
		
		/**
		 * @todo remove all api_key refrences
		 */
		$input['api_key'] = $input['appid'];
	}

	if (!defined('SFC_FANPAGE')) {
		// fanpage ids are big integers
		$input['fanpage'] = trim($input['fanpage']);
		if(! preg_match('/^[0-9]+$/i', $input['fanpage'])) {
		  $input['fanpage'] = '';
		}
	}

	$input = apply_filters('sfc_validate_options',$input); // filter to let sub-plugins validate their options too
	return $input;
}

// the cookie is signed using our application secret, so it's unfakable as long as you don't give away the secret
function sfc_cookie_parse() {
	$options = get_option('sfc_options');
	$args = array();
	parse_str(trim($_COOKIE['fbs_' . $options['appid']], '\\"'), $args);
	ksort($args);
	$payload = '';
	foreach ($args as $key => $value) {
		if ($key != 'sig') {
			$payload .= $key . '=' . $value;
		}
	}
	if (md5($payload . $options['app_secret']) != $args['sig']) {
		return null;
	}
	return $args;
}

function sfc_base64_url_decode($input) {
    return base64_decode(strtr($input, '-_', '+/'));
}

/*
// this function checks if the current FB user is a fan of your page.
// Returns true if they are, false otherwise.
function sfc_is_fan($pageid='0') {
	$options = get_option('sfc_options');

	if ($pageid == '0') {
		if ($options['fanpage']) $pageid = $options['fanpage'];
		else $pageid = $options['appid'];
	}

	include_once 'facebook-platform/facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);

	$fbuid=$fb->get_loggedin_user();

	if (!$fbuid) return false;

	if ($fb->api_client->pages_isFan($pageid) ) {
		return true;
	} else {
		return false;
	}
}

// get the current facebook user number (0 if the user is not connected to this site)
function sfc_get_user() {
	$options = get_option('sfc_options');
	include_once 'facebook-platform/facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	$fbuid=$fb->get_loggedin_user();
	return $fbuid;
}
*/

function sfc_remote($obj, $connection='', $args=array(), $type = 'GET') {

	$type = strtoupper($type);
	
	if (empty($obj)) return null;
	
	if (empty($args['access_token'])) {
		$cookie = sfc_cookie_parse();
		if (!empty($cookie['access_token'])) {
			$args['access_token'] = $cookie['access_token'];
		}
	}
	
	$url = 'https://graph.facebook.com/'. $obj;
	if (!empty($connection)) $url .= '/'.$connection;
	if ($type == 'GET') $url .= '?access_token='.$args['access_token'];

	if ($type == 'POST') {
		$data = wp_remote_post($url, $args);
	} else if ($type == 'GET') {
		$data = wp_remote_get($url, $args);
	} 
	
	if ($data && !is_wp_error($data)) {
		$resp = json_decode($data['body'],true);
		return $resp;
	}
	
	return false;
}

// code to create a pretty excerpt given a post object
function sfc_base_make_excerpt($post) { 
	
	if (!empty($post->post_excerpt)) $text = $post->post_excerpt;
	else $text = $post->post_content;
	
	$text = strip_shortcodes( $text );

	remove_filter( 'the_content', 'wptexturize' );
	$text = apply_filters('the_content', $text);
	add_filter( 'the_content', 'wptexturize' );

	$text = str_replace(']]>', ']]&gt;', $text);
	$text = wp_strip_all_tags($text);
	$text = str_replace(array("\r\n","\r","\n"),' ',$text);

	$excerpt_more = apply_filters('excerpt_more', '[...]');
	$excerpt_more = html_entity_decode($excerpt_more, ENT_QUOTES, 'UTF-8');
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

	$max = min(1000,apply_filters('sfc_excerpt_length',1000));
	$max -= strlen ($excerpt_more) + 1;
	$max -= strlen ('</fb:intl>') * 2 - 1;

	if ($max<1) return ''; // nothing to send
	
	if (strlen($text) >= $max) {
		$text = substr($text, 0, $max);
		$words = explode(' ', $text);
		array_pop ($words);
		array_push ($words, $excerpt_more);
		$text = implode(' ', $words);
	}

	return $text;
}

// code to find any and all images in a post's actual content, given a post object (returns array of urls)
// this should give the best representative sample of images from the post to push to FB
function sfc_base_find_images($post) { 
	
	$images = array();
	
	// first we apply the filters to the content, just in case they're using shortcodes or oembed to display images
	$content = apply_filters('the_content', $post->post_content);
	
	// next, we get the post thumbnail, put it first in the image list
	if ( current_theme_supports('post-thumbnails') && has_post_thumbnail($post->ID) ) {
		$thumbid = get_post_thumbnail_id($post->ID);
		$att = wp_get_attachment_image_src($thumbid, 'full');
		if (!empty($att[0])) {
			$images[] = $att[0];
		}
	} 
	
	// now search for images in the content itself
	if ( preg_match_all('/<img (.+?)>/', $content, $matches) ) {
		foreach($matches[1] as $match) {
			foreach ( wp_kses_hair($match, array('http')) as $attr)
				$img[$attr['name']] = $attr['value'];
			if ( isset($img['src']) ) {
				if ( !isset( $img['class'] ) || ( isset( $img['class'] ) && false === straipos( $img['class'], apply_filters( 'sfc_img_exclude', array( 'wp-smiley' ) ) ) ) ) { // ignore smilies
					if (!in_array($img['src'], $images)) {
						$images[] = $img['src'];
					}
				}
			}
		}
	}
	
	return $images;
}

// tries to find any video content in a post for meta stuff (only finds first video embed)
function sfc_base_find_video($post) {

	$vid = array();
	
	// first we apply the filters to the content, just in case they're using shortcodes or oembed to display videos
	$content = apply_filters('the_content', $post->post_content);

	// look for an embed to add with video_src (simple, just add first embed)
	if ( preg_match('/<embed (.+?)>/', $content, $matches) ) {
		foreach ( wp_kses_hair($matches[1], array('http')) as $attr) $embed[$attr['name']] = $attr['value'];
		if ( isset($embed['src']) ) $vid[''] = $embed['src'];
		if ( isset($embed['height']) ) $vid[':height'] = $embed['height'];
		if ( isset($embed['width']) ) $vid[':width'] = $embed['width'];
		if ( isset($embed['type']) ) $vid[':type'] = $embed['type'];
	}
	
	return $vid;
}

// add meta tags for *everything*
add_action('wp_head','sfc_base_meta');
function sfc_base_meta() {
	global $post;
	$options = get_option('sfc_options');
	// exclude bbPress post types 
	if ( function_exists('bbp_is_custom_post_type') && bbp_is_custom_post_type() ) return;

	$excerpt = '';
	if (is_singular()) {
	
		global $wp_the_query;
		if ( $id = $wp_the_query->get_queried_object_id() ) {
			$post = get_post( $id );
		}
		
		// get the content from the main post on the page
		$content = sfc_base_make_excerpt($post);
		$images = sfc_base_find_images($post);
		$video = sfc_base_find_video($post);
		$title = get_the_title();
		$permalink = get_permalink();

		echo "<meta property='og:type' content='article' />\n";
		echo "<meta property='og:title' content='". esc_attr($title) ."' />\n";
		echo "<meta property='og:url' content='". esc_url($permalink) ."' />\n";
		echo "<meta property='og:description' content='". esc_attr($content) ."' />\n";
		
		if (!empty($images)) {
			foreach ($images as $image)
				echo "<meta property='og:image' content='{$image}' />\n";
		}
		
		if (!empty($video)) {
			foreach ($video as $type=>$value) {
				echo "<meta property='og:video{$type}' href='{$value}' />\n";
			}
		}
	} else if (is_home()) {
		echo "<meta property='og:type' content='blog' />\n";
		echo "<meta property='og:title' content='". get_bloginfo("name") ."' />\n";
		echo "<meta property='og:url' content='". esc_url(get_bloginfo("url")) ."' />\n";
	}
	
	// stuff on all pages
	echo "<meta property='og:site_name' content='". get_bloginfo("name") ."' />\n";
	echo "<meta property='fb:app_id' content='".esc_attr($options["appid"])."' />\n";
}

// finds a item from an array in a string
if (!function_exists('straipos')) :
function straipos($haystack,$array,$offset=0)
{
   $occ = array();
   for ($i = 0;$i<sizeof($array);$i++)
   {
       $pos = strpos($haystack,$array[$i],$offset);
       if (is_bool($pos)) continue;
       $occ[$pos] = $i;
   }
   if (sizeof($occ)<1) return false;
   ksort($occ);
   reset($occ);
   list($key,$value) = each($occ);
   return array($key,$value);
}
endif;

