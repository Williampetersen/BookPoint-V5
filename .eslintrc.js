/**
 * ESLint: WordPress recommended rules.
 *
 * Destructured React props are documented where it helps; requiring a @param line for every
 * prop adds noise without catching bugs, so those three JSDoc rules are off.
 */
module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	rules: {
		'jsdoc/require-param': 'off',
		'jsdoc/check-param-names': 'off',
		'jsdoc/check-line-alignment': 'off',
	},
	overrides: [
		{
			files: [ '.dev/**/*.js', 'webpack.config.js', 'babel.config.js', '.eslintrc.js' ],
			env: { node: true },
		},
	],
};
