/**
 * Classic JSX runtime: JSX compiles to createElement() imported from @wordpress/element.
 *
 * The WordPress preset uses the automatic runtime, which depends on the "react-jsx-runtime"
 * script that only exists in WordPress 6.6+. This plugin supports WordPress 6.2+, so JSX is
 * transformed here first (plugins run before presets) and the preset never sees any JSX.
 */
module.exports = ( api ) => {
	api.cache( true );
	return {
		presets: [ '@wordpress/babel-preset-default' ],
		plugins: [
			[
				'@wordpress/babel-plugin-import-jsx-pragma',
				{
					scopeVariable: 'createElement',
					scopeVariableFrag: 'Fragment',
					source: '@wordpress/element',
					isDefault: false,
				},
			],
			[
				'@babel/plugin-transform-react-jsx',
				{
					runtime: 'classic',
					pragma: 'createElement',
					pragmaFrag: 'Fragment',
				},
			],
		],
	};
};
