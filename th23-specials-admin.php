<?php

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_specials_admin extends th23_specials {

	// Extend class-wide variables
	public $i18n;
	private $admin;

	function __construct() {

		parent::__construct();

		// Setup basics (additions for backend)
		$this->plugin['dir_path'] = plugin_dir_path($this->plugin['file']);
		$this->plugin['settings'] = array(
			'base' => 'options-general.php',
			'permission' => 'manage_options',
		);
		// icon: "square" 48 x 48px (footer) / "horizontal" 36px height (header, width irrelevant) / both (resized if larger)
		$this->plugin['icon'] = array('square' => 'img/icon-square.png', 'horizontal' => 'img/icon-horizontal.png');
		$this->plugin['support_url'] = 'https://github.com/th23x/th23-specials/issues';
		// update: alternative update source
		$this->plugin['update_section'] = true;
		$this->plugin['update_url'] = 'https://github.com/th23x/th23-specials/releases/latest/download/update.json';

		// Load and setup required th23 Admin class
		if(file_exists($this->plugin['dir_path'] . '/inc/th23-admin-class.php')) {
			require($this->plugin['dir_path'] . '/inc/th23-admin-class.php');
			$this->admin = new th23_admin_v162($this);
		}
		if(!empty($this->admin)) {
			add_action('init', array(&$this, 'setup_admin_class'));
		}
		else {
			add_action('admin_notices', array(&$this, 'error_admin_class'));
		}

		// Load plugin options
		// note: earliest possible due to localization only available at "init" hook
		add_action('init', array(&$this, 'init_options'));

		// Check requirements
		add_action('init', array(&$this, 'requirements'), 100);

		// Install/ uninstall
		add_action('activate_' . $this->plugin['basename'], array(&$this, 'install'));
		add_action('deactivate_' . $this->plugin['basename'], array(&$this, 'uninstall'));

		// === POSTS / PAGES ===

		// == Allow only one category per post [one_category_only] / Disable categories for posts [disable_categories] / Enforce <!--more--> tag [enforce_more] ==
		if(!empty($this->options['one_category_only']) || !empty($this->options['disable_categories']) || !empty($this->options['enforce_more'])) {
			add_action('admin_init', function() {
				wp_register_script('th23-specials-post-list', $this->plugin['dir_url'] . 'th23-specials-post-list.js', array('jquery'), $this->plugin['version'], true);
				wp_register_script('th23-specials-post-edit', $this->plugin['dir_url'] . 'th23-specials-post-edit.js', array('jquery'), $this->plugin['version'], true);
				wp_register_script('th23-specials-block-edit', $this->plugin['dir_url'] . 'th23-specials-block-edit.js', array('jquery', 'wp-plugins', 'wp-editor', 'wp-data', 'wp-blocks', 'react'), $this->plugin['version'], true);
			});
			add_action('admin_enqueue_scripts', array(&$this, 'load_admin_js'));
			add_action('admin_print_scripts', array(&$this, 'localize_admin_js'));
		}

		// == Show category selection along hierarchy, not checked ones on top [order_categories] ==
		if(!empty($this->options['order_categories'])) {
			add_filter('wp_terms_checklist_args', array(&$this, 'category_checklist'));
		}

		// == Sort entries for link creation: categories, pages, posts [editor_link_creation] ==
		// todo: check with more real life entries in block editor, sorting seems to be more meaningful here by default, but filters seem not to have an effect
		if(!empty($this->options['editor_link_creation'])) {
			// remove pages from in between posts in editor
			add_filter('wp_link_query_args', array(&$this, 'editor_remove_page_links'));
			// prepend selection of existing content in editor with all matching categories (incl. empty ones) and pages
			add_filter('wp_link_query', array(&$this, 'editor_add_cat_page_links'), 10, 2);
		}

	}

	// Setup th23 Admin class
	function setup_admin_class() {

		// enhance plugin info with generic plugin data
		// note: make sure function exists as it is loaded late only, if at all - see https://developer.wordpress.org/reference/functions/get_plugin_data/
		if(!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$this->plugin['data'] = get_plugin_data($this->plugin['file']);

		// admin class is language agnostic, except translations in parent i18n variable
		// note: need to populate $this->i18n earliest at init hook to get user locale
		$this->i18n = array(
			'Plugin' => __('Plugin', 'th23-specials'),
			'Settings' => __('Settings', 'th23-specials'),
			/* translators: parses in plugin version number */
			'Version %s' => __('Version %s', 'th23-specials'),
			/* translators: parses in plugin name */
			'Copy from %s' => __('Copy from %s', 'th23-specials'),
			'Support' => __('Support', 'th23-specials'),
			'Done' => __('Done', 'th23-specials'),
			'Settings saved.' => __('Settings saved.', 'th23-specials'),
			'+' => __('+', 'th23-specials'),
			'-' => __('-', 'th23-specials'),
			'Save Changes' => __('Save Changes', 'th23-specials'),
			/* translators: parses in plugin author name / link */
			'By %s' => __('By %s', 'th23-specials'),
			'View details' => __('View details', 'th23-specials'),
			'Visit plugin site' => __('Visit plugin site', 'th23-specials'),
			'Error' => __('Error', 'th23-specials'),
			/* translators: 1: option name, 2: opening a tag of link to support/ plugin page, 3: closing a tag of link */
			'Invalid combination of input field and default value for "%1$s" - please %2$scontact the plugin author%3$s' => __('Invalid combination of input field and default value for "%1$s" - please %2$scontact the plugin author%3$s', 'th23-specials'),
			'Updates' => __('Updates', 'th23-specials'),
			'If disabled or unreachable, updates will use default WordPress repository' => __('If disabled or unreachable, updates will use default WordPress repository', 'th23-specials'),
			/* translators: parses in repository url */
			'Update from %s' => __('Update from %s', 'th23-specials'),
		);

	}
	function error_admin_class() {
		/* translators: parses in names of 1: class which failed to load */
		echo '<div class="notice notice-error"><p style="font-size: 14px;"><strong>' . esc_html($this->plugin['data']['Name']) . '</strong></p><p>' . esc_html(sprintf(__('Failed to load %1$s class', 'th23-specials'), 'th23 Admin')) . '</p></div>';
	}

	// Load plugin options
	function init_options() {

		// Settings: Screen options
		// note: default can handle boolean, integer or string
		$this->plugin['screen_options'] = array(
			'hide_description' => array(
				'title' => __('Hide settings descriptions', 'th23-specials'),
				'default' => false,
			),
		);

		// Settings: Define plugin options
		$this->plugin['options'] = array();

		// mail_smtp

		$this->plugin['options']['mail_smtp'] = array(
			'section' => __('Mail', 'th23-specials'),
			'title' => __('Use SMTP server', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Use SMTP server to send mails, instead PHP mail() function', 'th23-specials'),
			),
			'description' => __('Recommended: Test settings manually - no verification done by plugin!', 'th23-specials'),
			'attributes' => array(
				'data-childs' => '.option-smtp_secure,.option-smtp_server,.option-smtp_port,.option-smtp_auth,.option-smtp_from_name,.option-smtp_reply_mail,.option-smtp_reply_name',
			),
		);

		// mail - smtp_secure

		$this->plugin['options']['smtp_secure'] = array(
			'title' => __('Connection method', 'th23-specials'),
			'element' => 'dropdown',
			'default' => array(
				'single' => 'ssl',
				'ssl' => __('SSL', 'th23-specials'),
				'tls' => __('TLS', 'th23-specials'),
				'none' => __('Unsecured', 'th23-specials'),
			),
		);

		// mail - smtp_server

		$this->plugin['options']['smtp_server'] = array(
			'title' =>  __('Server address', 'th23-specials'),
			'default' => '',
			'description' => __('IP address or host name, eg. mail.host.com', 'th23-specials'),
		);

		// mail - smtp_port

		$this->plugin['options']['smtp_port'] = array(
			'title' =>  __('Port number', 'th23-specials'),
			'default' => 465,
			'attributes' => array(
				'class' => 'small-text',
			),
		);

		// mail - smtp_auth

		$this->plugin['options']['smtp_auth'] = array(
			'title' => __('Authentication', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Authentication required for SMTP server', 'th23-specials'),
			),
			'attributes' => array(
				'data-childs' => '.option-smtp_user,.option-smtp_pass',
			),
		);

		// mail - smtp_user

		$this->plugin['options']['smtp_user'] = array(
			'title' =>  __('User name', 'th23-specials'),
			'default' => '',
			'description' => __('Note: Should be done via setup of dedicated mailbox on server', 'th23-specials'),
		);

		// mail - smtp_pass

		$this->plugin['options']['smtp_pass'] = array(
			'title' =>  __('Password', 'th23-specials'),
			'default' => '',
		);

		// mail - smtp_from_name

		$this->plugin['options']['smtp_from_name'] = array(
			'title' =>  __('Sender name', 'th23-specials'),
			'default' => '',
		);

		// mail - smtp_reply_mail

		$this->plugin['options']['smtp_reply_mail'] = array(
			'title' =>  __('Reply mail address', 'th23-specials'),
			'default' => '',
		);

		// mail - smtp_reply_name

		$this->plugin['options']['smtp_reply_name'] = array(
			'title' =>  __('Reply name', 'th23-specials'),
			'default' => '',
		);

		// mail_prefix

		$this->plugin['options']['mail_prefix'] = array(
			'title' =>  __('Mail subject prefix', 'th23-specials'),
			'default' => '',
			/* translators: %s: blogname as defined for currenty blog in general settings enclosed in [] shown as <code> */
			'description' => sprintf(__('Prefix added by WP default will be removed - to keep the default, add %s above', 'th23-specials'), '<code>[' . get_option('blogname') . '] </code>'),
		);

		// disable_revisions

		$this->plugin['options']['disable_revisions'] = array(
			'section' => __('Posts / Pages', 'th23-specials'),
			'title' => __('Revisions', 'th23-specials'),
			'default' => 0,
			'attributes' => array(
				'class' => 'small-text',
			),
			/* translators: %s: number to add for respective setting */
			'description' => sprintf(__('Maximum number of revisions to keep per entry - set to %1$s for unlimited, set to %2$s to disable revisions', 'th23-specials'), '<code>-1</code>', '<code>0</code>'),
		);

		// edit_admin_only

		$this->plugin['options']['edit_admin_only'] = array(
			'title' =>  __('Edit restriction', 'th23-specials'),
			'render' => function() { return '<div>' . __('Restricted posts / pages', 'th23-specials') . '</div>'; },
			'default' => '',
			'description' => __('Only allow admins to edit certain posts / pages - list of respective IDs, separated by comma, empty for no restrictions', 'th23-specials'),
		);

		// title_markup

		$this->plugin['options']['title_markup'] = array(
			'title' =>  __('Titles', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				/* translators: %s: add highlighting syntax "*" around word in between as <code> */
				1 => sprintf(__('Allow %1$shighlighting%2$s in post and page titles', 'th23-specials'), '<code>*', '*</code>'),
			),
			/* translators: %s: add highlighting syntax "*" around word in between as <code> */
			'description' => sprintf(__('Replaces %1$smatching stars%2$s in titles by enclosing %3$s tags allowing styling by theme via CSS selector %4$s', 'th23-specials'), '<code>*', '*</code>', '<code>&lt;span&gt;&lt;/span&gt;</code>', '<code>.entry-title span</code>'),
		);

		// one_category_only

		$this->plugin['options']['one_category_only'] = array(
			'title' => __('Categories', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Allow to only specify one category per post', 'th23-specials'),
			),
			'description' => __('Note: Only affects handling via default editors', 'th23-specials') . '<br />' . __('Note: Only affects new assignments to a category, existing ones remain in place', 'th23-specials') . $this->one_category_only_check(),
		);

		// disable_categories

		$this->plugin['options']['disable_categories'] = array(
			'render' => function() { return '<div>' . __('Disabled categories for posts', 'th23-specials') . '</div>'; },
			'default' => '',
			'description' => __('Specified categories are not available for posts to be assigned in editor - list of respective IDs, separated by comma, empty for no restrictions', 'th23-specials') . '<br />' . __('Note: See notes for option above', 'th23-specials'),
		);

		// order_categories

		$this->plugin['options']['order_categories'] = array(
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Order categories in post edit screen along hierarchy', 'th23-specials'),
			),
		);

		// enable_excerpts

		$this->plugin['options']['enable_excerpts'] = array(
			'title' => __('Excerpts', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Enable excerpts for pages', 'th23-specials'),
			),
			'description' => __('Note: Wordpress default allows excerpts only for posts', 'th23-specials'),
		);

		// sticky_first

		$this->plugin['options']['sticky_first'] = array(
			'title' => __('Sticky posts', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Display sticky posts on top of list eg on archive pages', 'th23-specials'),
			),
			'description' => __('Warning: Might break previous/next post navigation, as sticky posts might be shown out of order eg in archive pages', 'th23-specials'),
		);

		// disable_author_links

		$this->plugin['options']['disable_author_links'] = array(
			'title' => __('Links', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 0,
				0 => '',
				1 => __('Disable author links and archives', 'th23-specials'),
			),
			'description' => __('Note: Keeps showing author names with respective contents', 'th23-specials'),
		);

		// editor_link_creation

		$this->plugin['options']['editor_link_creation'] = array(
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Separate pages and posts in link creation popup within editor', 'th23-specials'),
			),
		);

		// enforce_more

		$this->plugin['options']['enforce_more'] = array(
			'title' => __('Read more', 'th23-specials'),
			/* translators: %s: "<!--more-->" tag shown as <code> - sentence is extended by option dropdown "None" / "Posts and pages" / "Only posts" */
			'render' => function() { return sprintf(__('Require %s tag for', 'th23-specials'), '<code>&lt;!--more--&gt;</code>') . ' '; },
			'element' => 'dropdown',
			'default' => array(
				'single' => 'post_page',
				'' => __('None', 'th23-specials'),
				'post_page' => __('Posts and pages', 'th23-specials'),
				'post' => __('Only posts', 'th23-specials'),
			),
			'description' => __('Enforce tag for content longer than 5 paragraphs - on frontend only an excerpt until this tag will be shown on overview pages like search, archives, etc', 'th23-specials') . $this->enforce_more_check(),
		);

		// disable_wp_filter

		$this->plugin['options']['disable_wp_filter'] = array(
			'title' => __('Default filter', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Disable default "Wordpress" filters', 'th23-specials'),
			),
			'description' => __('Note: These filters only ensure "Wordpress" is written in the way the WP team wants it to be (stupid, leave that to your users)', 'th23-specials'),
		);

		// disable_version

		$this->plugin['options']['disable_version'] = array(
			'section' => __('Website', 'th23-specials'),
			'title' => __('Version info', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Remove version information from HTML', 'th23-specials'),
			),
		);

		// disable_links

		$this->plugin['options']['disable_links'] = array(
			'title' => __('Header links', 'th23-specials'),
			'description' => __('Note: This setting has no effect, if controlled by theme', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Remove unnecessary links from HTML head section', 'th23-specials'),
			),
		);

		// disable_jquery_migrate

		$this->plugin['options']['disable_jquery_migrate'] = array(
			'title' => __('jQuery Migrate', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Do not load jQuery Migrate', 'th23-specials'),
			),
			'description' => __('Note: Selection can be overruled by other plugins or theme', 'th23-specials'),
		);

		// disable_comment_css

		$this->plugin['options']['disable_comment_css'] = array(
			'title' => __('Inline CSS', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Remove default inline comment CSS', 'th23-specials'),
			),
			'description' => __('Note: This setting has no effect, if controlled by theme', 'th23-specials'),
		);

		// disable_emojis

		$this->plugin['options']['disable_emojis'] = array(
			'title' => __('Emojis', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Disable default emoji support', 'th23-specials'),
			),
			'description' => __('Note: This setting has no effect, if controlled by theme', 'th23-specials'),
		);

		// disable_oembed

		$this->plugin['options']['disable_oembed'] = array(
			'title' => __('oEmbed', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Disable oEmbed functionality', 'th23-specials'),
			),
			'description' => __('Note: This setting has no effect, if controlled by theme', 'th23-specials'),
		);

		// disable_legacy_contacts

		$this->plugin['options']['disable_legacy_contacts'] = array(
			'section' => __('User', 'th23-specials'),
			'title' => __('Contact options', 'th23-specials'),
			'element' => 'checkbox',
			'default' => array(
				'single' => 1,
				0 => '',
				1 => __('Remove legacy contact methods', 'th23-specials'),
			),
			'description' => __('Older installations include AIM, YIM and Jabber by default', 'th23-specials'),
		);

		// Settings: Define presets for template option values (pre-filled, but changable by user)
		$this->plugin['presets'] = array();

	}

	// Show post with more categories assigned [one_category_only]
	function one_category_only_check() {
		$html = '';
		if(empty($_REQUEST['check']) || 'multiple_categories' !== $_REQUEST['check']) {
			$html .= '<br /><a href="' . add_query_arg('check', 'multiple_categories') . '#one_category_only-row">' . __('Show posts assigned to more than one category', 'th23-specials') . '</a>';
		}
		else {
			global $wpdb;
			$result = $wpdb->get_results('SELECT p.ID as id, p.post_title as title, COUNT(r.term_taxonomy_id) as cats FROM ' . $wpdb->prefix . 'term_relationships r LEFT JOIN ' . $wpdb->prefix . 'term_taxonomy c ON c.term_taxonomy_id=r.term_taxonomy_id LEFT JOIN ' . $wpdb->prefix . 'posts p ON p.ID=r.object_id WHERE p.post_type="post" AND c.taxonomy="category" GROUP BY r.object_id HAVING COUNT(r.term_taxonomy_id)>1', OBJECT);
			$html .= '<br /><strong>' . __('Posts assigned to more than one category:', 'th23-specials') . '</strong>';
			if(!is_array($result) || 0 == count($result)) {
				$html .= '<br />&bull; ' . __('Nothing found', 'th23-specials');
			}
			else {
				foreach($result as $post) {
					$title = (!empty($post->title)) ? esc_html($post->title) : '<em>' . __('No title', 'th23-specials') . '</em>';
					$html .= '<br />&bull; <a href="/wp-admin/post.php?post=' . $post->id . '&action=edit">' . $title . '</a>';
				}
			}
		}
		return $html;
	}

	// Show posts / pages without more tag [enforce_more]
	function enforce_more_check() {
		$html = '';
		$links = array();
		if(empty($_REQUEST['check']) || 'posts_missing_more' !== $_REQUEST['check']) {
			$links[] = '<a href="' . add_query_arg('check', 'posts_missing_more') . '#enforce_more-row">' . __('Show posts without "read more"', 'th23-specials') . '</a>';
		}
		else {
			$missing_more = 'post';
		}
		if(empty($_REQUEST['check']) || 'pages_missing_more' !== $_REQUEST['check']) {
			$links[] = '<a href="' . add_query_arg('check', 'pages_missing_more') . '#enforce_more-row">' . __('Show pages without "read more"', 'th23-specials') . '</a>';
		}
		else {
			$missing_more = 'page';
		}
		$html .= '<br />' . implode(' | ', $links);
		if(!empty($missing_more)) {
			$result = get_posts(array('numberposts' => -1, 'post_type' => $missing_more, 's' => '-<!--more-->', 'search_columns' => array('post_content')));
			$html .= '<br /><strong>';
			$html .= ('post' == $missing_more) ? __('Posts without "read more":', 'th23-specials') : __('Pages without "read more":', 'th23-specials');
			$html .= '</strong>';
			if(!is_array($result) || 0 == count($result)) {
				$html .= '<br />&bull; ' . __('Nothing found', 'th23-specials');
			}
			else {
				foreach($result as $post) {
					$title = (!empty($post->post_title)) ? esc_html($post->post_title) : '<em>' . __('No title', 'th23-specials') . '</em>';
					$html .= '<br />&bull; <a href="/wp-admin/post.php?post=' . $post->ID . '&action=edit">' . $title . '</a>';
				}
			}
		}
		return $html;
	}

	// Install
	function install() {

		// Prefill values in an option template, keeping them user editable (and therefore not specified in the default value itself)
		// need to check, if items exist(ed) before and can be reused - so we dont' overwrite them (see uninstall with delete_option inactive)
		if(isset($this->plugin['presets'])) {
			if(!isset($this->options) || !is_array($this->options)) {
				$this->options = array();
			}
			$this->options = array_merge($this->plugin['presets'], $this->options);
		}
		// Set option values, including current plugin version (invisibly) to be able to detect updates
		$this->options['version'] = $this->plugin['version'];
		update_option($this->plugin['slug'], $this->admin->get_options($this->options));
		$this->options = (array) get_option($this->plugin['slug']);

	}

	// Uninstall
	function uninstall() {

		// NOTICE: To keep all settings etc in case the plugin is reactivated, return right away - if you want to remove previous settings and data, comment out the following line!
		return;

		// Delete option values
		delete_option($this->plugin['slug']);

	}

	// Requirements - checks
	function requirements() {
		// check requirements only on relevant admin pages
		global $pagenow;
		if(empty($pagenow)) {
			return;
		}
		if('index.php' == $pagenow) {
			// admin dashboard
			$context = 'admin_index';
		}
		elseif('plugins.php' == $pagenow) {
			// plugins overview page
			$context = 'plugins_overview';
		}
		elseif($this->plugin['settings']['base'] == $pagenow && !empty($_GET['page']) && $this->plugin['slug'] == $_GET['page']) {
			// plugin settings page
			$context = 'plugin_settings';
		}
		else {
			return;
		}

		// customization: Check - plugin not designed for multisite setup
		if(is_multisite()) {
			$this->plugin['requirement_notices']['multisite'] = '<strong>' . __('Warning', 'th23-specials') . '</strong>: ' . __('Your are running a multisite installation - the plugin is not designed for this setup and therefore might not work properly', 'th23-specials');
		}

		// allow further checks (without re-assessing $context)
		do_action('th23_specials_requirements', $context);

	}

	// == customization: from here on plugin specific ==

	// === POSTS / PAGES ===

	// == Allow only one category per post [one_category_only] / Disable categories for posts [disable_categories] / Enforce <!--more--> tag [enforce_more] ==

	// Enqueue JS if required for current admin page
	function load_admin_js() {
		// determine admin screen
		global $current_screen;
		if(empty($current_screen) || empty($current_screen->base) || empty($current_screen->post_type)) {
			return;
		}
		// quick edit (list / overview)
		if('edit' == $current_screen->base && 'post' == $current_screen->post_type) {
			$this->data['load_admin_js'] = 'th23-specials-post-list';
		}
		// single post (classic / block)
		elseif('post' == $current_screen->base && in_array($current_screen->post_type, array('post', 'page'))) {
			$this->data['load_admin_js'] = (!empty($current_screen->is_block_editor)) ? 'th23-specials-block-edit' : 'th23-specials-post-edit';
		}
		// load js
		if(!empty($this->data['load_admin_js'])) {
			$this->data['post_type'] = $current_screen->post_type;
			wp_enqueue_script($this->data['load_admin_js']);
		}
	}

	// Localize JS if required
	function localize_admin_js() {
		if(empty($this->data['load_admin_js'])) {
			return;
		}
		$localize = array(
			'one_category_only' => (!empty($this->options['one_category_only'])) ? 1 : 0,
			'one_category_only_notice' => esc_html(__('More than one category is assigned, but only one is allowed. Saving changes will limit this to one category.', 'th23-specials')),
			'disable_categories' => (!empty($this->options['disable_categories'])) ? $this->options['disable_categories'] : '',
			'disable_categories_notice' => esc_html(__('A disabled category is currently assigned. The assignment will remain, but once you remove it you will not be able to add it again.', 'th23-specials')),
			'categories_title' => esc_html(__('Categories', 'th23-specials')),
			'post_type' => $this->data['post_type'],
			'enforce_more' => (!empty($this->options['enforce_more'])) ? $this->options['enforce_more'] : '',
			'longer_than' => 5, // paragraphs
			'insert_after' => 3, // n-th paragraph
			'enforce_more_notice' => esc_html(__('Missing "read more" was added automatically - please review and adjust if required', 'th23-specials')),
			'enforce_more_error' => esc_html(__('Missing "read more" - please add before saving', 'th23-specials')),
		);
		// get all categories, including empty ones, consider "wp_terms_checklist_args" filter
		$localize['categories_selection'] = json_encode($this->categories_selection(apply_filters('wp_terms_checklist_args', array('parent' => 0, 'hide_empty' => false))));
		// determine categories assigned to current post
		$categories_assigned = array();
		global $post_id;
		// new post
		if(empty($post_id)) {
			$categories_assigned[] = (string) get_option('default_category');
		}
		else {
			foreach(get_the_category() as $cat) {
				$categories_assigned[] = (string) $cat->term_id;
			}
		}
		$localize['categories_assigned'] = implode(',', $categories_assigned);
		wp_localize_script($this->data['load_admin_js'], 'th23_specials', $localize);
	}

	// Localize JS helper function to provide all categories in required format
	function categories_selection($args) {
		$cats = array();
		foreach(get_categories($args) as $cat) {
			$cats[] = array(
				'id' => (string) $cat->term_id,
				'title' => esc_html($cat->name),
				'children' => $this->categories_selection(array_merge($args, array('parent' => $cat->term_id))),
			);
		}
		return $cats;
	}

	// == Show category selection along hierarchy, not checked ones on top [order_categories] ==

	// Adjust category selection in meta box (classic) / replacement panel (block)
	// note: default block editor category selection fails to use this filter
	function category_checklist($args) {
		$args['checked_ontop'] = false;
		return $args;
	}

	// == Sort entries for link creation: categories, pages, posts [editor_link_creation] ==

	// Remove pages from in between posts in editor
	function editor_remove_page_links($query) {
		if(($key = array_search('page', $query['post_type'])) !== false) {
			unset($query['post_type'][$key]);
		}
		return $query;
	}

	// Prepend selection of existing content in editor with all matching categories (incl. empty ones) and pages
	function editor_add_cat_page_links($result, $query) {
		if(!isset($query['offset']) || empty($query['offset'])) {
			// pages
			$pages = get_pages(array('hierarchical' => 0, 'sort_column' => 'post_title', 'sort_order' => 'desc'));
			foreach($pages as $page) {
				if(!isset($query['s']) || empty($query['s']) || strpos(strtolower($page->post_title), strtolower($query['s'])) !== false) {
					array_unshift($result, array('ID' => 'page_' . $page->ID, 'title' => esc_html($page->post_title), 'permalink' => get_page_link($page->ID), 'info' => esc_html__('Page', 'th23-specials')));
				}
			}
			// categories (handle last, so they end up first in the list)
			$cats = get_categories(array('hide_empty' => 0, 'exclude' => 1, 'orderby' => 'name', 'order' => 'desc'));
			foreach($cats as $cat) {
				if(!isset($query['s']) || empty($query['s']) || strpos(strtolower($cat->name), strtolower($query['s'])) !== false) {
					array_unshift($result, array('ID' => 'cat_' . $cat->term_id, 'title' => esc_html($cat->name), 'permalink' => get_category_link($cat->term_id), 'info' => esc_html__('Category', 'th23-specials')));
				}
			}
		}
		return $result;
	}

}

?>
