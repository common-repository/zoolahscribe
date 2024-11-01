<?php
/*
Plugin Name: ZoolahScribe
Version: 1.42
Description: This plugin allows creators of WordPress blogs to put their content under the ZoolahScribe pay umbrella.  Your readers get single sign-on and a creative mix of ways to pay for your blog.  You get paid in dollars running micro-payment enabled subscription plans that can cost as little as a penny - or even nothing!  Augment your CPM model with a "freemium" plan or tiered subscriptions.  ZoolahScribe gives you the tools to create a subscription plan that lets you best monetize your work.  Create your account, take the tutorial, contact our helpful staff, and get set up in minutes at http://ZoolahScribe.com.
Author: ZoolahScribe
Author URL: http://zoolahscribe.com/
*/

//prevent direct access
if(!defined('DB_NAME')) die('This file should not be accessed directly!');

global $wp_version;
if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	wp_die("ZoolahPress requires at least version 5.0 of PHP (You have PHP ".PHP_VERSION.").");
}
if (version_compare($wp_version, '2.8', '<')) {
	wp_die("ZoolahPress requires at least version 2.8 of Wordpress (You have Wordpress $wp_version)");
}


function zoolah_auth_msg() {
	echo "<div id=\"message\" class=\"updated fade\">Successfully authorized your blog with ZoolahScribe.</div>";
}

function zoolah_error_msg() {
	echo "<div id=\"message\" class=\"error fade\">Failed to authorize your blog with ZoolahScribe! Please try again.</div>";
}

//admin related functionalities
if(preg_match('/wp-admin/',$_SERVER['PHP_SELF']))
{
	require_once('zoolah_oauth.class.php');
	require_once('zoolah_admin.class.php');

	if (isset($_REQUEST['oauth_token'])) {
		$zoauth = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'));
		$zoolah_tokens = $zoauth->getZoolahUserTokens($_REQUEST['oauth_token']);
		$access_token = $zoolah_tokens['oauth_token'];
		$access_token_secret = $zoolah_tokens['oauth_token_secret'];
		if (!$access_token || !$access_token_secret || $access_token == '' || $access_token_secret == '') {
			add_action('admin_notices', 'zoolah_error_msg');
		} else {
			if ($zoauth->isUserOwnerOfClientApp()) {
				update_option('zoolah_admin_access_token', $zoolah_tokens['oauth_token']);
				update_option('zoolah_admin_access_token_secret', $zoolah_tokens['oauth_token_secret']);
				add_action('admin_notices', 'zoolah_auth_msg');
			} else {
				add_action('admin_notices', 'zoolah_error_msg');
			}
		}
	}

	// Set our Wordpress action hooks
	if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
		add_action('edit_post', array('ZoolahAdmin', 'save_post_subscriptions'));
		add_action('publish_post', array('ZoolahAdmin', 'save_post_subscriptions'));
		add_action('save_post', array('ZoolahAdmin', 'save_post_subscriptions'));
	}

	if (substr($GLOBALS['wp_version'], 0, 3) >= '2.5') {
		add_action('edit_form_advanced', array('ZoolahAdmin', 'choose_post_subscription_plans'));
	} else {
		add_action('dbx_post_advanced', array('ZoolahAdmin', 'choose_post_subscription_plans'));
	}

	  //create instance for zoolah admin class & initiate all the functions
	  ZoolahAdmin :: init();
} else {
	//end of admin related functionalities

	require_once('zoolah_press.class.php');

	function zoolah_build_user() {
		$zpress = new ZoolahPress();
		if (isset($_REQUEST['oauth_token']) && $_REQUEST['oauth_token'] != '' && ! is_user_logged_in()) {
			if (isset($_REQUEST['oauth_token_secret']) && $_REQUEST['oauth_token_secret'] != '') {
				$zoa = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'), $_REQUEST['oauth_token'], $_REQUEST['oauth_token_secret']);
				if ($zoa->verifyZoolahAccountAuthorized()) {
					$user_data = $zoa->getZoolahUserInfo();
					$username = "ZOOLAH_{$user_data['login']}";
					$wpuid = null;
					if (($wpuid = username_exists($username)) !== false && $wpuid !== null) {
						$zpress->set_current_zoolah_user($wpuid, $username);
					} else {
						$user_data['access_token'] = $_REQUEST['oauth_token'];
						$user_data['access_token_secret'] = $_REQUEST['oauth_token_secret'];
						$zpress->create_zoolah_user($user_data);
					}
				} else {
					add_action('loop_start', 'zoolah_gen_login_text');
				}
			} else {
				$zpress->build_zoolah_user($_REQUEST['oauth_token']);
			}
		} else if (! $zpress->is_zoolah_user_logged_in()) {
			add_action('loop_start', 'zoolah_gen_login_text');
		}
	}

	function zoolah_gen_login_text() {
		global $displayed;
		if (!isset($displayed)) $displayed = false;

		$image_folder = ZoolahPress::get_image_folder();
		$zpress = new ZoolahPress();
		$login_data = $zpress->zoolah_authorize_data();
		$subscribe_url	= ZoolahPress::build_site_url("scribe/subscription_orders/" . get_option('vendor_name'));
		$base_price = get_option('zoolah_base_price');

		$login_text = '<div class="zoolah-login-block"><br /><a href="'.$login_data['zoolah_auth_url'].'"><img src="'. $image_folder .'ButtonLoginDog35.png" alt="ZoolahScribe Member Login" style="border: none; vertical-align: middle;" /></a> <a href="'.$subscribe_url.'" alt="ZoolahScribe Subscribe Link"><img src="'. $image_folder .'ButtonSubscribe35.png" alt="ZoolahScribe Subscribe" style="border: none; vertical-align: middle;" /></a> <br /> <strong>Starting at only '.$base_price.' Zoolahs.</strong></div>';

		if (!$displayed) {
			echo $login_text;
			$displayed = true;
		} else {
			return false;
		}
	}

	function zoolah_login() {
		$zpress = new ZoolahPress();
		$auth_info = $zpress->zoolah_authorize_data();
		echo "<br /><br /><h2>Or login with <a href=\"{$auth_info['zoolah_auth_url']}\">ZoolahScribe</a>!</h2><br />";
	}

	add_action('init', 'zoolah_build_user');

	$zpress = new ZoolahPress();
	add_action('the_content',array(&$zpress,'protect_content'));
	add_action('the_excerpt',array(&$zpress,'protect_content'));

	add_action('login_form', 'zoolah_login');

}

?>
