<?php

// safety
die();

// === Config: plugin information ===
// note: for further explanations, see comments in th23 Plugin Info class and/or example in class repository
$plugin = array(

	// (external) assets base (optional, only for Github "readme.md" and own updater "update.json")
	'assets_base' => 'https://raw.githubusercontent.com/th23x/th23-specials/refs/heads/main/',

	// plugin basics (mandatory, only "icons" are optional)
	'name' => 'th23 Specials',
	'icons' => array(
		'square' => 'assets/icon-square.png',
		'horizontal' => 'assets/icon-horizontal.png',
	),
	'slug' => 'th23-specials',
	'tags' => array(
		'essentials',
		'filter',
		'smtp',
		'header',
	),

	// contributors (incl one mandatory author), (mandatory) homepage url, (optional) donate url, (optional) support url
	// note: "user" has to be a valid username on https://profiles.wordpress.org/username which is auto-linked to the WP profile
	'contributors' => array(
		array(
			'user' => 'th23',
			'name' => 'Thorsten',
			'url' => 'https://thorstenhartmann.de',
			'avatar' => 'https://thorstenhartmann.de/avatar.png',
			'author' => true,
		),
	),
	'homepage' => 'https://github.com/th23x/th23-specials',
	// 'donate_link' => '',
	// 'support_url' => '',

	// latest version (mandatory)
	'last_updated' => '2025-04-24 10:15:00',
	'version' => '6.0.1',
	'download_link' => 'https://github.com/th23x/th23-specials/releases/latest/download/th23-specials-v6.0.1.zip',

	// requirements (mandatory)
	'requires_php' => '8.0',
	'requires' => '4.2', // min WP version
	'tested' => '6.8', // max tested WP version

	// license (mandatory, but "description" optional)
	'license' => array(
		'GPL-3.0' => 'https://github.com/th23x/th23-specials/blob/main/LICENSE',
		'description' => 'You are free to use this code in your projects as per the `GNU General Public License v3.0`. References to this repository are of course very welcome in return for my work 😉',
	),

	// header banner (optional)
	'banners' => array(
		'low' => 'https://raw.githubusercontent.com/th23x/th23-specials/refs/heads/main/assets/banner-772x250.jpg',
		'high' => 'https://raw.githubusercontent.com/th23x/th23-specials/refs/heads/main/assets/banner-1544x500.jpg',
	),

	// description
	// - description [json] = summary (mandatory) + intro (optional) + usage (optional)
	// - introduction [git] = intro (optional) + screenshots (mandatory)
	// - description [wp] = intro (optional) + usage (optional)

	// summary (mandatory) - max 150 characters (wp restriction)
	'summary' => 'Essentials to customize Wordpress via simple settings, SMTP, title highlight, category selection, more separator, sticky posts, remove clutter, ...',
	// intro (optional)
	'intro' => 'Customize your Wordpress website even more to your needs via **simple admin settings** instead of code modifications.

Setting up new websites from time to time I realized, that I keep using **similar sets of modifications over and over again**. This plugin gives you a wide range of modifications at hand **without any core or theme code changes** - making them persistent across updates or switching to another theme.

th23 Specials core features include (all available as options in admin dashboard):

* Mail sending via **SMTP server** instead of default PHP mail function - and with custom subject prefix
* **Revision and edit restrictions** for posts and pages
* **Highlighting in titles** of posts and pages - for custom styling via theme
* **Category selection** via radio button, forcing single category assignment and option to restrict available categories - via Classic and Gutenberg / Block editor
* **Excerpts** for pages
* **Sticky posts** also sticky on archive pages
* Disabling **author links and archives**
* Enforcing usage of **more tag / block / separator** in posts and pages - preventing too long full text display on home and overview pages
* Disable various **headers** - core version info, links, jQuery Migrate, inline CSS, emojis, oEmbed, legacy contact options',
	// screenshots (mandatory)
	'screenshots' => array(
		1 => array(
			'src' => 'assets/screenshot-1.jpg',
			'caption' => 'Settings section in the admin dashboard with easy to reach options',
		),
		2 => array(
			'src' => 'assets/screenshot-2.jpg',
			'caption' => 'Category selection (when limited to one per post) via radion buttons in the quick edit view',
		),
		3 => array(
			'src' => 'assets/screenshot-3.jpg',
			'caption' => 'Enforced "read more" block in the Gutenberg / block editor',
		),
	),
	// usage (optional)
	'usage' => 'Simply install plugin and choose customizations required from the plugin settings page. Few options involve further actions to achieve required result - **see below and FAQ section** for more details.

For **highlighting in post / page titles**, put part to highlight in between `*matching stars*` in the editor. This part will be enclosed by `<span></span>` tags in the HTML on the frontend, allowing styling by theme via the CSS selector `.entry-title span`.

> [!NOTE]
> Some options change important core functionality of Wordpress - make sure you **properly test your website** before usage in production environment!',

	// setup (optional)
	// - installation [wp] = setup [git]
	'setup' => 'For a manual installation upload extracted `th23-specials` folder to your `wp-content/plugins` directory.

The plugin is **configured via its settings page in the admin area**. Find all options under `Settings` -> `th23 Specials`. The options come with a description of the setting and its behavior directly next to the respective settings field.',

	// frequently asked questions (mandatory)
	'faq' => array(
		'non_compliance' => array(
			'q' => 'Is there a way to identify **existing posts / pages that do not comply** with the one category only requirement or that are missing the "read more" block / tag?',
			'a' => 'Yes, there are links in the descriptions on the th23 Specials settings page, **next to the respective option** to search for "non-compliant" posts / pages.

Upon a click on this link you will see all currently non-compliant posts / pages. You can modify these by clicking on their titles, which loads them into your default editor.',
		),
		'no_effect' => array(
			'q' => 'Some **settings seem to have no effect** - eg oEmbed features are still active depsite deactivated?',
			'a' => 'This might be happening as **some options can be "overruled"** by settings by your theme. For settings that might be affected, please see the description on the settings page.

To change such settings, please **check your active theme** and adjust them there, if required.',
		),
	),

	// changelog (mandatory, sorted by version, content can be a string or an array for a list)
	'changelog' => array(
		'v6.0.1' => array(
			'fix: update th23 Admin class to v1.6.1',
		),
		'v6.0.0' => array(
			'n/a: first public release',
		),
	),

	// upgrade_notice (mandatory, sorted by version, content can be a string or an array for a list)
	'upgrade_notice' => array(
		'v6.0.0' => 'n/a',
	),

);

// === Do NOT edit below this line for config ===

// safety
define('ABSPATH', 'defined');

// load class, generate plugin info
require_once(__DIR__ . '/inc/th23-plugin-info-class.php');
$th23_plugin_info = new th23_plugin_info();
$th23_plugin_info->generate($plugin);

?>
