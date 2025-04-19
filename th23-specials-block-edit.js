(function(wp, React) {

	wp.domReady(function() {

		// SINGLE post in BLOCK editor

		const { createElement } = React;
		const { registerPlugin } = wp.plugins;
		const { PluginDocumentSettingPanel } = wp.editor;
		const { subscribe, select, dispatch } = wp.data;
		const { createBlock } = wp.blocks;
		const { removeEditorPanel, editPost, lockPostSaving, unlockPostSaving } = dispatch('core/editor');
		const { getBlocks } = select('core/block-editor');
		const { insertBlock } = dispatch('core/block-editor');
		const { createNotice, removeNotice } = dispatch('core/notices');

		// == Allow only one category per post [one_category_only] / Disable categories for posts [disable_categories] ==

		const one_category_only = (1 == th23_specials['one_category_only']) ? true : false;
		const disabled_cat_ids = th23_specials['disable_categories'].split(',').filter(Boolean); // remove empty elements
		const categories_selection = JSON.parse(th23_specials['categories_selection']);

		// get originally assigned categories
		let org_post_category_ids = th23_specials['categories_assigned'].split(',');
		// adjust selected category, if only one category per post is allowed
		let sel_post_category_ids = (one_category_only && org_post_category_ids.length > 1) ? [org_post_category_ids[0]] : org_post_category_ids;

		// remove default category selection panel, as it can not be customized as required
		removeEditorPanel('taxonomy-panel-category');

		// create replacement category selection rows
		const CategoryRows = function(props) {
			let children = [];
			props.cats.forEach(cat => {
				let input = {
					value: cat.id,
					type: props.type,
					name: 'post_category[]',
					id: 'th23-category-input-' + cat.id,
					// update selected categories upon user changes
					onChange: function(e) {
						if(e.target.checked) {
							if('radio' == e.target.type) {
								sel_post_category_ids = [e.target.value];
							}
							else if(!sel_post_category_ids.includes(e.target.value)) {
								sel_post_category_ids.push(e.target.value);
							}
						}
						else if(sel_post_category_ids.includes(e.target.value)) {
							sel_post_category_ids = sel_post_category_ids.filter(val => val !== e.target.value);
						}
						// prepare change for saving ie convert all to integers, unlocking save button
						editPost( { 'categories': sel_post_category_ids.map(x => +x) } );
					}
				};
				if(sel_post_category_ids.includes(cat.id)) {
					input.defaultChecked = true;
				}
				else if(disabled_cat_ids.includes(cat.id)) {
					input.disabled = true;
				}
				children.push(createElement('li', { id: 'th23-category-li-' + cat.id }, [
					createElement('label', { className: 'selectit' }, [
						createElement('input', input),
						cat.title,
					]),
					createElement(CategoryRows, { class_name: 'children', cats: cat.children, type: props.type }),
				]));
			});
			let css = ('children' == props.class_name) ? { marginTop: '6px', marginLeft: '16px' } : {};
			return (children.length > 0) ? createElement('ul', { className: props.class_name, style: css }, children) : '';
		};

		// add replacement category selection panel
		// todo: create new category (in block editor)
		registerPlugin('th23-specials-categories', {
			render: function() {
				let elements = [];
				// create category selection rows ie input fields
				elements.push(createElement(CategoryRows, {
					class_name: 'th23-specials-category-selection',
					cats: categories_selection,
					type: (one_category_only) ? 'radio' : 'checkbox'
				}));
				// show notices within block editor
				let notices = [];
				if(one_category_only && org_post_category_ids.length > 1) {
					notices.push({ type: 'warning', message: th23_specials['one_category_only_notice'] });
					// promote change ie reduction to one assigned category to ensure save is enabled
					editPost( { 'categories': sel_post_category_ids.map(x => +x) } );
				}
				if(org_post_category_ids.some(item => disabled_cat_ids.includes(item))) {
					notices.push({ type: 'info', message: th23_specials['disable_categories_notice'] });
				}
				notices.forEach((notice) => {
					elements.push(createElement('div', { className: 'notice th23-notice notice-' + notice.type, style: { margin: '10px 0 0', padding: '.5em 1em' } }, notice.message));
				});
				// create panel and add elements
				return createElement(PluginDocumentSettingPanel, {
					name: 'th23-specials-categories',
					title: th23_specials['categories_title'],
					isEnabled: true,
					opened: true
				}, elements);
			},
		});

		// == Enforce <!--more--> tag [enforce_more] ==

		const post_type = th23_specials['post_type'];
		const enforce_more = th23_specials['enforce_more'];
		const longer_than = parseInt(th23_specials['longer_than']);
		const insert_after = parseInt(th23_specials['insert_after']);

		// enforce for current post type?
		if(enforce_more.includes(post_type)) {
			// subscribe to any changes and detect any changes in the number of blocks
			// note: starting with 0 waits for initial load of block data before first check
			let num_blocks = 0;
			let init_check = true;
			let save_lock = false;

			// debounce slows down frequency to check to 300ms
			subscribe(_.debounce(() => {
				// note: only retrieves top level blocks by default, if only number required could use getBlockCount() instead
				// note: top limit relevant blocks: postBlocks.filter(block => block.name === 'core/paragraph' || block.name === 'core/quote')
				const post_blocks = getBlocks();
				const more_block = post_blocks.some(block => block.name === 'core/more');
				// blocks in editor changed, also on initial loading
				if(post_blocks.length != num_blocks) {
					num_blocks = post_blocks.length;
					// more tag missing?
					if(num_blocks > longer_than && !more_block) {
						// upon first check add more block and show info
						if(init_check) {
							const more_block = createBlock('core/more', { content: '<!--more-->' });
							// note: inserts at index position (starting at zero) of top level blocks
							insertBlock(more_block, insert_after);
							createNotice('info', th23_specials['enforce_more_notice'], { id: 'th23-specials-more-notice', isDismissible: true });
						}
						// upon later checks lock saving and show warning
						else if(!save_lock) {
							lockPostSaving('th23-specials-more-lock');
							save_lock = true;
							createNotice('warning', th23_specials['enforce_more_error'], { id: 'th23-specials-more-notice', isDismissible: true });
						}
					}
					// not first check ie first time number of blocks changed
					init_check = false;
				}
				// more tag present - unlock saving, remove notice
				if(save_lock && more_block) {
					unlockPostSaving('th23-specials-more-lock');
					save_lock = false;
					removeNotice('th23-specials-more-notice');
				}
			}, 300));
		}

	});

})(window.wp, window.React);
