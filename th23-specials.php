<?php
/*
Plugin Name: th23 Specials
Description: Adjust WordPress behaviour to own needs
Version: 6.0.0
Author: Thorsten Hartmann (th23)
Author URI: https://th23.net
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: th23-specials
Domain Path: /lang

Coded 2014-2025 by Thorsten Hartmann (th23)
https://th23.net/
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_specials {

	// Initialize class-wide variables
	public $plugin = array(); // plugin (setup) information
	public $options = array(); // plugin options (user defined, changable)
	public $data = array(); // data exchange between plugin functions

	function __construct() {

		// Setup basics
		$this->plugin['slug'] = 'th23-specials';
		$this->plugin['file'] = __FILE__;
		$this->plugin['basename'] = plugin_basename($this->plugin['file']);
		$this->plugin['dir_url'] = plugin_dir_url($this->plugin['file']);
		$this->plugin['version'] = '6.0.0';

		// Load plugin options
		$this->options = (array) get_option($this->plugin['slug']);

		// Localization
		add_action('init', array(&$this, 'localize'));

		// Detect update
		if(empty($this->options['version']) || $this->options['version'] != $this->plugin['version']) {
			// load class and trigger required actions
			$plugin_dir_path = plugin_dir_path($this->plugin['file']);
			if(file_exists($plugin_dir_path . '/th23-specials-upgrade.php')) {
				require($plugin_dir_path . '/th23-specials-upgrade.php');
				$upgrade = new th23_specials_upgrade($this);
				$upgrade->start();
				// reload options - at least option version should have changed
				$this->options = (array) get_option($this->plugin['slug']);
			}
		}

		// == customization: from here on plugin specific ==

		// === MAIL ===

		// == Use SMTP server [mail_smtp] ==
		if(!empty($this->options['mail_smtp'])) {
			add_action('phpmailer_init', array(&$this, 'smtp_init'));
			// add tip against spam to overlay messages and mails (th23 Susbcribe plugin)
			add_filter('th23_subscribe_omsg_text', array(&$this, 'spam_notice_overlay'), 10, 2);
			add_filter('th23_subscribe_confirmation_link', array(&$this, 'spam_notice_mail'), 15);
		}

		// == Custom prefix for mail subject [mail_prefix] ==
		add_action('wp_mail', array(&$this, 'mail_subject_remove_prefix'));

		// === POSTS / PAGES ===

		// == Disable revisions [disable_revisions] ==
		if(isset($this->options['disable_revisions']) && -1 !== $this->options['disable_revisions']) {
			add_filter('wp_revisions_to_keep', function($num) {
				return (int) $this->options['disable_revisions'];
			}, 1);
		}

		// == Restrict editing / deleting of posts / pages [edit_admin_only] ==
		// note: needs to be present on "frontend" to also be in effect for change attempts via REST API
		if(!empty($this->options['edit_admin_only'])) {
			add_filter('user_has_cap', array(&$this, 'edit_admin_only'), 10, 4);
		}

		// == Enable excerpts for pages [enable_excerpts] ==
		if(!empty($this->options['enable_excerpts'])) {
			add_action('init', function() {
				add_post_type_support('page', 'excerpt');
			});
		}

		// == Show sticky posts on top of overview pages [sticky_first] ==
		if(!empty($this->options['sticky_first'])) {
			add_action('pre_get_posts', array(&$this, 'sticky_first_remove'));
			add_filter('the_posts', array(&$this, 'sticky_first_add'), 10, 2);
		}

		// == Handle *highlight* in post and page titles [title_markup] ==
		if(!empty($this->options['title_markup'])) {
			add_filter('the_title', array(&$this, 'post_title_show'), 1, 2); // filter early as basis for other filters
			// note: needs to be present on "frontend" to also be in effect for change attempts via REST API
			add_action('post_updated', array(&$this, 'post_title_changed'), 10, 3);
		}

		// == Disable author links and archives [disable_author_links] ==
		if(!empty($this->options['disable_author_links'])) {
			// change get_the_author_posts_link() hooking in late, so nobody overwrites
			add_filter('the_author_posts_link', function($link) {
				return get_the_author();
			}, 1000, 1);
			// change get_author_posts_url() hooking in late, so nobody overwrites
			add_filter('author_link', function($link, $author_id, $author_nicename) {
				return '#';
			}, 1000, 3);
			// disable author archive pages
			add_action('template_redirect', function() {
				if(isset($_GET['author']) || is_author()) {
					global $wp_query;
					$wp_query->set_404();
					status_header(404);
					nocache_headers();
				}
			});
		}

		// === WEBSITE ===

		// note: various changes via filter require WP fully loaded
		add_action('init', array(&$this, 'adjust_website'), 0);

		// == Add "show my age" shortcode ==
		// example: I am [show-my-age birthday="mm/dd/yyyy"] years old.
		add_shortcode('show-my-age', function($birthday) {
			extract(shortcode_atts(array('birthday' => 'birthday'), $birthday));
			return gmdate("Y", time() - strtotime($birthday)) - 1970;
		});

		// === USER ===

		// == Remove legacy contact methods [disable_legacy_contacts] ==
		if(!empty($this->options['disable_legacy_contacts'])) {
			add_filter('user_contactmethods', function($methods) {
				unset($methods['aim'], $methods['yim'], $methods['jabber']);
				return $methods;
			});
		}

	}

	// Error logging
	function log($msg) {
		if(!empty(WP_DEBUG) && !empty(WP_DEBUG_LOG)) {
			if(empty($this->plugin['data'])) {
				$plugin_data = get_file_data($this->plugin['file'], array('Name' => 'Plugin Name'));
				$plugin_name = $plugin_data['Name'];
			}
			else {
				$plugin_name = $this->plugin['data']['Name'];
			}
			error_log($plugin_name . ': ' . print_r($msg, true));
		}
	}

	// Localization
	function localize() {
		load_plugin_textdomain('th23-specials', false, dirname($this->plugin['basename']) . '/lang');
	}

	// == customization: from here on plugin specific ==

	// === MAIL ===

	// == Use SMTP server [mail_smtp] ==

	// Use SMTP for all outgoing mails [mail_smtp]
	function smtp_init($phpmailer) {

		// Activate SMTP option
		$phpmailer->IsSMTP();

		// Define server
		$phpmailer->SMTPSecure = $this->options['smtp_secure'];
		$phpmailer->SMTPAutoTLS = false; // Note: PHPMailer 5.2.10 introduced this option, but it might cause issues if the server is advertising TLS with an invalid certificate
		$phpmailer->Host = $this->options['smtp_server'];
		$phpmailer->Port = $this->options['smtp_port'];

		// Authentication
		$phpmailer->SMTPAuth = false;
		if(!empty($this->options['smtp_auth'])) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $this->options['smtp_user'];
			$phpmailer->Password = $this->options['smtp_pass'];
		}

		// Sender identity
		$phpmailer->From = $phpmailer->Username;
		$phpmailer->Sender = $phpmailer->From;
		if(!empty($this->options['smtp_from_name'])) {
			$phpmailer->FromName = $this->options['smtp_from_name'];
		}
		if(!empty($this->options['smtp_reply_mail'])) {
			if(!empty($this->options['smtp_reply_name'])) {
				$phpmailer->AddReplyTo($this->options['smtp_reply_mail'], $this->options['smtp_reply_name']);
			}
			else {
				$phpmailer->AddReplyTo($this->options['smtp_reply_mail']);
			}
		}

	}

	// Add tip against spam to overlay messages (th23 Susbcribe plugin) [mail_smtp]
	// alternative text: ... add <a href="mailto:%1$s">%1$s</a> to your contact list to avoid notifications being marked as spam
	function spam_notice_overlay($message, $id) {
		$mail_link_escaped = '<a href="mailto:' . esc_attr($this->options['smtp_user']) . 'subject=' . esc_attr__('Manual confirmation', 'th23-specials') . '">' . esc_html($this->options['smtp_user']) . '</a>';
		if($id == 'subscribe_success_global' || $id == 'subscribe_success_comment') {
			/* translators: parses in linked mail address */
			$message .= '<span class="th23-special-subscribe-hint">' . sprintf(esc_html__('Tip: Send an email to %s to avoid notifications being marked as spam', 'th23-specials'), $mail_link_escaped) . '</span>';
		}
		elseif($id == 'subscribe_visitor_confirm_global' || $id == 'subscribe_visitor_confirm_comment') {
			/* translators: parses in linked mail address */
			$message .= '<span class="th23-special-subscribe-hint">' . sprintf(esc_html__('<strong>No mail received?</strong> Please check your spam folder - and send an email to %s to avoid notifications being marked as spam', 'th23-specials'), $mail_link_escaped) . '</span>';
		}
		elseif($id == 'subscribe_visitor_already_comment') {
			/* translators: parses in linked mail address */
			$message .= '<span class="th23-special-subscribe-hint">' . sprintf(esc_html__('Tip: To ensure you get notifications to your inbox, send an email to %s as this will avoid notifications being marked as spam', 'th23-specials'), $mail_link_escaped) . '</span>';
		}
		return $message;
	}
	// Add tip against spam to mails (th23 Susbcribe plugin) [mail_smtp]
	function spam_notice_mail($link) {
		return $link . "\r\n\r\n" . esc_html__('Tip: Additionally respond to this email to avoid notifications being marked as spam!', 'th23-specials');
	}

	// == Custom prefix for mail subject, replacing default "[%blogname%] " [mail_prefix] ==
	function mail_subject_remove_prefix($components) {
		$default_prefix = '[' . wp_specialchars_decode(get_option('blogname'), ENT_QUOTES) . '] ';
		$components['subject'] = str_replace($default_prefix, $this->options['mail_prefix'], $components['subject']);
		return $components;
	}

	// === POSTS / PAGES ===

	// == Restrict editing / deleting of posts / pages, filtering allowed capabilities for user [edit_admin_only] ==
	// note: needs to be present on "frontend" to also be in effect for change attempts via REST API
	function edit_admin_only($allowed_caps, $required_caps, $args, $user) {
		// current user is admin ie has capabilities to modify options ie change edit restrictions
		if(in_array('manage_options', $allowed_caps)) {
			return $allowed_caps;
		}
		// current action does not target a specific post/page by id ("$args[2]") or not a scpecially protected post/page
		if(empty($args[2]) || !in_array($args[2], explode(',', $this->options['edit_admin_only']))) {
			return $allowed_caps;
		}
		// current action does not require permission to edit/delete a post/page
		if(empty($checked_caps = array_intersect(array('edit_posts', 'edit_others_posts', 'edit_published_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages'), $required_caps))) {
			return $allowed_caps;
		}
		// removing checked capabilities from allowed capabilities - will prevent action
		return array_diff_key($allowed_caps, array_fill_keys($checked_caps, 1));
	}

	// == Show sticky posts on top of overview pages [sticky_first] ==

	// Remove sticky posts (unless "ignore_sticky_posts" is set) from standard query set [sticky_first]
	function sticky_first_remove($query) {
		// "ignore_sticky_posts" OR (not at home AND not at category overview) OR no sticky posts
		if($query->get('ignore_sticky_posts') || (!$query->is_home && !$query->is_category) || empty($sticky_ids = (array) get_option('sticky_posts'))) {
			return;
		}
		$query->set('post__not_in', $sticky_ids);
	}

	// Add sticky posts at top of first page on home and category archive pages (unless "ignore_sticky_posts" is set) [sticky_first]
	function sticky_first_add($posts, $query) {
		// "ignore_sticky_posts" OR (not at home AND not at category overview) OR not on first page OR no sticky posts
		if($query->get('ignore_sticky_posts') || (!$query->is_home && !$query->is_category) || (int) $query->get('paged') > 1 || empty($sticky_ids = (array) get_option('sticky_posts'))) {
			return $posts;
		}
		$sticky_query = array('post__in' => $sticky_ids);
		// on home display only sticky posts not belonging to a category, set category ID to "uncategorized", ie 1
		if($query->is_home) {
			$sticky_query['cat'] = 1;
		}
		// sticky posts first
		return array_merge((array) get_posts(array_merge($query->query, $sticky_query)), $posts);
	}

	// == Handle *highlight* in post and page titles ==

	// Show title with markup on frontend
	function post_title_show($post_title, $post_id) {
		// todo: check for wp_doing_ajax() which is backend, but might be used on frontend
		if(is_admin() || wp_is_serving_rest_request() || empty($markup = get_post_meta($post_id, 'th23_title_marked', true))) {
			return $post_title;
		}
		return $markup;
	}

	// Store *highlight* in title as markup
	// note: needs to be present on "frontend" to also be in effect for change attempts via REST API
	function post_title_changed($post_id, $post_after, $post_before) {
		$post_title_marked = preg_replace('/\*(.+?)\*/', '<span>$1</span>', $post_after->post_title);
		if($post_title_marked != $post_after->post_title) {
			update_post_meta($post_id, 'th23_title_marked', $post_title_marked);
		}
		else {
			delete_post_meta($post_id, 'th23_title_marked');
		}
	}

	// === WEBSITE ===

	// note: various changes via filter require WP fully loaded
	function adjust_website() {

		// == Remove default filters, altering the spelling of post content [disable_wp_filter] ==
		if(!empty($this->options['disable_wp_filter'])) {
			remove_filter('the_content', 'capital_P_dangit', 11);
			remove_filter('the_title', 'capital_P_dangit', 11);
			remove_filter('wp_title', 'capital_P_dangit', 11);
			remove_filter('comment_text', 'capital_P_dangit', 31);
		}

		// == Remove information about core version from meta tags [disable_version] ==
		if(!empty($this->options['disable_version'])) {
			remove_action('wp_head', 'wp_generator');
		}

		// == Remove various links from HTML head [disable_links] ==
		if(!empty($this->options['disable_links']) && !get_theme_support('th23-control-header-links')) {
			remove_action('wp_head', 'rsd_link'); // remove RSD link
			remove_action('wp_head', 'wlwmanifest_link'); // remove WLWManifest link
			remove_action('wp_head', 'rest_output_link_wp_head'); // remove REST api link
			remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0); // remove shortlink
			remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0); // remove previous/next links
			remove_action('wp_head', 'feed_links_extra', 3); // remove extra feeds - keeping only main and comment ones
		}

		// == Remove jQuery Migrate dependency [disable_jquery_migrate] ==
		if(!empty($this->options['disable_jquery_migrate']) && !is_admin()) {
			add_action('wp_default_scripts', function($scripts) {
				if($script = $scripts->registered['jquery']) {
					$script->deps = array_diff($script->deps, array('jquery-migrate'));
				}
			});
		}

		// == Remove inline CSS for comment added by original WP widget [disable_comment_css] ==
		if(!empty($this->options['disable_comment_css']) && !get_theme_support('th23-control-comment-css')) {
			add_filter('show_recent_comments_widget_style', '__return_false', 99);
		}

		// == Remove default emoji handling [disable_emojis] ==
		if(!empty($this->options['disable_emojis']) && !get_theme_support('th23-control-emoji')) {
			// disable default emoji function
			remove_action('init', 'smilies_init', 5);
			// disable default emoji filter
			remove_filter('the_content', 'convert_smilies');
			remove_filter('the_excerpt', 'convert_smilies');
			remove_filter('comment_text', 'convert_smilies', 20);
			// remove emoji JS, CSS (frontend)
			remove_action('wp_head', 'print_emoji_detection_script', 7);
			remove_action('wp_print_styles', 'print_emoji_styles');
			// remove emoji JS, CSS (Tiny MCE editor, which can be present on frontend and in admin)
			remove_action('admin_print_scripts', 'print_emoji_detection_script');
			remove_action('admin_print_styles', 'print_emoji_styles');
			add_filter('tiny_mce_plugins', function($plugins) {
				return array_diff((array) $plugins, array('wpemoji'));
			});
			// disable everything else related to emojis
			remove_filter('the_content_feed', 'wp_staticize_emoji');
			remove_filter('comment_text_rss', 'wp_staticize_emoji');
			remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
			// remove wordpress.org DNS prefetch used for default emojis
			remove_action('wp_head', 'wp_resource_hints', 2);
		}

		// == Remove oEmbed functionality [disable_oembed] ==
		if(!empty($this->options['disable_oembed']) && !get_theme_support('th23-control-oembed')) {
			remove_action('rest_api_init', 'wp_oembed_register_route'); // REST API endpoint
			remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10); // oEmbed auto discovery
			remove_action('wp_head', 'wp_oembed_add_discovery_links'); // oEmbed discovery links
			remove_action('wp_head', 'wp_oembed_add_host_js'); // oEmbed-specific JS
		}

	}

}

// === INITIALIZATION ===

$th23_specials_path = plugin_dir_path(__FILE__);

// Load additional admin class, if required...
if(is_admin() && file_exists($th23_specials_path . 'th23-specials-admin.php')) {
	require($th23_specials_path . 'th23-specials-admin.php');
	$th23_specials = new th23_specials_admin();
}
// ...or initiate plugin directly
else {
	$th23_specials = new th23_specials();
}

?>
