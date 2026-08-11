const defaultConfig = require('./node_modules/@wordpress/scripts/config/webpack.config');

const path = require('path');

module.exports = {
	...defaultConfig,
	performance: {
		hints: false,
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'@': path.resolve(__dirname, './src/'),
			'@framework': path.resolve(__dirname, './src/framework/'),
		},
	},
	entry: {
		...defaultConfig.entry,
		'activity-logs/index': path.resolve(
			process.cwd(),
			'src',
			'activity-logs',
			'index.js'
		),
		'admin-settings/index': path.resolve(
			process.cwd(),
			'src',
			'admin-settings',
			'index.js'
		),
		'events-settings/index': path.resolve(
			process.cwd(),
			'src',
			'events-settings',
			'index.js'
		),
		'dashboard/index': path.resolve(
			process.cwd(),
			'src',
			'dashboard',
			'index.js'
		),
		'common/index': path.resolve(
			process.cwd(),
			'src',
			'common',
			'index.js'
		),
	},
};
