/**
 * Human-readable labels for known `context` / `before_data` / `after_data`
 * keys, shared by every logger (see `AbstractLogger::get_common_context()`)
 * plus the extra keys individual loggers add.
 *
 * Anything not listed here falls back to `humanizeKey()`'s snake_case ->
 * "Title Case" conversion, so a new/unmapped event type still gets a
 * readable label instead of a raw JSON key - this list only exists to
 * override that default where a friendlier wording is worth it.
 */
const FIELD_LABELS = {
	current_user_id: 'Acting User ID',
	current_user_roles: 'Acting User Roles',
	roles: 'Roles',
	user_agent: 'User Agent',
	request_url: 'Request URL',
	attempted_username: 'Attempted Username',
	attempted_display_name: 'Attempted Display Name',
	switched_from_user_id: 'Switched From (User ID)',
	switched_from_user_login: 'Switched From (Username)',
	switched_to_roles: 'Switched To Roles',
	registered_user_roles: 'Registered Roles',
	old_roles: 'Previous Roles',
	new_role: 'New Role',
	reassign: 'Content Reassigned To',
	blog_id: 'Site ID',
	role: 'Role',
	user_meta_key: 'Custom Field',
	meta_key: 'Meta Key',
	plugin: 'Plugin',
	theme: 'Theme',
	post_type: 'Post Type',
	post_status: 'Post Status',
	user_email: 'Email Address',
	user_login: 'Username',
	display_name: 'Display Name',
	first_name: 'First Name',
	last_name: 'Last Name',
	nickname: 'Nickname',
	description: 'Biographical Info',
	user_url: 'Website',
	super_admin: 'Super Admin',
	name: 'Name',
	uuid: 'UUID',

	// Posts/pages/CPTs (PostActivityLogger, and the WooCommerce loggers
	// that piggyback on the same post-based diff pattern).
	post_title: 'Title',
	post_name: 'URL Slug',
	post_content: 'Content',
	post_excerpt: 'Excerpt',
	post_author: 'Author',
	post_parent: 'Parent',
	post_date: 'Date',
	post_password: 'Password',
	has_password: 'Password Protected',
	sticky: 'Sticky',
	template: 'Template',

	// Media (MediaActivityLogger).
	mime_type: 'File Type',
	guid: 'File URL',
	thumbnail_id: 'Featured Image ID',
	alt_text: 'Alt Text',
	parent_post_id: 'Attached To (Post ID)',
	site_icon: 'Site Icon (Attachment ID)',

	// Comments (CommentActivityLogger) and WooCommerce reviews (ReviewActivityLogger).
	comment_content: 'Comment',
	comment_author: 'Author Name',
	comment_author_email: 'Author Email',
	comment_author_url: 'Author Website',
	comment_status: 'Status',
	comment_approved: 'Status',
	comment_post_ID: 'Post ID',
	comment_parent: 'Parent Comment',
	comment_post_id: 'Post ID',
	rating: 'Rating',

	// Plugins/themes (PluginActivityLogger, ThemeActivityLogger).
	version: 'Version',
	author: 'Author',
	plugin_file: 'Plugin File',
	network_wide: 'Network-Wide',
	error_code: 'Error Code',
	checksum: 'Checksum',
	size: 'File Size (Bytes)',
	file: 'File',

	// Core/settings (WPSettingsActivityLogger).
	wp_version: 'WordPress Version',
	auto_update: 'Automatic Update',

	// Menus/widgets (MenuActivityLogger, WidgetActivityLogger).
	menu_id: 'Menu ID',
	slug: 'Slug',
	title: 'Title',
	url: 'URL',
	object: 'Linked To (Type)',
	object_id: 'Linked To (ID)',
	menu_item_parent: 'Parent Menu Item',
	menu_order: 'Order',
	option_name: 'Option',

	// WooCommerce (product/order/coupon/category loggers).
	sku: 'SKU',
	regular_price: 'Regular Price',
	sale_price: 'Sale Price',
	stock_status: 'Stock Status',
	stock_quantity: 'Stock Quantity',
	catalog_visibility: 'Catalog Visibility',
	product_cat: 'Category',
	product_status: 'Status',
	coupon_status: 'Status',
	coupon_amount: 'Discount Amount',
	status: 'Status',
	order_status: 'Order Status',
	total: 'Total',
	refund_total: 'Refund Amount',
	note: 'Note',
	customer_note: 'Customer Note',
};

/**
 * Labels for the individual WooCommerce address fields, used to build a
 * `billing_<field>`/`shipping_<field>` label without hardcoding every
 * combination - see `humanizeKey()`.
 */
const ADDRESS_FIELD_LABELS = {
	first_name: 'First Name',
	last_name: 'Last Name',
	company: 'Company',
	address_1: 'Address Line 1',
	address_2: 'Address Line 2',
	city: 'City',
	state: 'State/Province',
	postcode: 'Postcode',
	country: 'Country',
	email: 'Email',
	phone: 'Phone',
};

/**
 * Keys that are useful but verbose (long strings, rarely what an admin
 * is scanning for) - shown de-emphasized under "Technical Details"
 * rather than mixed into the main details table.
 */
const TECHNICAL_CONTEXT_KEYS = ['user_agent', 'request_url'];

/**
 * Snake_case -> "Title Case", with no other special-casing. The shared
 * fallback for anything `humanizeKey()` doesn't have a better label for.
 *
 * @param {string} key Raw context/diff key.
 * @return {string} Title-cased label.
 */
const titleCase = (key) =>
	key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

/**
 * Convert a snake_case context key into a readable label, falling back
 * to Title Case for anything not in `FIELD_LABELS`.
 *
 * WooCommerce order edits flatten each address into
 * `billing_<field>`/`shipping_<field>` keys (see
 * `OrderActivityLogger::diff_order_fields()`) rather than one blob per
 * address, so those are special-cased here instead of listing every
 * field twice in `FIELD_LABELS` - this also picks up any address field
 * WooCommerce adds in the future without needing an update here.
 *
 * @param {string} key Raw context/diff key.
 * @return {string} Human-readable label.
 */
export const humanizeKey = (key) => {
	if (FIELD_LABELS[key]) {
		return FIELD_LABELS[key];
	}

	const addressMatch = key.match(/^(billing|shipping)_(.+)$/);

	if (addressMatch) {
		const [, group, field] = addressMatch;
		const groupLabel = group === 'billing' ? 'Billing' : 'Shipping';
		const fieldLabel = ADDRESS_FIELD_LABELS[field] || titleCase(field);

		return `${groupLabel} ${fieldLabel}`;
	}

	return titleCase(key);
};

/**
 * Parse a JSON string (or pass through an already-parsed object) into a
 * plain object, returning null for anything empty/invalid rather than
 * throwing - callers can treat null as "nothing to show".
 *
 * @param {string|object|null} value Raw `context`/`before_data`/`after_data` value.
 * @return {object|null} Parsed object, or null if empty/invalid.
 */
export const parseJsonSafe = (value) => {
	if (!value) {
		return null;
	}

	if (typeof value === 'object') {
		return value;
	}

	try {
		const parsed = JSON.parse(value);

		return parsed && typeof parsed === 'object' ? parsed : null;
	} catch (e) {
		return null;
	}
};

/**
 * Format a single field's value for display: arrays become a
 * comma-separated list, booleans become Yes/No, empty values become an
 * em dash, and anything else is stringified.
 *
 * @param {*} value Raw field value.
 * @return {string} Display-ready string.
 */
export const formatFieldValue = (value) => {
	if (value === null || value === undefined || value === '') {
		return '—';
	}

	if (Array.isArray(value)) {
		return value.length ? value.join(', ') : '—';
	}

	if (typeof value === 'boolean') {
		return value ? 'Yes' : 'No';
	}

	if (typeof value === 'object') {
		return JSON.stringify(value);
	}

	return String(value);
};

/**
 * Build rows for the "Details" table out of a log's `context` JSON,
 * splitting off the verbose technical fields (user agent, request URL)
 * so they can be rendered de-emphasized instead of crowding the fields
 * an admin actually scans for.
 *
 * @param {string|null} contextJson Raw `context` column value.
 * @return {{rows: Array<{key: string, label: string, value: string}>, technicalRows: Array<{key: string, label: string, value: string}>}} Main and technical rows.
 */
export const buildContextRows = (contextJson) => {
	const context = parseJsonSafe(contextJson);

	if (!context) {
		return { rows: [], technicalRows: [] };
	}

	const rows = [];
	const technicalRows = [];

	Object.keys(context).forEach((key) => {
		const row = {
			key,
			label: humanizeKey(key),
			value: formatFieldValue(context[key]),
		};

		if (TECHNICAL_CONTEXT_KEYS.includes(key)) {
			technicalRows.push(row);
		} else {
			rows.push(row);
		}
	});

	return { rows, technicalRows };
};

/**
 * Build rows for a before/after diff table out of a log's `before_data`
 * and `after_data` JSON, merging keys present on either side so a field
 * that was only added (or only removed) still shows up, with the other
 * side rendered as an em dash.
 *
 * Rows where the formatted before/after value is identical are dropped.
 * Not every logger pre-filters its snapshot down to just the fields that
 * changed - some send the same fixed field set on both sides regardless
 * of which one actually differs - so without this, a "Changes" table
 * could show several rows that aren't actually changes, alongside the
 * one that is.
 *
 * @param {string|null} beforeJson Raw `before_data` column value.
 * @param {string|null} afterJson  Raw `after_data` column value.
 * @return {Array<{key: string, label: string, before: string, after: string}>} Diff rows, changed fields only.
 */
export const buildDiffRows = (beforeJson, afterJson) => {
	const before = parseJsonSafe(beforeJson) || {};
	const after = parseJsonSafe(afterJson) || {};

	const keys = Array.from(
		new Set([...Object.keys(before), ...Object.keys(after)])
	);

	return keys
		.map((key) => ({
			key,
			label: humanizeKey(key),
			before: formatFieldValue(before[key]),
			after: formatFieldValue(after[key]),
		}))
		.filter((row) => row.before !== row.after);
};
