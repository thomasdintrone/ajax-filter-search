/**
 * Editor side of the Ajax Filter Search block. Written without a build step:
 * it uses the wp.* globals (declared as dependencies in editor.asset.php).
 * The front end is rendered by PHP, so the editor shows a server-side preview.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useSelect = wp.data.useSelect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;

	var DEFAULT = { label: __( 'Use plugin setting', 'ajax-filter-search' ), value: '' };

	var showHide = [
		DEFAULT,
		{ label: __( 'Show', 'ajax-filter-search' ), value: 'show' },
		{ label: __( 'Hide', 'ajax-filter-search' ), value: 'hide' },
	];

	function Edit( props ) {
		var attributes = props.attributes;
		var set = props.setAttributes;

		var postTypes = useSelect( function ( select ) {
			return select( 'core' ).getPostTypes( { per_page: -1 } );
		}, [] );

		var typeForTaxonomies = attributes.postType || 'post';
		var taxonomies = useSelect(
			function ( select ) {
				return select( 'core' ).getTaxonomies( { per_page: -1, type: typeForTaxonomies } );
			},
			[ typeForTaxonomies ]
		);

		var typeOptions = [ DEFAULT ].concat(
			( postTypes || [] )
				.filter( function ( type ) {
					return type.viewable && type.slug !== 'attachment';
				} )
				.map( function ( type ) {
					return { label: type.name + ' (' + type.slug + ')', value: type.slug };
				} )
		);

		var taxonomyOptions = [ DEFAULT, { label: __( 'None', 'ajax-filter-search' ), value: 'none' } ].concat(
			( taxonomies || [] )
				.filter( function ( tax ) {
					return tax.slug !== 'post_format';
				} )
				.map( function ( tax ) {
					return { label: tax.name, value: tax.slug };
				} )
		);

		var blockProps = useBlockProps();

		return el(
			'div',
			blockProps,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Content', 'ajax-filter-search' ) },
					el( SelectControl, {
						label: __( 'Post type', 'ajax-filter-search' ),
						value: attributes.postType,
						options: typeOptions,
						onChange: function ( value ) {
							set( { postType: value, taxonomy: '', terms: '' } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Taxonomy for tabs', 'ajax-filter-search' ),
						value: attributes.taxonomy,
						options: taxonomyOptions,
						onChange: function ( value ) {
							set( { taxonomy: value, terms: '' } );
						},
					} ),
					el( TextControl, {
						label: __( 'Limit to term slugs', 'ajax-filter-search' ),
						help: __( 'Comma separated, for example news,press. Leave empty for all.', 'ajax-filter-search' ),
						value: attributes.terms,
						onChange: function ( value ) {
							set( { terms: value } );
						},
					} ),
					el( RangeControl, {
						label: __( 'Posts per page', 'ajax-filter-search' ),
						help: __( '0 uses the plugin setting.', 'ajax-filter-search' ),
						value: attributes.perPage,
						min: 0,
						max: 100,
						onChange: function ( value ) {
							set( { perPage: value || 0 } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Order by', 'ajax-filter-search' ),
						value: attributes.orderby,
						options: [
							DEFAULT,
							{ label: __( 'Date published', 'ajax-filter-search' ), value: 'date' },
							{ label: __( 'Title', 'ajax-filter-search' ), value: 'title' },
							{ label: __( 'Date modified', 'ajax-filter-search' ), value: 'modified' },
						],
						onChange: function ( value ) {
							set( { orderby: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Order', 'ajax-filter-search' ),
						value: attributes.order,
						options: [
							DEFAULT,
							{ label: __( 'Newest / Z to A first', 'ajax-filter-search' ), value: 'DESC' },
							{ label: __( 'Oldest / A to Z first', 'ajax-filter-search' ), value: 'ASC' },
						],
						onChange: function ( value ) {
							set( { order: value } );
						},
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Display', 'ajax-filter-search' ), initialOpen: false },
					el( SelectControl, {
						label: __( 'Starting layout', 'ajax-filter-search' ),
						value: attributes.layout,
						options: [
							DEFAULT,
							{ label: __( 'List', 'ajax-filter-search' ), value: 'list' },
							{ label: __( 'Grid', 'ajax-filter-search' ), value: 'grid' },
						],
						onChange: function ( value ) {
							set( { layout: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Category tabs', 'ajax-filter-search' ),
						value: attributes.showTabs,
						options: showHide,
						onChange: function ( value ) {
							set( { showTabs: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Layout switcher', 'ajax-filter-search' ),
						value: attributes.showViews,
						options: showHide,
						onChange: function ( value ) {
							set( { showViews: value } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Search box', 'ajax-filter-search' ),
						checked: attributes.showSearch,
						onChange: function ( value ) {
							set( { showSearch: value } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Month filter', 'ajax-filter-search' ),
						checked: attributes.showMonths,
						onChange: function ( value ) {
							set( { showMonths: value } );
						},
					} ),
					el( ToggleControl, {
						label: __( 'Year filter', 'ajax-filter-search' ),
						checked: attributes.showYears,
						onChange: function ( value ) {
							set( { showYears: value } );
						},
					} )
				)
			),
			el( ServerSideRender, {
				block: 'ajax-filter-search/feed',
				attributes: attributes,
			} )
		);
	}

	wp.blocks.registerBlockType( 'ajax-filter-search/feed', {
		edit: Edit,
		save: function () {
			return null; // Rendered by PHP.
		},
	} );
}( window.wp ) );
