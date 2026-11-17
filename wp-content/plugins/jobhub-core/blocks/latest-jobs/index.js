/**
 * Editor UI for the Latest Jobs block. Written with wp.element.createElement
 * so the plugin needs no build step.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, RangeControl, SelectControl, ToggleControl } = wp.components;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	registerBlockType( 'jobhub/latest-jobs', {
		edit( { attributes, setAttributes } ) {
			const categories = useSelect(
				( select ) => select( 'core' ).getEntityRecords( 'taxonomy', 'job_category', { per_page: 100 } ) || [],
				[]
			);

			const options = [ { label: __( 'All categories', 'jobhub' ), value: '' } ].concat(
				categories.map( ( c ) => ( { label: c.name, value: c.slug } ) )
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'jobhub' ) },
						el( RangeControl, {
							label: __( 'Number of jobs', 'jobhub' ),
							min: 1,
							max: 12,
							value: attributes.count,
							onChange: ( count ) => setAttributes( { count } ),
						} ),
						el( SelectControl, {
							label: __( 'Category', 'jobhub' ),
							value: attributes.category,
							options,
							onChange: ( category ) => setAttributes( { category } ),
						} ),
						el( ToggleControl, {
							label: __( 'Featured jobs only', 'jobhub' ),
							checked: attributes.featuredOnly,
							onChange: ( featuredOnly ) => setAttributes( { featuredOnly } ),
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( ServerSideRender, { block: 'jobhub/latest-jobs', attributes } )
				)
			);
		},
		save: () => null,
	} );
} )( window.wp );
