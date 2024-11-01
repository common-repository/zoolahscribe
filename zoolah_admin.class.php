<?php

require_once('zoolah_oauth.class.php');
require_once('zoolah_press.class.php');

// Needed for get_currentuserinfo()
require_once( ABSPATH . WPINC . '/pluggable.php' );

class ZoolahAdmin {

	public function init() {
        global $current_user;
        get_currentuserinfo();

		$is_site_admin = false;
		if (function_exists('is_site_admin')) {
			$is_site_admin = is_site_admin();
		}

        if ($is_site_admin) {
            add_action('admin_menu', array('ZoolahAdmin', 'add_zoolah_site_admin_menu'));
            add_action('wp_dashboard_setup', array('ZoolahAdmin', 'zoolah_load_site_admin_dashboard_widget'));
        } else {
			add_action('admin_menu', array('ZoolahAdmin', 'add_zoolah_admin_menu'));     
            add_action('wp_dashboard_setup', array('ZoolahAdmin', 'zoolah_load_dashboard_widget'));
        }
	}

    public function zoolah_load_site_admin_dashboard_widget() {                          
        ZoolahAdmin::zoolah_load_dashboard_widget();
    }   
    
	public function zoolah_load_dashboard_widget() {
		wp_add_dashboard_widget('zoolahpress_widget', 'Zoolah Press', array('ZoolahAdmin', 'zoolahpress_dashboard_widget'));
	}

	public function zoolahpress_dashboard_widget() {
		$zoauth = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'), get_option('zoolah_admin_access_token'), get_option('zoolah_admin_access_token_secret'));
		$stats = $zoauth->getZoolahVendorStats();
		if (!$stats) {
			echo "Failed to retrieve your ZoolahPress stats.";
			return false;
		} else {
			$widget_html  = '<div id="zoolah_dashboard_widget"';
			$widget_html .= '<h1>Welcome to ZoolahPress!</h1>';

			if (count($stats['subscription_plans']) > 0) {
				$widget_html .= <<<TABLEOF
<table id="zoolah_subscription_plan_stats">
	<tr>
		<th>Subscription Plan</th>
		<th># Subscribers</th>
		<th>Revenue*</th>
	</tr>
TABLEOF;
				foreach ($stats['subscription_plans'] as $plan_name => $data) {
					$data['revenue'] = round($data['revenue'], 2);
					$widget_html .= <<<ROWEOF
<tr>
	<td>$plan_name</td>
	<td>{$data['total_subscribers']}</td>
	<td>\${$data['revenue']}</td>
</tr>
ROWEOF;
				}

				$widget_html .= "</table>";
				$widget_html .= "<p>*Revenue normalized to an approximate monthly value.</p>";
			} else {
				$widget_html .= "<p>No subscribers yet.</p>";
			}

			$widget_html .= "</div>";
		}

		echo $widget_html;
	}

    public function add_zoolah_site_admin_menu() {
        ZoolahAdmin::add_zoolah_admin_menu();
    }

	public function add_zoolah_admin_menu() {
		add_menu_page(
			'ZoolahPress',
			'ZoolahPress',
			7,
			str_replace("\\", "/", __FILE__),
			array('ZoolahAdmin', 'show_zoolah_admin')
		);
		add_submenu_page(
			__FILE__,
			'ZoolahPress Configure',
			'Configure',
			7,
			"zoolahpress/zoolah_admin.class.php&what=configure",
			array('ZoolahAdmin', 'zoolah_admin_conf')
		);
		add_submenu_page(
			__FILE__,
			'ZoolahPress Statistics',
			'Statistics',
			7,
			"zoolahpress/zoolah_admin.class.php&what=statistics",
			array('ZoolahAdmin', 'zoolah_admin_statistics')
		);
	}

	public function save_post_subscriptions($id) {
		$zoolah_subscription_plan_tokens = array();

		if (isset($_POST['zoolah_subscription_plan_tokens'])) {
			foreach ($_POST['zoolah_subscription_plan_tokens'] as $plan)
				$zoolah_subscription_plan_tokens[] = $plan;
			if (count($zoolah_subscription_plan_tokens) > 0) {
				delete_post_meta($id, 'zoolah_subscription_plan_tokens');
				add_post_meta($id, 'zoolah_subscription_plan_tokens', $zoolah_subscription_plan_tokens);
			}
		}
		else { delete_post_meta($id, 'zoolah_subscription_plan_tokens'); }
	}


	public function choose_post_subscription_plans() {
		global $post;

		$plans = self::get_subscription_plans();
		$plan_tokens = array();
		if (isset($post->ID) && $post->ID > 0) {
			$post_sub_plans = get_post_meta($post->ID,'zoolah_subscription_plan_tokens',true);
			if (isset($post_sub_plans) && is_array($post_sub_plans))
				$plan_tokens = $post_sub_plans;
		}
?>
<div id="wp_zoolah_subscription" class="postbox">
	<h3>Select a Zoolah Subscription Plan for this Post</h3>
	<div class="inside">
		<?php if (!empty($plans)): ?>
			<?php foreach ($plans as $plan): ?>
				<? $checked = (in_array($plan['token'], $plan_tokens)) ? 'checked="checked"' : ''; ?>
				<input type="checkbox" name="zoolah_subscription_plan_tokens[]" value="<? echo $plan['token']; ?>" <?php echo $checked; ?> />
				<span><? echo $plan['name']; ?></span>
				<br />
			<?php endforeach; ?>
		<?php else: ?>
			<p>You have not yet created any ZoolahScribe plans.</p>
		<?php endif; ?>
	</div>
<?php

	}

	public function zoolah_show_top_navbar($current = '') {
		$plugin_url = $_SERVER['REQUEST_URI'];
		// ::TODO:: does this work in MU?
		$blog_name = get_option('blogname');
		$top_menu_items = array(
			array(
				"anchor" => "Configure Zoolah",
				"link" => "href=\"{$plugin_url}&what=configure\""
			),
			array(
				"anchor" => "ZoolahPress Statistics",
				"link" => "href=\"{$plugin_url}&what=statistics\""
			)
		);

		$output_menu  = '<div class="tablenav">';
		$output_menu  = '<h2>ZoolahScribe Config Settings</h2>';
		$output_menu .= '<div class="form-table">';

		$output_menu .= '<ul>';
		foreach ($top_menu_items as $item)
			$output_menu .= "<li><a {$item['link']}>{$item['anchor']}</a></li>";
		$output_menu .= '</ul>';

		$output_menu .= '</div>';
		$output_menu .= '<div class="view-switch" style="height: 20px;">';
		$output_menu .= '</div>';
		$output_menu .= '<div class="clear" />';
		$output_menu .= '</div>';

		return $output_menu;
	}

	public function zoolah_admin_conf() {
		if (isset($_POST['zoolah_consumer_key']) && isset($_POST['zoolah_consumer_secret'])) {
			update_option('zoolah_consumer_key',trim($_POST['zoolah_consumer_key']));
			update_option('zoolah_consumer_secret',trim($_POST['zoolah_consumer_secret']));

			//$url = "http" . ((!empty($_SERVER['HTTPS'])) ? "s" : "") . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
			$url = "http" . ((!empty($_SERVER['HTTPS'])) ? "s" : "") . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
			$url = preg_replace('/\/[^\/]+$/', '/', $url);
			$zoauth = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'));
			$zoolah_token = $zoauth->getZoolahRequestToken();
			$zoolah_auth_url = $zoauth->getZoolahAuthorizeURL($zoolah_token);

			echo "<h3>Successfully saved your oauth credentials.</h3><br />";
			echo "<h2>Step 2 of 2</h2>";
			echo "<h2> You must authorize your blog to connect to ZoolahScribe by clicking <a href=\"{$zoolah_auth_url}&oauth_callback={$url}\">here</a>.</h2><h2>This will return you back to your wordpress admin interface after you have authorized your blog.</h2>";
		} else {

		?>
		<h2>Step 1 of 2</h2>
		<h3>You must first add an OAuth Client Application in your ZoolahScribe Vendor Dashboard located <a href="<? echo ZoolahPress::build_site_url('scribe/vendors'); ?>">here</a>.</h3>
		<h3>Copy and paste your 'Consumer Key' and 'Consumer Secret' into the form below to connect your blog to ZoolahScribe.</h3><br />
		<form method="post" name="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="zoolah_consumer_key">Consumer Key</label>
						</th>
						<td>
							<input type="text" class="regular-text" value="<?php echo get_option('zoolah_consumer_key');?>" id="zoolah_consumer_key" name="zoolah_consumer_key"/>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="zoolah_consumer_secret">Consumer Secret</label>
						</th>
						<td>
							<input type="text" class="regular-text" value="<?php echo get_option('zoolah_consumer_secret');?>" id="zoolah_consumer_secret" name="zoolah_consumer_secret"/>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td><input class="button-primary" type="submit" value="Save" /></td>
					</tr>
				</tbody>
			</table>
		</form>

		<?php
		}
	}

	public function zoolah_admin_statistics() {
		$zoauth = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'), get_option('zoolah_admin_access_token'), get_option('zoolah_admin_access_token_secret'));
		$stats = $zoauth->getZoolahVendorStats();
		if (!$stats) {
			echo "Failed to retrieve your ZoolahPress stats.";
			return false;
		} else {
			$widget_html  = '<div id="zoolah_dashboard_widget"';
			$widget_html .= '<h1>ZoolahPress Statistics For Your Blog</h1>';
			$widget_html .= '<h2>Subscription Plan Revenue Stats</h2>';

			if (count($stats['subscription_plans']) > 0) {
				$widget_html .= <<<TABLEOF
<table id="zoolah_subscription_plan_stats">
	<tr>
		<th>Subscription Plan</th>
		<th># Subscribers</th>
		<th>Revenue</th>
	</tr>
TABLEOF;
				foreach ($stats['subscription_plans'] as $plan_name => $data) {
					$widget_html .= <<<ROWEOF
<tr>
	<td>$plan_name</td>
	<td>{$data['total_subscribers']}</td>
	<td>\${$data['revenue']}</td>
</tr>
ROWEOF;
				}

				$widget_html .= "</table>";
				$widget_html .= "<p>*Revenue normalized to an approximate monthly value.</p>";
			} else {
				$widget_html .= "<p>No subscribers yet.</p>";
			}

			$widget_html .= "</div>";

			$widget_html .= '<br /><br />';
			$widget_html .= '<h2>New Users in the Last 30 Days</h2>';
			if (count($stats['new_subscriptions']) > 0) {
				$widget_html .= <<<TABLEOF
<table id="zoolah_new_subscriptions_stats">
	<tr>
		<th>User Name</th>
		<th>Subscription Plan</th>
		<th>Length</th>
	</tr>
TABLEOF;
				foreach ($stats['new_subscriptions'] as $plan_token => $data) {
					$widget_html .= <<<ROWEOF
<tr>
	<td>{$data['user_name']}</td>
	<td>{$data['name']}</td>
	<td>{$data['length']}</td>
</tr>
ROWEOF;
				}

				$widget_html .= "</table>";
			} else {
				$widget_html .= "<p>No new Subscribers this month.</p>";
			}
		}

		echo $widget_html;
	}

	public function zoolah_authorize_zpress() {
		$url = "http" . ((!empty($_SERVER['HTTPS'])) ? "s" : "") . "://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
		$url = preg_replace('/\/[^\/]+$/', '/', $url);
		$zoauth = new ZoolahOAuth(get_option('zoolah_consumer_key'), get_option('zoolah_consumer_secret'));
		$zoolah_token = $zoauth->getZoolahRequestToken();
		$zoolah_auth_url = $zoauth->getZoolahAuthorizeURL($zoolah_token);

		echo "<h2> You must authorize your blog to connect to ZoolahScribe by clicking <a href=\"{$zoolah_auth_url}&oauth_callback={$url}\">here</a>.</h2><h2>This will return you back to your wordpress admin interface after you have authorized your blog.</h2>";
	}

	public function show_zoolah_admin() {
		$page_request = isset($_GET['what']) ? trim($_GET['what']) : "";

		echo self::zoolah_show_top_navbar($page_request);
		switch($page_request) {
			case "configure":
				self::zoolah_admin_conf();
			break;
			case "authorize_zpress":
				self::zoolah_authorize_zpress();
			break;
			case "statistics":
				self::zoolah_admin_statistics();
			break;
		}
	}

	public function get_subscription_plans() {
		$zpress = new ZoolahPress();
		$plans = $zpress->get_updated_subscription_plans();

		return $plans;
	}

}

?>
