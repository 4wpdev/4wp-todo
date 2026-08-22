/**
 * Admin React entry for 4WP ToDo.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

module.exports = {
	...defaultConfig,
	entry() {
		const inherited =
			typeof defaultConfig.entry === 'function'
				? defaultConfig.entry()
				: defaultConfig.entry || {};

		return {
			...inherited,
			'admin/index': path.resolve(
				process.cwd(),
				'src/admin/index.js'
			),
			'frontend/index': path.resolve(
				process.cwd(),
				'src/frontend/index.js'
			),
			'tasks-page/index': path.resolve(
				process.cwd(),
				'src/tasks-page/index.js'
			),
			'blocks/todo-tasks/index': path.resolve(
				process.cwd(),
				'src/blocks/todo-tasks/index.js'
			),
		};
	},
};
