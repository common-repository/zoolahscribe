<?php

require_once('OAuth.php');

class ZoolahOAuth {
	public static $ZOOLAH_URL = "http://zoolahscribe.com/";

	public $token;
	public $signature_method;
	public $zoolah_consumer;

	function __construct($consumer_key, $consumer_secret, $oauth_token = null, $oauth_token_secret = null) {
		$this->signature_method = new OAuthSignatureMethod_HMAC_SHA1();

		$this->zoolah_consumer = new OAuthConsumer($consumer_key, $consumer_secret);
		if ($oauth_token == null || $oauth_token_secret == null)
			$this->token = null;
		else
			$this->token = new OAuthConsumer($oauth_token, $oauth_token_secret);
	}

	public function getZoolahUserInfo() {
		$url = ZoolahPress::build_url('oauth_user_info.json');

		$request = $this->request($url);
		$user_info = json_decode($request, true);

		return $user_info;
	}

	public function getZoolahUserVendorSubscriptions() {
		$url = ZoolahPress::build_url('oauth_user_vendor_subscriptions.json');

		$request = $this->request($url);
		$user_info = json_decode($request, true);

		return $user_info;
	}

	public function getZoolahVendorStats() {
		$url = ZoolahPress::build_url('oauth_vendor_stats.json');

		$request = $this->request($url);
		$vendor_stats = json_decode($request, true);
		if (isset($vendor_stats['error']))
			return false;

		return $vendor_stats;
	}

	public function getZoolahVendorSubscriptionPlans() {
		$url = ZoolahPress::build_url('oauth_vendor_subscription_plans.json');

		$request = $this->request($url);
		$vendor_plans = json_decode($request, true);

		return $vendor_plans;
	}

	public function getZoolahRequestToken() {
		$data = $this->request(self::zoolahRequestTokenURL());

		$token = $this->getResponseData($data);

		$this->token = new OAuthConsumer($data['oauth_token'], $data['oauth_token_secret']);
		return $token;
	}

	public function getZoolahUserTokens($oauth_token = false) {
		if (!$oauth_token) return false;
		$data = $this->request(self::zoolahAccessTokenURL(), array('oauth_token' => $oauth_token));
		$token = $this->getResponseData($data);

		$this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);

		return $token;
	}

	public function isUserOwnerOfClientApp() {
		$data = $this->request(ZoolahPress::build_url('oauth_is_user_owner_client_app.json'));

		$result = json_decode($data, true);

		if (isset($result['error'])) {
			return false;
		} else if (isset($result['success'])) {
			return true;
		} else {
			return false;
		}
	}

	public function verifyZoolahAccountAuthorized() {
		$data = $this->request(ZoolahPress::build_url('oauth_verify_account_authorized.json'));

		$result = json_decode($data, true);

		if (isset($result['error'])) {
			return false;
		} else if (isset($result['success'])) {
			return true;
		} else {
			return false;
		}
	}

	public function getZoolahAuthorizeURL($token) {
		return self::zoolahAuthorizeURL() . "?oauth_token={$token['oauth_token']}";
	}

	public function getZoolahAuthorizedAccountURL($token) {
		return self::zoolahAuthorizedAccountURL() . "?oauth_token={$token['oauth_token']}";
	}

	public function getResponseData($data) {
		$params = split('&', $data);
		$result = array();
		$f_split = create_function('$s', 'return split("=", $s);');

		foreach (array_map($f_split, $params) as $doublet)
			if (isset($doublet[1]))
				$result[urldecode($doublet[0])] = urldecode($doublet[1]);

		return $result;
	}

	public function request($url, $params = null) {
		$method = 'GET';

		$request = OAuthRequest::from_consumer_and_token($this->zoolah_consumer, $this->token, $method, $url, $params);

		$request->sign_request($this->signature_method, $this->zoolah_consumer, $this->token);

		return $this->curl_request($request->to_url());
	}

	public function curl_request($url) {
		$curl_req = curl_init();
		curl_setopt($curl_req, CURLOPT_URL, $url);
		curl_setopt($curl_req, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl_req, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec ($curl_req);
		curl_close($curl_req);

		return $response;
	}

	/**
	 * STATIC OAUTH URLS
	 */
	public static function zoolahRequestTokenURL() {
		return self::$ZOOLAH_URL . 'oauth/request_token';
	}

	public static function zoolahAccessTokenURL() {
		return self::$ZOOLAH_URL . 'oauth/access_token';
	}

	public static function zoolahAuthorizeURL() {
		return self::$ZOOLAH_URL . 'oauth/authorize';
	}

	public static function zoolahAuthorizedAccountURL() {
		return self::$ZOOLAH_URL . 'oauth/active_oauth_account';
	}

}

?>
