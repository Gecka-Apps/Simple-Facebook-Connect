<?php
global $sfc_like_defaults;
$sfc_like_defaults = array(
		'id'=>0,
		'layout'=>'standard', 		// standard or button_count
		'showfaces'=>'true', 		// true or false
		'width'=>'450',
		'height'=>'65',
		'send' => 'false',		// true or false
		'action'=>'like',		// like or recommend
		'colorscheme'=>'light',		// light or dark
		'font' => 'lucida+grande',	// arial, lucida+grande, seqoe+ui, tahoma, trebuchet+ms, or verdana
		);

function get_sfc_like_button($args='') {
	global $sfc_like_defaults;
	$args = wp_parse_args($args, $sfc_like_defaults);
	extract($args);

	if (empty($url)) $url = urlencode(get_permalink($id));

	return "<fb:like href='{$url}' send='{$send}' layout='{$layout}' show_faces='{$showfaces}' width='{$width}' height='{$height}' action='{$action}' colorscheme='{$colorscheme}' font='{$font}'></fb:like>";
}

function sfc_like_button($args='') {
	echo get_sfc_like_button($args);
}

function sfc_like_shortcode($atts) {
	global $sfc_like_defaults;
	$args = shortcode_atts($sfc_like_defaults, $atts);

	return get_sfc_like_button($args);
}
add_shortcode('fb-like', 'sfc_like_shortcode');

function sfc_like_button_automatic($content) {
	global $post;
	$post_types = apply_filters('sfc_like_post_types', get_post_types( array('public' => true) ) );
	if ( !in_array($post->post_type, $post_types) ) return $content;
	
	// exclude bbPress post types 
	if ( function_exists('bbp_is_custom_post_type') && bbp_is_custom_post_type() ) return $content;
	
	$options = get_option('sfc_options');

	$args = array(
		'layout'=>$options['like_layout'],
		'action'=>$options['like_action'],
		'send'=>$options['like_send'],
	);

	$button = get_sfc_like_button($args);
	switch ($options['like_position']) {
		case "before":
			$content = $button . $content;
			break;
		case "after":
			$content = $content . $button;
			break;
		case "both":
			$content = $button . $content . $button;
			break;
		case "manual":
		default:
			break;
	}
	return $content;
}
add_filter('the_content', 'sfc_like_button_automatic', 30);

// add the admin sections to the sfc page
add_action('admin_init', 'sfc_like_admin_init');
function sfc_like_admin_init() {
	add_settings_section('sfc_like', __('Like Button Settings', 'sfc'), 'sfc_like_section_callback', 'sfc');
	add_settings_field('sfc_like_position', __('Like Button Position', 'sfc'), 'sfc_like_position', 'sfc', 'sfc_like');
	add_settings_field('sfc_like_layout', __('Like Button Layout', 'sfc'), 'sfc_like_layout', 'sfc', 'sfc_like');
	add_settings_field('sfc_like_action', __('Like Button Action', 'sfc'), 'sfc_like_action', 'sfc', 'sfc_like');
	add_settings_field('sfc_like_send', __('Send Button', 'sfc'), 'sfc_like_send', 'sfc', 'sfc_like');
}

function sfc_like_section_callback() {
	echo '<p>'.__('Choose where you want the like button added to your content.', 'sfc').'</p>';
}

function sfc_like_position() {
	$options = get_option('sfc_options');
	if (!$options['like_position']) $options['like_position'] = 'manual';
	?>
	<ul>
	<li><label><input type="radio" name="sfc_options[like_position]" value="before" <?php checked('before', $options['like_position']); ?> /> <?php _e('Before the content of your post', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_position]" value="after" <?php checked('after', $options['like_position']); ?> /> <?php _e('After the content of your post', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_position]" value="both" <?php checked('both', $options['like_position']); ?> /> <?php _e('Before AND After the content of your post', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_position]" value="manual" <?php checked('manual', $options['like_position']); ?> /> <?php _e('Manually add the button to your theme or posts (use the sfc_like_button() function in your theme)', 'sfc'); ?></label></li>
	</ul>
<?php
}

function sfc_like_layout() {
	$options = get_option('sfc_options');
	if (!$options['like_layout']) $options['like_layout'] = 'standard';
	?>
	<ul>
	<li><label><input type="radio" name="sfc_options[like_layout]" value="standard" <?php checked('standard', $options['like_layout']); ?> /> <?php _e('Standard', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_layout]" value="button_count" <?php checked('button_count', $options['like_layout']); ?> /> <?php _e('Button with counter', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_layout]" value="box_count" <?php checked('box_count', $options['like_layout']); ?> /> <?php _e('Box with counter', 'sfc'); ?></label></li>
	</ul>
<?php
}

function sfc_like_action() {
	$options = get_option('sfc_options');
	if (!$options['like_action']) $options['like_action'] = 'like';
	?>
	<ul>
	<li><label><input type="radio" name="sfc_options[like_action]" value="like" <?php checked('like', $options['like_action']); ?> /> <?php _e('Like', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_action]" value="recommend" <?php checked('recommend', $options['like_action']); ?> /> <?php _e('Recommend', 'sfc'); ?></label></li>
	</ul>
<?php
}

function sfc_like_send() {
	$options = get_option('sfc_options');
	if (!$options['like_send']) $options['like_send'] = 'false';
	?>
	<ul>
	<li><label><input type="radio" name="sfc_options[like_send]" value="true" <?php checked('true', $options['like_send']); ?> /> <?php _e('Enabled', 'sfc'); ?></label></li>
	<li><label><input type="radio" name="sfc_options[like_send]" value="false" <?php checked('false', $options['like_send']); ?> /> <?php _e('Disabled', 'sfc'); ?></label></li>
	</ul>
<?php 
}

add_filter('sfc_validate_options','sfc_like_validate_options');
function sfc_like_validate_options($input) {
	if (!in_array($input['like_position'], array('before', 'after', 'both', 'manual'))) {
			$input['like_position'] = 'manual';
	}
	return $input;
}

