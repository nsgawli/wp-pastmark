import {
	buildContextRows,
	buildDiffRows,
	parseJsonSafe,
} from './logFieldFormat';

export {
	buildLogDetailsPath,
	buildLogDetailsUrl,
} from '@framework/utils/logDetailsUrl';

export const LOG_DETAILS_VIEW_MODES = {
	drawer: 'drawer',
	singlePage: 'single_page',
};

/**
 * Escape a value for use as a Markdown table cell: pipes would end the
 * cell early, and a literal newline would break the row onto its own
 * (invalid) line - both are real risks here since cell values can be
 * arbitrary log data (e.g. a multi-line comment or post content diff).
 *
 * @param {*} value Raw cell value.
 * @return {string} Markdown-safe cell text.
 */
const escapeMarkdownCell = (value) =>
	String(value ?? '')
		.replace(/\|/g, '\\|')
		.replace(/\r?\n/g, ' ');

/**
 * Render rows into a Markdown table, or an empty string if there's
 * nothing to show - so callers can splice the result in without an
 * extra existence check.
 *
 * @param {Array<Object>}                       rows    Row objects.
 * @param {Array<{key: string, title: string}>} columns Column definitions.
 * @return {string} Markdown table, or ''.
 */
const markdownTable = (rows, columns) => {
	if (!rows.length) {
		return '';
	}

	const header = `| ${columns.map((column) => column.title).join(' | ')} |`;
	const divider = `| ${columns.map(() => '---').join(' | ')} |`;
	const body = rows
		.map(
			(row) =>
				`| ${columns.map((column) => escapeMarkdownCell(row[column.key])).join(' | ')} |`
		)
		.join('\n');

	return [header, divider, body].join('\n');
};

/**
 * Build a Markdown export of a log entry: the same information the
 * details view shows on screen - overview fields, the before/after
 * diff, and the details table - not just the message.
 *
 * @param {Object} log Log details object, as returned by the REST API.
 * @return {string} Markdown text.
 */
export const getLogMarkdown = (log = {}) => {
	const lines = [
		`**${log.event_label || log.event || '-'}** - ${log.message || '-'}`,
		'',
	];

	lines.push(`- **User:** ${log.user || '-'}`);

	if (log.object_label) {
		const target = log.object_url
			? `[${log.object_label}](${log.object_url})`
			: log.object_label;

		lines.push(`- **Target:** ${target}`);
	}

	lines.push(
		`- **Severity:** ${log.severity_label || log.severity || '-'}`,
		`- **Action:** ${log.action_label || log.action || '-'}`,
		`- **IP:** ${log.ip || '-'}`,
		`- **Timestamp:** ${log.date || '-'}`
	);

	const diffRows = buildDiffRows(log.before_data, log.after_data);
	const { rows: detailRows } = buildContextRows(log.context);

	// `content_diff` rows (PM-142/PM-143) are a single multi-line diff
	// string, not a before/after pair - escapeMarkdownCell() would flatten
	// their newlines and make the diff unreadable inside a table cell, so
	// each gets its own fenced code block instead of a table row.
	const fieldDiffRows = diffRows.filter((row) => !row.isContentDiff);
	const contentDiffRows = diffRows.filter((row) => row.isContentDiff);

	const diffTable = markdownTable(fieldDiffRows, [
		{ key: 'label', title: 'Field' },
		{ key: 'before', title: 'Before' },
		{ key: 'after', title: 'After' },
	]);

	if (diffTable) {
		lines.push('', '**Changes**', '', diffTable);
	}

	contentDiffRows.forEach((row) => {
		lines.push('', `**${row.label}**`, '', '```diff', row.diff, '```');
	});

	const detailsTable = markdownTable(detailRows, [
		{ key: 'label', title: 'Field' },
		{ key: 'value', title: 'Value' },
	]);

	if (detailsTable) {
		lines.push('', '**Details**', '', detailsTable);
	}

	return lines.join('\n');
};

/**
 * Build a JSON export of a log entry.
 *
 * `before_data`/`after_data`/`context` come back from the REST API as
 * JSON-encoded strings (that's the raw DB column value) - stringifying
 * the log object as-is would double-encode them, producing an escaped
 * JSON string inside the JSON instead of a proper nested object. Parse
 * them first so the export is actually usable JSON.
 *
 * @param {Object} log Log details object, as returned by the REST API.
 * @return {string} Pretty-printed JSON text.
 */
export const getLogJson = (log = {}) => {
	const output = {
		...log,
		before_data: parseJsonSafe(log.before_data),
		after_data: parseJsonSafe(log.after_data),
		context: parseJsonSafe(log.context),
	};

	return JSON.stringify(output, null, 2);
};
