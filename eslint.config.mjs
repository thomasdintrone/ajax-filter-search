import js from '@eslint/js';
import globals from 'globals';

// Shipping JavaScript only: the front-end script, the block editor script and the admin script.
export default [
	{ ignores: [ 'node_modules/**', 'vendor/**', 'build/**', '.cache/**', 'spikes/**', 'tests/**', 'docs/**', '**/*.min.js' ] },
	js.configs.recommended,
	{
		files: [ 'public/js/**/*.js' ],
		languageOptions: { ecmaVersion: 2018, sourceType: 'script', globals: { ...globals.browser } },
	},
	{
		files: [ 'blocks/**/*.js' ],
		languageOptions: { ecmaVersion: 2018, sourceType: 'script', globals: { ...globals.browser, wp: 'readonly' } },
	},
	{
		files: [ 'admin/js/**/*.js' ],
		languageOptions: { ecmaVersion: 2018, sourceType: 'script', globals: { ...globals.browser, jQuery: 'readonly', ajaxurl: 'readonly' } },
	},
];
