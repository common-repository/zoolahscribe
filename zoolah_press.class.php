<?php

require_once('zoolah_oauth.class.php');
// required for username_exists function
require_once( ABSPATH . WPINC . '/registration.php' );

class ZoolahPress {

	public static $BASE_SCRIBE_URL = "http://zoolahscribe.com/";
	public static $BASE_API_PATH = "api/v1/";

	private $postid;
	private $zoolah_subscriptions;
	private $zoolah_user_logged_in;
	private $consumer_key;
	private $consumer_secret;

	public function __construct() {
		$this->reset_subscriptions();

		$this->vendor_name		= get_option('zoolah_vendor_name');
		$this->consumer_key		= get_option('zoolah_consumer_key');
		$this->consumer_secret	= get_option('zoolah_consumer_secret');

		$this->zoolah_user_logged_in = false;
	}

	private function reset_subscriptions() {
		$this->zoolah_subscriptions = array();
		$this->subscription_plans = false;
	}

	public function protect_content($content) {
		global $post,$current_user;
		$this->postid = $post->ID;

		if($this->is_content_protected()) {
			if(!$this->is_zoolah_user_logged_in()) {
				$content = $this->get_custom_excerpt($content);
				$content .= $this->get_available_subscriptions();
			} else if (!$this->is_zoolah_user_authorized()) {
				$content = $this->get_custom_excerpt($content);
				$content .= $this->get_available_subscriptions();
			} else {
				// user authorized for content
			}
		}
		$this->reset_subscriptions();

		return $content;
	}

	public function is_zoolah_user_logged_in() {
		if ($this->zoolah_user_logged_in) return true;

		global $current_user;

		if ( ! is_user_logged_in()) {
			return false;
		}

		$zoolah_user_login = get_usermeta($current_user->ID, 'zoolah_login');

		if (!$zoolah_user_login || $zoolah_user_login == '') {
			return false;
		} else {
			$this->zoolah_user_logged_in = true;
			$this->update_zoolah_user_subscription_tokens();

			return true;
		}
	}

	private function is_zoolah_user_authorized() {
		$post_plans = $this->get_post_plans();

		foreach ($this->zoolah_user_subscription_tokens as $subscription)
			if (in_array($subscription['plan_token'], $post_plans))
				return true;
	}

	private function get_available_subscriptions() {
		$subscribe_url	= self::build_site_url("scribe/subscription_orders/{$this->vendor_name}");
		$subscriptions = "<div class='updated fade below-h2' id='message' style='border: none;'><p><strong>This content is protected with ZoolahScribe, <a href=\"$subscribe_url\">Subscribe Now!</a>.</strong></p></div>";

		return $subscriptions;
	}

	private function get_custom_excerpt($content) {
		return substr(strip_tags($content),0,100).' ...<br />';
	}

	private function is_content_protected() {
		if(!$this->postid || $this->postid < 0)
			return false;

		$post_plans = $this->get_post_plans();

		if (count($post_plans) == 0)
			return false;

		$plans = $this->get_subscription_plans();

		if (!is_array($post_plans) || !is_array($plans))
			return false;
		else
			$this->zoolah_subscriptions = $post_plans;

		foreach ($post_plans as $post_plan)
			if (array_key_exists($post_plan, $plans))
				return true;
	}

	private function get_subscription_plans() {
		if ($this->subscription_plans === false)
			$this->subscription_plans = get_option('zoolah_subscription_plans');

		return $this->subscription_plans;
	}

	public function zoolah_authorize_data() {
		$zoauth = new ZoolahOAuth($this->consumer_key, $this->consumer_secret);
		$zoolah_token = $zoauth->getZoolahRequestToken();
		$zoolah_auth_url = $zoauth->getZoolahAuthorizedAccountURL($zoolah_token);

		$data = array(
			"zoolah_auth_url" 		=> $zoolah_auth_url,
			"zoolah_token"			=> $zoolah_token['oauth_token'],
			"zoolah_token_secret"	=> $zoolah_token['oauth_token_secret'],
		);

		update_option('zoolah_authorize_data', $data);

		return $data;
	}

	public function get_updated_subscription_plans() {
		$plans = $this->update_subscription_plans();

		return $plans;
	}

	private function update_subscription_plans() {
		$zoauth = new ZoolahOAuth($this->consumer_key, $this->consumer_secret, get_option('zoolah_admin_access_token'), get_option('zoolah_admin_access_token_secret'));
		$zoolah_vendor_data = $zoauth->getZoolahVendorSubscriptionPlans();

		$plans = $zoolah_vendor_data['subscription_plans'];
		$vendor_name = $zoolah_vendor_data['vendor_name'];

		$min_cost = false;
		foreach ($plans as $plan)
			foreach ($plan['offers'] as $offer)
				if (!$min_cost || $offer['amount'] < $min_cost)
					$min_cost = $offer['amount'];
		$min_cost = ($min_cost) ? $min_cost : 0;

		update_option('zoolah_vendor_name', $vendor_name);
		update_option('zoolah_subscription_plans', $plans);
		update_option('zoolah_base_price', $min_cost);

		$this->subscription_plans = $plans;
		return $plans;
	}

	private function update_zoolah_user_subscription_tokens() {
		global $current_user;

		delete_usermeta($current_user->ID, 'zoolah_user_subscriptions');

		$access_token = get_usermeta($current_user->ID, 'access_token');
		$access_token_secret = get_usermeta($current_user->ID, 'access_token_secret');
		$zoauth = new ZoolahOAuth($this->consumer_key, $this->consumer_secret, $access_token, $access_token_secret);

		$zoolah_user_subscriptions = $zoauth->getZoolahUserVendorSubscriptions();

		if (!is_array($zoolah_user_subscriptions) || count($zoolah_user_subscriptions) == 0) {
			$this->zoolah_user_subscription_tokens = array();
		} else {
			$user_subscriptions = array();
			foreach ($zoolah_user_subscriptions as $sub_token => $subscription)
				$user_subscriptions[] = $subscription;

			update_usermeta($current_user->ID,'zoolah_user_subscriptions',$user_subscriptions);
			$this->zoolah_user_subscription_tokens = $user_subscriptions;
		}
	}

	private function get_post_plans() {
		if(!$this->postid || $this->postid < 0)
			return array();

		$post_plans = get_post_meta($this->postid, 'zoolah_subscription_plan_tokens');
		if (is_array($post_plans) && count($post_plans) == 1 && is_array($post_plans[0]))
			$post_plans = $post_plans[0];

		if (!is_array($post_plans))
			return array();
		else
			return $post_plans;
	}

	public static function build_url($url) {
		return self::build_site_url(self::$BASE_API_PATH.$url);
	}

	public static function build_site_url($url) {
		return self::$BASE_SCRIBE_URL.$url;
	}

	private function build_plan_durations($plan) {
		if (count($plan['offers']) == 0)
			return '';
		else if (count($plan['offers']) == 1)
			return $plan['offers'][0]['length'];
		// else count is at least 2 so we potentially have a range

		$min = false;
		$max = false;
		$durations = array(
			0 => 'Daily',
			1 => 'Weekly',
			2 => 'Monthly',
			3 => 'Yearly'
		);

		foreach ($plan['offers'] as $offer_data) {
			$offer = $offer_data;
			$index = array_search($offer['length'], $durations);

			if (!$index) continue;

			if (!$min || $index < $min) $min = $index;
			if (!$max || $index > $max) $max = $index;
		}

		// Switched away from displaying a range, display single duration
		if (!$min || !$max || $min === $max)
			return ($min) ? $durations[$min] : (($max) ? $durations[$max] : '');
		else
			return $durations[$min];
	}

	private function build_plan_prices($plan) {
		if (count($plan['offers']) == 0)
			return '';
		else if (count($plan['offers']) == 1)
			return $plan['offers'][0]['amount'];
		// else count is at least 2 so we potentially have a range

		$prices = array();

		foreach ($plan['offers'] as $offer_data) {
			$offer = $offer_data;
			$prices[] = $offer['amount'];
		}

		asort($prices, SORT_NUMERIC);

		// Switched away from displaying a range, display single price
		if (count($prices) == 0)
			return '';
		else if (count($prices) == 1)
			return $prices[0];
		else
			return $prices[0];
	}

	public function build_zoolah_user($oauth_token = false, $oauth_token_secret = false) {
		if (!$oauth_token || $oauth_token == '') return false;

		if ($oauth_token_secret && $oauth_token_secret != '') {
			$zoauth = new ZoolahOAuth($this->consumer_key, $this->consumer_secret, $oauth_token, $oauth_token_secret);
			$user_tokens = array('oauth_token' => $oauth_token, 'oauth_token_secret' => $oauth_token_secret);
		} else {
			$zoauth = new ZoolahOAuth($this->consumer_key, $this->consumer_secret);
			$user_tokens = $zoauth->getZoolahUserTokens($oauth_token);
		}
		$user_info = $zoauth->getZoolahUserInfo();

		if (!$user_info || !is_array($user_info) || !isset($user_info['login']))
			return false;

		$user_info['access_token'] = $user_tokens['oauth_token'];
		$user_info['access_token_secret'] = $user_tokens['oauth_token_secret'];

		$user = $this->create_zoolah_user($user_info);

		return (!$user) ? false : $user;
	}

	public function create_zoolah_user($user_info = array()) {
		if (empty($user_info)) return false;
		$wpuid = null;

		$username					= $user_info['login'];
		$zoolah_username			= "ZOOLAH_" . $user_info['login'];
		$user_access_token			= $user_info['access_token'];
		$user_access_token_secret	= $user_info['access_token_secret'];

		if (($wpuid = username_exists($zoolah_username)) != null) {
			// user exists
		} else {
			$new_user_info = array(
				'user_login'	=> $zoolah_username,
				'user_nicename'	=> $username,
				'nickname'		=> $username,
				'display_name'	=> $username,
				'user_url'		=> self::build_site_url('users/'.$username),
				'user_pass'		=> wp_generate_password(),
				'role'			=> 'subscriber'
			);

			$wpuid = wp_insert_user($new_user_info);

			if ($wpuid) {
				update_usermeta($wpuid, 'zoolah_login', $username);
			}
		}

		if ($wpuid) {
			update_usermeta($wpuid, 'access_token', $user_access_token);
			update_usermeta($wpuid, 'access_token_secret', $user_access_token_secret);

			$this->set_current_zoolah_user($wpuid, $username);

			return $wpuid;
		} else {
			return false;
		}
	}

	public function set_current_zoolah_user($wpuid = false, $username = false) {
		if (!$wpuid || !is_numeric($wpuid) || !$username) return false;

		global $current_user;

		wp_set_current_user($wpuid, $username);
		wp_set_auth_cookie($wpuid, true, false);
		do_action('wp_login', $username);

		get_currentuserinfo();

		return $wpuid;
	}

	public static function get_image_folder() {
		return plugins_url(plugin_basename(dirname(__FILE__))) . "/images/";
	}

}

?>
