/**
 * Editor side of the Ajax Filter Search block. Written without a build step:
 * it uses the wp.* globals (declared as dependencies in editor.asset.php).
 * The front end is rendered by PHP, so the editor shows a server-side preview.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var useSelect = wp.data.useSelect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var FormTokenField = wp.components.FormTokenField;
	var useState = wp.element.useState;
	var useRef = wp.element.useRef;
	var RangeControl = wp.components.RangeControl;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;

	var DEFAULT = { label: __( 'Use plugin setting', 'ajax-filter-search' ), value: '' };

	// Set by the plugin (AFS_Frontend::editor_assets): the designs, the site's default design and the site's default taxonomy.
	var data = window.afsEditor || {};
	var designs = data.designs || [
		{ value: 'modern', label: 'Modern', own: false },
		{ value: 'classic', label: 'Classic', own: false },
	];
	var ratios = [ '16:10', '3:2', '4:3', '1:1', '16:9' ];
	var cards = data.cards || {};

	/** Show/hide options whose first entry says what the plugin setting currently is, for example "Use plugin setting (Show)". */
	function showHideFor( key ) {
		/* translators: %s: the current value of the setting, "Show" or "Hide". */
		var label = sprintf( __( 'Use plugin setting (%s)', 'ajax-filter-search' ), cards[ key ] ? __( 'Show', 'ajax-filter-search' ) : __( 'Hide', 'ajax-filter-search' ) );
		return [
			{ label: label, value: '' },
			{ label: __( 'Show', 'ajax-filter-search' ), value: 'show' },
			{ label: __( 'Hide', 'ajax-filter-search' ), value: 'hide' },
		];
	}

	/** What a Show/Hide attribute comes to: its own value, or the plugin setting when it is empty. */
	function effective( value, key ) {
		if ( 'show' === value ) {
			return true;
		}
		if ( 'hide' === value ) {
			return false;
		}
		return !! cards[ key ];
	}

	function ownsMarkup( design ) {
		var effective = design || data.siteDesign || '';
		return designs.some( function ( item ) {
			return item.value === effective && item.own;
		} );
	}

	/**
	 * Searchable term picker. The block stores slugs (comma separated, as the shortcode does); the picker shows names.
	 * A slug that is not in the loaded terms stays in the list as it is, so nothing is lost when the list is long.
	 */
	function TermPicker( props ) {
		var taxonomy = props.taxonomy;
		var searchState = useState( '' );
		var search = searchState[ 0 ];
		var setSearch = searchState[ 1 ];
		var names = useRef( {} ); // slug => name, for every term seen so far.

		var terms = useSelect(
			function ( select ) {
				if ( ! taxonomy || taxonomy === 'none' ) {
					return null;
				}
				var query = { per_page: 100, orderby: 'name', order: 'asc', _fields: 'id,slug,name' };
				if ( search ) {
					query.search = search;
				}
				return select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, query );
			},
			[ taxonomy, search ]
		);

		// While a new search loads, keep offering the last results instead of an empty list.
		var last = useRef( [] );
		if ( terms ) {
			last.current = terms;
		}
		var list = terms || last.current;

		list.forEach( function ( term ) {
			names.current[ term.slug ] = term.name;
		} );

		var selected = ( props.value || '' )
			.split( ',' )
			.map( function ( slug ) {
				return slug.trim();
			} )
			.filter( Boolean );
		var label = function ( slug ) {
			return names.current[ slug ] || slug;
		};
		var slugFor = function ( token ) {
			var found = Object.keys( names.current ).filter( function ( slug ) {
				return names.current[ slug ] === token;
			} )[ 0 ];
			return found || token;
		};

		// The token field has no bottom margin of its own; the other controls in the panel have 24px.
		return el( 'div', { style: { marginBottom: '24px' } }, el( FormTokenField, {
			label: __( 'Limit to these terms', 'ajax-filter-search' ),
			value: selected.map( label ),
			suggestions: list
				.filter( function ( term ) {
					return selected.indexOf( term.slug ) === -1;
				} )
				.map( function ( term ) {
					return term.name;
				} ),
			onChange: function ( tokens ) {
				props.onChange(
					tokens
						.map( function ( token ) {
							return slugFor( typeof token === 'string' ? token : token.value );
						} )
						.join( ',' )
				);
			},
			onInputChange: function ( input ) {
				setSearch( input.length > 1 ? input : '' );
			},
			__experimentalExpandOnFocus: true,
			__experimentalShowHowTo: false,
			// Only terms that exist can be added; a typed name that matches none is dropped.
			__experimentalValidateInput: function ( token ) {
				return Object.keys( names.current ).some( function ( slug ) {
					return names.current[ slug ] === token;
				} );
			},
		} ) );
	}

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

		var effectiveTaxonomy = attributes.taxonomy || data.siteTaxonomy || '';
		var ownMarkup = ownsMarkup( attributes.design );
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
					effectiveTaxonomy && effectiveTaxonomy !== 'none'
						? el( TermPicker, {
								taxonomy: effectiveTaxonomy,
								value: attributes.terms,
								onChange: function ( value ) {
									set( { terms: value } );
								},
						  } )
						: null,
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
						label: __( 'Design', 'ajax-filter-search' ),
						value: attributes.design,
						options: [ DEFAULT ].concat(
							designs.map( function ( item ) {
								return { label: item.label, value: item.value };
							} )
						),
						onChange: function ( value ) {
							set( { design: value } );
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
					el( SelectControl, {
						label: __( 'Sort control', 'ajax-filter-search' ),
						value: attributes.showSort,
						options: showHide,
						onChange: function ( value ) {
							set( { showSort: value } );
						},
					} ),
					el( SelectControl, {
						label: __( 'Active filter chips', 'ajax-filter-search' ),
						value: attributes.showChips,
						options: showHide,
						onChange: function ( value ) {
							set( { showChips: value } );
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
				),
				ownMarkup
					? el(
							PanelBody,
							{ title: __( 'Cards', 'ajax-filter-search' ), initialOpen: true },
							el( SelectControl, {
								label: __( 'Featured image', 'ajax-filter-search' ),
								value: attributes.showImage,
								options: showHideFor( 'showImage' ),
								onChange: function ( value ) {
									set( { showImage: value } );
								},
							} ),
							// The image shape only matters while the featured image is shown.
							effective( attributes.showImage, 'showImage' )
								? el( SelectControl, {
										label: __( 'Image shape', 'ajax-filter-search' ),
										value: attributes.imageRatio,
										options: [ { /* translators: %s: the current value of the setting, for example 16:10. */ label: sprintf( __( 'Use plugin setting (%s)', 'ajax-filter-search' ), cards.imageRatio || '16:10' ), value: '' } ].concat(
											ratios.map( function ( ratio ) {
												return { label: ratio, value: ratio };
											} )
										),
										onChange: function ( value ) {
											set( { imageRatio: value } );
										},
								  } )
								: null,
							el( RangeControl, {
								label: __( 'Columns', 'ajax-filter-search' ),
								help: sprintf( /* translators: %d: the current default number of columns. */ __( 'The most columns used. 0 uses the plugin setting (%d).', 'ajax-filter-search' ), cards.columns || 3 ),
								value: attributes.columns,
								min: 0,
								max: 4,
								onChange: function ( value ) {
									set( { columns: value || 0 } );
								},
							} ),
							el( SelectControl, {
								label: __( 'Excerpt', 'ajax-filter-search' ),
								value: attributes.showExcerpt,
								options: showHideFor( 'showExcerpt' ),
								onChange: function ( value ) {
									set( { showExcerpt: value } );
								},
							} ),
							// The excerpt length only matters while the excerpt is shown.
							effective( attributes.showExcerpt, 'showExcerpt' )
								? el( RangeControl, {
										label: __( 'Excerpt length (words)', 'ajax-filter-search' ),
										help: sprintf( /* translators: %d: the current default excerpt length in words. */ __( '0 uses the plugin setting (%d).', 'ajax-filter-search' ), cards.excerptLength || 25 ),
										value: attributes.excerptLength,
										min: 0,
										max: 80,
										onChange: function ( value ) {
											set( { excerptLength: value || 0 } );
										},
								  } )
								: null,
							el( SelectControl, {
								label: __( 'Publication date', 'ajax-filter-search' ),
								value: attributes.showDate,
								options: showHideFor( 'showDate' ),
								onChange: function ( value ) {
									set( { showDate: value } );
								},
							} ),
							// The time is shown next to the date, so it only matters while the date is shown.
							effective( attributes.showDate, 'showDate' )
								? el( SelectControl, {
										label: __( 'Include the time', 'ajax-filter-search' ),
										value: attributes.showTime,
										options: showHideFor( 'showTime' ),
										onChange: function ( value ) {
											set( { showTime: value } );
										},
								  } )
								: null,
							el( SelectControl, {
								label: __( 'Category badge', 'ajax-filter-search' ),
								value: attributes.showCategory,
								options: showHideFor( 'showCategory' ),
								onChange: function ( value ) {
									set( { showCategory: value } );
								},
							} )
					  )
					: null
			),
			// The preview is server-rendered markup with real links (tabs, pagination). Disabled makes it inert in the editor,
			// so clicking it selects the block instead of navigating the canvas to the block-renderer URL.
			el(
				Disabled,
				null,
				el( ServerSideRender, {
					block: 'ajax-filter-search/feed',
					attributes: attributes,
				} )
			)
		);
	}

	wp.blocks.registerBlockType( 'ajax-filter-search/feed', {
		edit: Edit,
		save: function () {
			return null; // Rendered by PHP.
		},
	} );
}( window.wp ) );
