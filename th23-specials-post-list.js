jQuery(document).ready(function($) {

	// INLINE editing on post overview screen

	// == Allow only one category per post [one_category_only] / Disable categories for posts [disable_categories] ==

	const one_category_only = (1 == th23_specials['one_category_only']) ? true : false;
	const disabled_cat_ids = th23_specials['disable_categories'].split(',').filter(Boolean); // remove empty elements

	// jquery selectors for multiple usage
	const input_cats = '.inline-editor ul.category-checklist input[name="post_category[]"]';
	const input_indeterminate = '.inline-editor ul.category-checklist input[name="indeterminate_post_category[]"]';

	// collect existing category ids
	let existing_cat_ids = [];
	$('.inline-edit-categories input[type="checkbox"]').each(function() {
		let value = $(this).val();
		if(!existing_cat_ids.includes(value)) {
			existing_cat_ids.push(value);
		}
	});

	// preserve functionality of original edit function
	const wp_inline_edit_function = inlineEditPost.edit;

	// replace original inline edit function
	inlineEditPost.edit = function(post_id) {

		// re-establish original functionality and add following customization
		wp_inline_edit_function.apply(this, arguments);

		// get the post id as number, if required from object method
		if(typeof(post_id) == 'object') {
			post_id = parseInt(this.getId(post_id));
		}

		// get categories assigned to post
		let org_post_category_ids = $('#category_' + post_id).text().split(',');
		// verify first assigned category to be valid, default to uncategorized (id 1) otherwise - store as single item in array
		let sel_post_category_ids = (one_category_only) ? ((existing_cat_ids.includes(org_post_category_ids[0])) ? [org_post_category_ids[0]] : [1]) : org_post_category_ids;
		// set category accordingly in inline edit
		$(input_cats).each(function() {
			$(this).prop('checked', sel_post_category_ids.includes($(this).val()));
			if(one_category_only) {
				$(this).prop('type', 'radio');
			}
		});

		// handle disabled categories - only leave enabled if post is currently assigned
		disabled_cat_ids.forEach((cat_id) => {
			$('.inline-editor ul.category-checklist input[value="' + cat_id + '"]').prop('disabled', !org_post_category_ids.includes(cat_id));
		});

		// show notices
		let notices = [];
		if(one_category_only && org_post_category_ids.length > 1) {
			notices.push({ type: 'warning', message: th23_specials['one_category_only_notice'] });
		}
		if(org_post_category_ids.some(item => disabled_cat_ids.includes(item))) {
			notices.push({ type: 'info', message: th23_specials['disable_categories_notice'] });
		}
		show_notices(notices);

	}

	// preserve functionality of original prepare bulk function
	const wp_inline_bulk_function = inlineEditPost.setBulk;

	// replace original prepare bulk function
	inlineEditPost.setBulk = function() {

		// re-establish original functionality and add following customization
		wp_inline_bulk_function.apply(this, arguments);

		let cats = {};
		let cat_keys = [];
		let keep_enabled = [];

		// switch to category radio buttons requires to collect some info about post selection
		if(one_category_only) {

			let checked_posts = $('tbody th.check-column input[type="checkbox"]:checked');
			checked_posts.each(function() {
				let cat_id = $(this).val();
				let checked = $('#category_' + cat_id).text().split(',');
				checked.map(function(cat_id) {
					cats[cat_id] || (cats[cat_id] = 0);
					cats[cat_id]++;
				});
			});

			// cat_keys contain id of selected categories
			cat_keys = Object.keys(cats);
			// one_category_only AND all posts have the same category/categories assigned
			if(cat_keys.length == 1 || Object.values(cats).every((val) => val === checked_posts.length) == true) {
				// keep assigned category enabled
				keep_enabled.push(cat_keys[0]);
				// deselect all but (first jointly) assigned category
				$(input_cats).prop('checked', false);
				$(input_cats + '[value="' + cat_keys[0] + '"]').prop('checked', true);
				// remove all indeterminate
				$(input_cats).prop('indeterminate', false);
				$(input_indeterminate).remove();
			}
			// one_category_only AND not all posts have same cat(s) - all are indeterminate
			else {
				// uncheck all items and set indeterminate for all
				$(input_cats).each(function() {
					let cat_id = $(this).val();
					$(this).prop('checked', false).prop('indeterminate', true);
					if(!$(this).parent().find('input[name="indeterminate_post_category[]"]').length) {
						$(this).after('<input type="hidden" name="indeterminate_post_category[]" value="' + cat_id + '">');
					}
				})
				// one change of any radio button remove all indeterminate
				$(input_cats).on('change', function() {
					$(input_indeterminate).remove();
				});
			}

			// switch to radio buttons
			$(input_cats).prop('type', 'radio');

		}
		else {

			// keep disabled categories enabled, if they are assigned to all posts already
			$(input_cats + ':checked').each(function() {
				let value = $(this).val();
				if(!$(this).parent().find('input[name="indeterminate_post_category[]"]').length) {
					keep_enabled.push(value);
				}
			});

		}

		// disable disbaled categories, excluding exceptions
		disabled_cat_ids.forEach((cat_id) => {
			$('.inline-editor ul.category-checklist input[value="' + cat_id + '"]').prop('disabled', (!keep_enabled.includes(cat_id)) ? true : false);
		});

		// show notices
		let notices = [];
		if(one_category_only && cat_keys.length > 1) {
			notices.push({ type: 'warning', message: th23_specials['one_category_only_notice'] });
		}
		if(cat_keys.some(item => disabled_cat_ids.includes(item))) {
			notices.push({ type: 'info', message: th23_specials['disable_categories_notice'] });
		}
		show_notices(notices);

	}

	// show notices within inline editor
	function show_notices(notices) {
		$('.inline-editor .th23-notice').remove();
		notices.forEach((notice) => {
			$('<div class="notice th23-notice notice-' + notice.type + '" style="clear: both; padding: .5em 1em;">' + notice.message + '</div>').insertBefore('.inline-editor div.inline-edit-save');
		});
	}

});
