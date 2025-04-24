<?php
/*
th23 Plugin Info
Support functionality to create readme.txt (WordPress.org), README.md (Github) and update.json (online updater)
Version: 0.4.0

Coded 2025 by Thorsten Hartmann (th23)
https://th23.net/

see plugin-info-example.php file in source repository for documentation of options
*/

// Security - exit if accessed directly
if(!defined('ABSPATH')) {
    exit;
}

class th23_plugin_info {

	public $version = '0.4.0';

	// output options
	private $modes = array(
		'json' => array('file' => 'update.json', 'title' => 'update.json (th23 Admin Upgrader)', 'function' => 'update_json'),
		'git' => array('file' => 'README.md', 'title' => 'README.md (github.com)', 'function' => 'readme_md'),
		'wp' => array('file' => 'readme.txt', 'title' => 'readme.txt (wordpress.org)', 'function' => 'readme_txt'),
	);

	// plugin information
	private $plugin;

	function __construct() {
	}

	// generate output
	public function generate($plugin) {

		// get plugin information
		if(empty($plugin)) {
			die('Missing plugin information');
		}
		$this->plugin = $plugin;

		// determine mode
		$mode = (isset($_GET['mode'])) ? (string) $_GET['mode'] : '';
		$file = (isset($_GET['file'])) ? (('server' === $_GET['file']) ? 'server' : 'download') : '';
		if(empty($mode)) {
			// empty and invalid mode will result in same output ie empty page
			die();
		}
		elseif('all' == $mode && 'download' == $file) {
			die('Files can only be downloaded one by one');
		}

		// save or download file(s) or show content
		$title = '';
		$html = '';
		$i = 0;
		foreach($this->modes as $id => $details) {
			if($id != $mode && 'all' != $mode) {
				continue;
			}
			if('download' == $file) {
				$content = call_user_func(array($this, $details['function']));
				header('Content-Description: File Transfer');
				header('Content-Type: text/plain');
				header('Content-Disposition: attachment; filename=' . $details['file']);
				header('Content-Transfer-Encoding: binary');
				header('Content-Length: ' . strlen($content));
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Expires: 0');
				header('Pragma: public');
				echo $content;
			}
			elseif('server' == $file) {
				if('all' != $mode) {
					$title = ' &bull; ' . $details['title'];
				}
				// note: writing to server is safe as it only rewrites what is distributed anyhow with the plugin (except during development) and only uses content of this file ie does not write any external input onto the server
				if(false !== file_put_contents($details['file'], call_user_func(array($this, $details['function'])))) {
					$html .= '<p><strong style="color: green;">Done</strong>: <strong>' . $details['file'] . '</strong> created</p>' . "\n";
				}
				else {
					$html .= '<p><strong style="color: red;">Error</strong>: Failed to create <strong>' . $details['file'] . '</strong></p>' . "\n";
				}
			}
			else {
				if('all' == $mode) {
					$i++;
					$margin = ($i > 1) ? ' margin-top: 2em;' : '';
					$html .= '<div style="margin: 1em;' . $margin . '"><strong>' . $details['title'] . '</strong>' . "\n";
					$style = ' style="border: 1px solid lightgrey; overflow: auto; padding: .5em;"';
				}
				else {
					$title = ' &bull; ' . $details['title'];
					$style = '';
				}
				$html .= '<pre' . $style . '><code>';
				$html .= htmlspecialchars(call_user_func(array($this, $details['function'])));
				$html .= '</code></pre>' . "\n";
				if('all' == $mode) {
					$html .= '</div>' . "\n";
				}
			}
		}
		if(!empty($html)) {
			echo '<!doctype html>' . "\n";
			echo '<html>' . "\n";
			echo '<head>' . "\n";
			echo '<meta charset="UTF-8">' . "\n";
			echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
			echo '<title>Plugin Info' . $title . '</title>' . "\n";
			echo '</head>' . "\n";
			echo '<body>' . "\n";
			echo $html;
			echo '</body>' . "\n";
			echo '</html>' . "\n";
		}

	}

	// add plugin version to images to prevent extensive browser caching
	private function add_v($link) {
		$link .= (str_contains($link, '?')) ? '&' : '?';
		return $link .= 'v=' . $this->plugin['version'];
	}

	// WordPress.org online updater via "plugins_api" hook, expects to receive information as pre-formatted html, thus allowing more "fancy" styling than WP.org repository - enhance markdown / convert to html
	// note: fields supporting html are: summary, intro, usage, setup, faq - answers, changelog - change, screenshots - caption, upgrade_notice - notice, license - description
	private function md2html($content, $blocks = true) {

		if($blocks) {

			// blockquotes: incl Github special boxes
			/*
			> [!NOTE/TIP/IMPORTANT/WARNING/CAUTION]
			> Highlights information that users should be aware
			*/
			$content = preg_replace_callback("/^> (\[!([A-Z]*?)\]\n|.*?)?(.*?)(\n\n|\z)/ms", function ($matches) {
				$box_type = (!empty($matches[2])) ? $matches[2] : 'DEFAULT';
				$box_header = ('DEFAULT' != $box_type) ? '<div style="color: _color_' . $box_type . '_;">_icon_' . $box_type . '_ <strong>' . $box_type . '</strong></div><p>' : '<p>' . $matches[1];
				// blockquotes: remove leading "> " for lines within box
				$box_content = preg_replace("/(^> *)/m", "", $matches[3]);
				return '<blockquote style="padding: 0 0.5em; border-left: 5px solid _color_' . $box_type . '_;">' . $box_header . $box_content . '</p></blockquote>' . $matches[4];
			}, $content);
			// blockquotes: colors and icons for Github special boxes
			$replacements = array(
				'_color_DEFAULT_' => 'rgb(150, 150, 150)',
				'_color_NOTE_' => 'rgb(9, 105, 218)',
				'_color_TIP_' => 'rgb(26, 127, 55)',
				'_color_IMPORTANT_' => 'rgb(130, 80, 223)',
				'_color_WARNING_' => 'rgb(154, 103, 0)',
				'_color_CAUTION_' => 'rgb(207, 34, 46)',
				'_icon_NOTE_' => '&#128488;&#65039;',
				'_icon_TIP_' => '&#128161;',
				'_icon_IMPORTANT_' => '&#10071;',
				'_icon_WARNING_' => '&#9888;&#65039;',
				'_icon_CAUTION_' => '&#128721;',
			);
			$content = str_replace(array_keys($replacements), $replacements, $content);

			// code blocks: replace "```language\n...```" with "<pre><code>...</code></pre>" - "s" modifier to capture "..." across lines
			$content = preg_replace_callback("/```.*?\n(.*?)(\n)?```/s", function ($matches) {
				// code blocks: replace line breaks in code block with "<br>"
				return '<pre><code>' . str_replace("\n", '<br>', $matches[1]) . '</code></pre>';
			}, $content);

			$replacements = array(

				// list elements: wrap lines starting with "* " in "li" tags - "m" modifier to capture start ("^") and end ("$") of lines
				"/^\* (.*?)$/m" => "<li>$1</li>",
				// list elements: remove remaining "\n" (new lines) between list elements
				"/<\/li>\n<li>/" => "</li><li>",
				// list elements: wrap set of "<li>...</li>" (list elements) in "ul" tags - "s" modifier to capture "..." across single (!) linebreaks
				"/(<li>.*?<\/li>)\n\n/s" => "<ul>$1</ul>\n\n",

				// linebreaks: remove "\n" at beginning, end or in between block elements
				"/(\A\n+|\n+\z)/" => "",
				"/(<\/blockquote>|<\/ul>|<\/pre>|<\/h\d>|<\/p>)\n+(<blockquote|<ul|<pre|<h\d|<p)/" => "$1$2",
				// linebreaks: start with "<p>" if beginning is not opening of a block element
				"/\A(?!<blockquote|<ul|<pre|<h\d|<p>)/" => "<p>",
				// linebreaks: end with "</p>" if ending is not closure of a block element
				"/(?<!<\/blockquote>|<\/ul>|<\/pre>|<\/h\d>|<\/p>)\z/" => "</p>",
				// linebreaks: remove new lines after closure of a block element and start new "<p>" if not directly another block element follows
				"/(<\/blockquote>|<\/ul>|<\/pre>|<\/h\d>|<\/p>)\n+(?!<blockquote|<ul|<pre|<h\d|<p)/" => "$1<p>",
				// linebreaks: remove new lines before start of a block element and close "</p>" if not directly after another block element
				"/(?!<\/blockquote>|<\/ul>|<\/pre>|<\/h\d>|<\/p>)\n+(<blockquote|<ul|<pre|<h\d|<p)/" => "</p>$1",

			);
			$content = preg_replace(array_keys($replacements), $replacements, $content);

			$replacements = array(

				// linebreaks: convert remaining "\n" to paragraph breaks and "<br>" tags
				"\n\n" => "</p><p>",
				"\n" => "<br>",

			);
			$content = str_replace(array_keys($replacements), $replacements, $content);

		}
		$replacements = array(

			// code inline: replace "`...`" with "<code>...</code>"
			"/`(.*?)`/" => "<code>$1</code>",

			// bold: replace "**...**" with "<strong>...</strong>"
			"/\*\*(.*?)\*\*/" => "<strong>$1</strong>",

			// images: ![alt text](/images/icon48.png "Logo Title Text 1")
			"/!\[(.*?)\]\((.*?)(?: \"(.*?)\")?\)/" => "<img alt=\"$1\" src=\"$2\" title=\"$3\" />",

			// links: [I'm an inline-style link with title](https://www.google.com "Google's Homepage")
			"/\[(.*?)\]\((.*?)(?: \"(.*?)\")?\)/" => "<a href=\"$2\" title=\"$3\">$1</a>",

		);
		$content = preg_replace(array_keys($replacements), $replacements, $content);

		$replacements = array(

			// other: corerct bad practice of character arrows ("->"), change to html arrow symbol
			"->" => "&#8594;",

		);
		$content = str_replace(array_keys($replacements), $replacements, $content);

		return $content;

	}

	// update.json (th23 Admin Upgrader)
	private function update_json() {

		// basis can be used with some modifications to match json expected by WP upgrader
		$json = $this->plugin;
		unset($json['assets_base']);
		$assets_base = (!empty($this->plugin['assets_base'])) ? $this->plugin['assets_base'] : '';

		// icons
		unset($json['icons']);
		if(!empty($this->plugin['icons']['square'])) {
			$json['icons'] = array('1x' => $this->add_v($this->plugin['icons']['square']));
		}

		// tags
		foreach($json['tags'] as $id => $tag) {
			unset($json['tags'][$id]);
			$json['tags'][$tag] = $tag;
		}

		// author / contributors
		$contributors = array();
		foreach($this->plugin['contributors'] as $details) {
			if(!empty($details['user'])) {
				$display_name = (!empty($details['name'])) ? $details['name'] : $details['user'];
				$profile_url = 'https://profiles.wordpress.org/' . $details['user'] . '/';
				if(empty($json['author']) && !empty($details['author'])) {
					$json['author'] = '<a href="' . $profile_url . '">' . $display_name . '</a>';
					$json['author_profile'] = $profile_url;
				}
				$contributors[$details['user']] = array(
					'profile' => $profile_url,
					'avatar' => (!empty($details['avatar'])) ? $this->add_v($details['avatar']) : '',
					'display_name' => $display_name,
				);
			}
		}
		$json['contributors'] = $contributors;

		// support
		if(empty($json['donate_link'])) {
			$json['donate_link'] = $this->plugin['homepage'];
		}
		if(empty($json['support_url'])) {
			$json['support_url'] = $this->plugin['homepage'];
		}

		// remove copied source that requires re-formatting
		unset($json['license'], $json['summary'], $json['intro'], $json['usage'], $json['setup'], $json['faq'], $json['changelog']);

		// banners
		foreach($json['banners'] as $id => $banner) {
			$json['banners'][$id] = $this->add_v($banner);
		}
		if(!empty($json['banners']['high'])) {
			$json['banners']['2x'] = $json['banners']['high'];
		}
		if(!empty($json['banners']['low'])) {
			$json['banners']['1x'] = $json['banners']['low'];
		}

		// description - summary, intro, usage
		$json['sections'] = array();
		$description = $this->plugin['summary'];
		if(!empty($this->plugin['intro'])) {
			$description .= "\n\n" . $this->plugin['intro'];
		}
		if(!empty($this->plugin['usage'])) {
			$description .= "\n\n" . '<h3>Usage</h3>' . "\n\n" . $this->plugin['usage'];
		}
		$json['sections']['description'] = $this->md2html($description);

		// installation - setup
		if(!empty($this->plugin['setup'])) {
			$json['sections']['installation'] = $this->md2html($this->plugin['setup']);
		}

		// faq
		$faq = '';
		foreach($this->plugin['faq'] as $id => $qa) {
			$faq .= '<h4>' . $this->md2html($qa['q'], false) . '</h4>';
			$faq .= '<div>' . $this->md2html($qa['a']) . '</div>';
		}
		$json['sections']['faq'] = $faq;

		// changelog
		$changelog = '';
		foreach($this->plugin['changelog'] as $version => $changes) {
			$changelog .= '<h4>' . $version . '</h4><ul>';
			$changes = (is_array($changes)) ? $changes : array($changes);
			foreach($changes as $change) {
				$changelog .= '<li>' . $this->md2html($change) . '</li>';
			}
			$changelog .= '</ul>';
		}
		$json['sections']['changelog'] = $changelog;

		// screenshots
		$assets_base = (!empty($this->plugin['assets_base'])) ? $this->plugin['assets_base'] : '';
		$screenshots = '<ol>';
		foreach($this->plugin['screenshots'] as $num => $screenshot) {
			if(!empty($screenshot['src'])) {
				$screenshots .= '<li>';
				$src = $this->add_v($assets_base . $screenshot['src']);
				$caption = (!empty($screenshot['caption'])) ? $this->md2html($screenshot['caption'], false) : '';
				$screenshots .= '<a href="' . $src . '"><img src="' . $src . '" alt="' . $caption . '"></a>';
				if(!empty($caption)) {
					$screenshots .= '<p>' . $caption . '</p>';
				}
				$screenshots .= '</li>';
				// note: update assets base and html format caption main json array
				$json['screenshots'][$num] = array(
					'src' => $src,
					'caption' => $caption,
				);
			}
		}
		$json['sections']['screenshots'] = $screenshots . '</ol>';

		// upgrade notice
		foreach($json['upgrade_notice'] as $version => $notices) {
			$notices = (is_array($notices)) ? $notices : array($notices);
			$notices_html = '';
			foreach($notices as $notice) {
				$notices_html = '<li>' . $this->md2html($notice) . '</li>';
			}
			if(!empty($notices_html)) {
				$notices_html = '<ul>' . $notices_html . '</ul>';
			}
			$json['upgrade_notice'][$version] = $notices_html;
		}

		// dev: in case of errors, consider "JSON_UNESCAPED_UNICODE" and "JSON_UNESCAPED_LINE_TERMINATORS" constants as well
		return json_encode($json, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

	}

	// README.md (github.com)
	private function readme_md() {
		$assets_base = (!empty($this->plugin['assets_base'])) ? $this->plugin['assets_base'] : '';
		$git = '';
		// headline
		$git .= '# ' . ((!empty($this->plugin['icons']['horizontal'])) ? '<img alt="' . $this->plugin['name'] . '" src="' . $this->add_v($assets_base . $this->plugin['icons']['horizontal']) . '" width="200">' : $this->plugin['name']) . "\n\n";
		$git .= $this->plugin['summary'] . "\n\n\n";

		// intro
		if(!empty($this->plugin['intro'])) {
			$git .= '## 🚀 Introduction' . "\n\n";
			$git .= $this->plugin['intro'] . "\n\n";
		}
		foreach($this->plugin['screenshots'] as $screenshot) {
			if(!empty($screenshot['src'])) {
				$caption = (!empty($screenshot['caption'])) ? $screenshot['caption'] : '';
				$git .= '> <img alt="" title="' . $caption . '" src="' . $this->add_v($assets_base . $screenshot['src']) . '" width="400">' . "\n";
			}
		}
		$git .= "\n\n";
		// setup
		if(!empty($this->plugin['setup'])) {
			$git .= '## ⚙️ Setup' . "\n\n";
			$git .= $this->plugin['setup'] . "\n\n\n";
		}
		// usage
		if(!empty($this->plugin['usage'])) {
			$git .= '## 🖐️ Usage' . "\n\n";
			$git .= $this->plugin['usage'] . "\n\n\n";
			}
		// faq
		$git .= '## ❓ FAQ' . "\n\n";
		foreach($this->plugin['faq'] as $id => $qa) {
			$git .= '### Q: ' . $qa['q'] . ' ###' . "\n\n";
			$git .= 'A: ' . $qa['a'] . "\n\n";
		}
		$git .= "\n";
		// support
		$git .= '## 🤝 Contributors' . "\n\n";
		if(0 === strpos($this->plugin['homepage'], 'https://github.com/')) {
			$git .= 'Feel free to [raise issues](' . $this->plugin['homepage'] . '/issues) or [contribute code](' . $this->plugin['homepage'] . '/pulls) for improvements via GitHub.' . "\n\n\n";
		}
		else {
			$git .= 'Feel free to raise issues or contribute code for improvements via [the homepage](' . $this->plugin['homepage'] . ').' . "\n\n\n";
		}
		// license
		$git .= '## ©️ License' . "\n\n";
		$git .= ((!empty($this->plugin['license']['description'])) ? $this->plugin['license']['description'] : array_keys($this->plugin['license'])[0] ) . "\n";
		return $git;
	}

	// remove additional markdown supported by Github, see https://docs.github.com/de/get-started/writing-on-github/getting-started-with-writing-and-formatting-on-github/basic-writing-and-formatting-syntax#alerts
	private function simple_md($content) {
		$replacements = array(
			// replace notice boxes "> [!{NOTE/TIP/IMPORTANT/WARNING/CAUTION}]" by respective headlines "= {...} ="
			"/^> \[!([A-Z]*?)\]/m" => "= $1 =\n",
			// remove blockquotes (also used as bottom part of notice boxes) ie remove "> " when on line start, replace lines only containing ">" by an empty line
			"/^> /m" => "",
			"/^>$/m" => "",
		);
		return preg_replace(array_keys($replacements), $replacements, $content);
	}

	// readme.txt (wordpress.org)
	private function readme_txt() {
		$wp = '';
		// basics (mandatory, except "donate_link")
		$wp .= '=== ' . $this->plugin['name'] . ' ===' . "\n";
		$contributors = array();
		foreach($this->plugin['contributors'] as $details) {
			$contributors[] = $details['user'];
		}
		$wp .= 'Contributors: ' . implode(', ', $contributors) . "\n";
		$wp .= 'Donate link: ' . ((!empty($this->plugin['donate_link'])) ? $this->plugin['donate_link'] : $this->plugin['homepage']) . "\n";
		$wp .= 'Tags: ' . implode(', ', $this->plugin['tags']) . "\n";
		$wp .= 'Requires at least: ' . $this->plugin['requires'] . "\n";
		$wp .= 'Tested up to: ' . $this->plugin['tested'] . "\n";
		$wp .= 'Stable tag: ' . $this->plugin['version'] . "\n";
		$wp .= 'Requires PHP: ' . $this->plugin['requires_php'] . "\n";
		$license = array_keys($this->plugin['license'])[0];
		$wp .= 'License: ' . $license . "\n";
		$wp .= 'License URI: ' . $this->plugin['license'][$license] . "\n\n\n";
		// short description (mandatory)
		$wp .= $this->plugin['summary'] . "\n\n\n";
		// intro (optional) / usage (optional)
		if(!empty($this->plugin['intro']) || !empty($this->plugin['usage'])) {
			$wp .= '== Description ==' . "\n\n";
		}
		if(!empty($this->plugin['intro'])) {
			$wp .= $this->simple_md($this->plugin['intro']) . "\n\n";
		}
		if(!empty($this->plugin['intro']) && !empty($this->plugin['usage'])) {
			$wp .= '= Usage =' . "\n\n";
		}
		if(!empty($this->plugin['usage'])) {
			$wp .= $this->simple_md($this->plugin['usage']) . "\n\n";
		}
		if(!empty($this->plugin['intro']) || !empty($this->plugin['usage'])) {
			$wp .= "\n";
		}
		// setup / config (optional)
		if(!empty($this->plugin['setup'])) {
			$wp .= '== Installation ==' . "\n\n";
			$wp .= $this->simple_md($this->plugin['setup']) . "\n\n\n";
		}
		// faq (mandatory)
		$wp .= '== Frequently Asked Questions ==' . "\n\n";
		foreach($this->plugin['faq'] as $id => $qa) {
			$wp .= '= ' . preg_replace("/\*\*(.*?)\*\*/", "$1", $qa['q']) . ' =' . "\n\n";
			$wp .= $this->simple_md($qa['a']) . "\n\n";
		}
		$wp .= "\n";
		// screenshots (mandatory)
		$wp .= '== Screenshots ==' . "\n\n";
		$i = 0;
		foreach($this->plugin['screenshots'] as $screenshot) {
			if(!empty($screenshot['src'])) {
				$i++;
				$wp .= $i . '. ' . ((!empty($screenshot['caption'])) ? $screenshot['caption'] : 'n/a') . "\n";
			}
		}
		$wp .= "\n\n";
		// changelog (mandatory)
		$wp .= '== Changelog ==' . "\n";
		foreach($this->plugin['changelog'] as $version => $changes) {
			$wp .= "\n" . '= ' . $version . ' =' . "\n\n";
			$changes = (is_array($changes)) ? $changes : array($changes);
			foreach($changes as $change) {
				$wp .= '* ' . $this->simple_md($change) . "\n";
			}
		}
		$wp .= "\n\n";
		// upgrade notice (mandatory)
		$wp .= '== Upgrade Notice ==' . "\n";
		foreach($this->plugin['upgrade_notice'] as $version => $notices) {
			$wp .= "\n" . '= ' . $version . ' =' . "\n\n";
			$notices = (is_array($notices)) ? $notices : array($notices);
			foreach($notices as $notice) {
				$wp .= '* ' . $this->simple_md($notice) . "\n";
			}
		}
		return $wp;
	}

}

?>
