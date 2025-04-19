jQuery(document).ready(function($) {

	// SINGLE post in CLASSIC editor

	// == Allow only one category per post [one_category_only] / Disable categories for posts [disable_categories] ==

	const one_category_only = (1 == th23_specials['one_category_only']) ? true : false;
	const disabled_cat_ids = th23_specials['disable_categories'].split(',').filter(Boolean); // remove empty elements

	// jquery selectors for multiple usage
	const input_cats = 'ul.categorychecklist input[name="post_category[]"]';

	// get categories post is assigned to
	let org_post_category_ids = th23_specials['categories_assigned'].split(',');

	// adjust selected category, if only one category per post is allowed
	let sel_post_category_ids = (one_category_only && org_post_category_ids.length > 1) ? [org_post_category_ids[0]] : org_post_category_ids;
	$(input_cats).each(function() {
		$(this).prop('checked', sel_post_category_ids.includes($(this).val()));
		if(one_category_only) {
			$(this).prop('type', 'radio');
		}
	});

	// handle disabled categories - only leave enabled if post is currently assigned
	disabled_cat_ids.forEach((cat_id) => {
		$('.categorychecklist input[value="' + cat_id + '"]').prop('disabled', !sel_post_category_ids.includes(cat_id));
	});

	// show notices within classic editor
	let notices = [];
	if(one_category_only && org_post_category_ids.length > 1) {
		notices.push({ type: 'warning', message: th23_specials['one_category_only_notice'] });
	}
	if(org_post_category_ids.some(item => disabled_cat_ids.includes(item))) {
		notices.push({ type: 'info', message: th23_specials['disable_categories_notice'] });
	}
	notices.forEach((notice) => {
		$('#major-publishing-actions').append('<div class="notice th23-notice notice-' + notice.type + '" style="margin: 15px 0 5px; padding: .5em 1em;">' + notice.message + '</div>');
	});

	// adjust popular category list
	let pop_post_categories = [];
	$('#categorychecklist-pop li').each(function() {
		let cat_id = $('input', this).val();
		if(!disabled_cat_ids.includes(cat_id)) {
			pop_post_categories.push({ cat_id: cat_id, cat_title: $(this).text().trim() });
		}
		// if multi selection remains, only disable respective cats if not selected
		else if(!one_category_only && !sel_post_category_ids.includes(cat_id)) {
			$('input', this).prop('disabled', true);
		}
	});
	// replace list with label links for single select only
	if(one_category_only) {
		$('#categorychecklist-pop').remove();
		let pop_html = '<ul id="categorychecklist-pop" class="categorychecklist form-no-clear">';
		pop_post_categories.forEach((pop_cat) => {
			pop_html += '<li class="popular-category"><label for="in-category-' + pop_cat.cat_id + '-2" style="cursor: pointer; color: #2271b1;">' + pop_cat.cat_title + '</label></li>';
		});
		pop_html += '</ul>';
		$('#category-pop').append(pop_html);
		$('#categorychecklist-pop label').click(function() { $('#category-tabs a[href="#category-all"]').click(); });
	}

	// watch and handle adding new categories
	if(one_category_only) {
		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if(mutation.addedNodes.length > 0 && typeof mutation.addedNodes[0].id !== 'undefined') {
					$('#' + mutation.addedNodes[0].id + ' input[name="post_category[]"]').prop('type', 'radio').prop('checked', true);
				}
			});
		});
		if($('#categorychecklist')[0]) {
			observer.observe($('#categorychecklist')[0], { attributes: false, childList: true });
		}
	}

	// == Enforce <!--more--> tag [enforce_more] ==

	const post_type = th23_specials['post_type'];
	const enforce_more = th23_specials['enforce_more'];
	const longer_than = parseInt(th23_specials['longer_than']);
	const insert_after = parseInt(th23_specials['insert_after']);

	// enforce for current post type?
	if(enforce_more.includes(post_type)) {

		const org_post_content = $('#content').val();
		let adjusted = false;
		let tinymce;

		// determine paragraph ending: block paragraphs, html p tags or double new lines
		let search = '\n\n';
		if(org_post_content.includes('<!-- /wp:paragraph -->')) {
			search = '<!-- /wp:paragraph -->';
		}
		else if (org_post_content.includes('</p>')) {
			search = '</p>';
		}

		// more tag missing?
		if(more_missing(org_post_content)) {

			// note for check upon loading visual editor (tinymce)
			adjusted = true;

			// insert more tag after "insert_after" paragraphs
			let i = 0;
			$('#content').val(org_post_content.replace(new RegExp(search, 'g'), match => ++i === insert_after ? search + '\n\n<!--more-->' : match));

			// show notice about adjustment
			more_notice('info', th23_specials['enforce_more_notice']);

		}

		// check adjustment upon loading visual editor (tinymce)
		// note: init happens automatically when opening edit page in visual mode or after manual switch from code view
		$(document).on('tinymce-editor-init', function(event, editor) {
			if(adjusted) {
				// prevent auto_p on excessive line breaks after paragraphs and added more tag
				editor.setContent($('#content').val().replace(/<!-- \/wp:paragraph -->\n\n|<\/p>\n\n|<!--more-->\n\n/g, match => match.replace('\n\n', '')));
			}
			// remember for later access
			tinymce = editor;
		});

		// check again upon saving - re-do check as content might habe been modified
		$('#publish').click(function(e){
			// based on active tab check content from tinymce or plain input for missing more tag
			let chk_post_content = ($('#wp-content-wrap').hasClass('tmce-active')) ? tinymce.getContent() : $('#content').val();
			if(more_missing(chk_post_content)) {
				// show notice as error, move to top (to ensure its visible) and prevent saving
				more_notice('error', th23_specials['enforce_more_error']);
				$(document).scrollTop(0);
				e.preventDefault();
			}
		});

		// check if more is missing (> "longer_than" paragraphs and not present)
		function more_missing(content) {
			return (content.split(search).length - 1 > longer_than && !content.includes('<!--more-->'));
		}

		// show more notice
		function more_notice(type, message) {
			$('#th23-notice-more').remove();
			$('#post').before('<div id="th23-notice-more" class="notice th23-notice notice-' + type + ' is-dismissible" style="margin: 15px 0 5px; padding: .75em 1em;">' + message + '<button class="notice-dismiss" type="button"></div>');
		}

		// remove more notice upon click on "add more" or dismiss button
		$(document).on('click', '#qt_content_more, #mceu_10-button, #th23-notice-more .notice-dismiss', function() {
			$('#th23-notice-more').remove();
		});

	}

});
