(function($){
	'use strict';

	bgeo.init = function() {
		$(document).on( 'click', '.bgeo-taggroup', bgeo.tags_toggle );
		$(document).on( 'click', '.bgeo-use', bgeo.tag_use );
		$(document).on( 'click', '.bgeo-ignore', bgeo.tag_ignore );
		$(document).on( 'click', '.bgeo-refresh', bgeo.tag_refresh );

		bgeo.first_run = true;

	  	bgeo.setup_templates();
		bgeo.prep_metaboxes();

		$( '#post input:first' ).after( bgeo.templates.nonce( { nonce: bgeo.nonce } ) );

		// Call the API
		bgeo.enrich();
	};

	// Initialize some templates for use later
	bgeo.setup_templates = function() {
		bgeo.templates = {
			tags:   Handlebars.compile( $( '#bgeo-handlebars-tags' ).html() ),
			nonce:  Handlebars.compile( $( '#bgeo-handlebars-nonce' ).html() ),
			ignore: Handlebars.compile( $( '#bgeo-handlebars-ignore' ).html() ),
			tag:    Handlebars.compile( $( '#bgeo-handlebars-tag' ).html() ),
		};
	};

	// Prep the tag metaboxes with the initial interface
	bgeo.prep_metaboxes = function() {
		$( '.the-tags' ).each(function() {
			var taxonomy = $( this ).attr( 'id' ).substr( 10 );

			// Settup ignored tags inputs
			if ( bgeo.ignored_by_tax.hasOwnProperty( taxonomy ) ) {
				$( bgeo.templates.ignore({
					taxonomy: taxonomy,
					ignored_taxonomies: bgeo.ignored_by_tax[ taxonomy ].join( ',' )
				}) ).insertAfter( this );
			} else if ( bgeo.local_taxonomies.hasOwnProperty( taxonomy ) ) {
				$( bgeo.templates.ignore({
					taxonomy: taxonomy,
					ignored_taxonomies: ''
				}) ).insertAfter( this );
			}//end else if

			// Add suggestions interface to metaboxes
			if ( bgeo.local_taxonomies.hasOwnProperty( taxonomy ) ) {
				$( '#tagsdiv-' + taxonomy + ' .inside' ).append( bgeo.templates.tags );
			}//end if
		});
	};

	// Call the API and get the suggested tags
	bgeo.enrich = function() {
		var params = {
			'action': 'bgeo_enrich',
			'post_id': bgeo.post_id,
			'nonce': bgeo.nonce
		};

		$.getJSON( ajaxurl, params, bgeo.enrich_callback );
	};

	// Handle response from API
	bgeo.enrich_callback = function( data, text_status, xhr ) {
		// container of our local taxonomies
		var taxonomies = {};

		for ( var prop in bgeo.taxonomy_map ) {
			taxonomies[ bgeo.taxonomy_map[ prop ] ] = [];
		}//end for

		// Look at terms returned and add terms to their matching local taxonomy
		$.each( data, function( idx, obj ) {
			var type = obj._type;

			if ( bgeo.taxonomy_map.hasOwnProperty( type ) ) {
				taxonomies[ bgeo.taxonomy_map[ type ] ].push( obj );
			}//end if
		});

		$.each( bgeo.local_taxonomies, function( taxonomy ) {
			if ( taxonomies.hasOwnProperty( taxonomy ) && taxonomies[ taxonomy ].length  ) {
				bgeo.enrich_taxonomy( taxonomy, taxonomies[ taxonomy ] );
			} else {
				bgeo.enrich_taxonomy( taxonomy, false );
			}
		});

		$(document).trigger( 'bgeo.complete' );

		bgeo.first_run = false;
	};

	// Handle suggestions for a given taxonomy
	bgeo.enrich_taxonomy = function( taxonomy, bgeo_objects ) {
		var $inside = $( '#tagsdiv-' + taxonomy + ' .inside');

		if ( false === bgeo_objects ) {
			$inside.find( '.bgeo-suggested-list' ).html( 'No suggestions found' );
			$inside.find( '.bgeo-ignored' ).hide();
			return;
		} else {
			$inside.find( '.bgeo-ignored' ).show();
		}

		if ( ! bgeo.suggested_terms.hasOwnProperty( taxonomy ) ) {
			bgeo.suggested_terms[ taxonomy ] = {};
		}//end if

		// build list of existing tags
		var existing_tags_hash = {};

		$.each( $inside.find( '.the-tags' ).val().split(','), function( key, tag ){
			existing_tags_hash[ tag.trim() ] = true;
		});

		var ignored_tags_hash = {};
		var ignored_tags = '';

		// build list of ignored tags
		$.each( $inside.find( '.the-ignored-tags' ).val().split(','), function( key, tag ){
			tag = tag.trim();

			// skip empty tags (usually if .val() above was zero length
			if ( '' === tag ) {
				return;
			}//end if

			// skip tags that are already in use
			if ( existing_tags_hash.hasOwnProperty( tag ) ) {
				return;
			}//end if

			if ( bgeo.first_run ) {
				ignored_tags = ignored_tags + bgeo.templates.tag( { name: tag } );
			}//end if

			ignored_tags_hash[ tag ] = true;
		});

		if ( '' !== ignored_tags ) {
			$inside.find( '.bgeo-ignored-list' ).html( ignored_tags );
		} else {
			$inside.find( '.bgeo-ignored-list' ).html( 'None' );
		}//end else

		// compile suggested tags
		$.each( bgeo_objects, function( idx, obj ) {
			if ( ignored_tags_hash[ obj.name.trim() ] || existing_tags_hash[ obj.name.trim() ] ) {
				return;
			}//end if

			if ( ! bgeo.suggested_terms[ taxonomy ].hasOwnProperty( obj.name ) ) {
				bgeo.suggested_terms[ taxonomy ][ obj.name ] = true;
			}//end if
		});

		var suggested_tags = '';

		$.each( bgeo.suggested_terms[ taxonomy ], function( tag ) {
			suggested_tags = suggested_tags + bgeo.templates.tag( { name: tag } );
		});

		if ( '' !== suggested_tags ) {
			$inside.find( '.bgeo-suggested-list' ).html( suggested_tags );
		} else {
			$inside.find( '.bgeo-suggested-list' ).html( 'No suggestions found' );
		}//end else
	};

	// Toggle taglist
	bgeo.tags_toggle = function( e ) {
		var $obj = $( e.currentTarget );
		$obj.nextAll( '.bgeo-taglist' ).toggle();
		e.preventDefault();
	};

	// Use an geography tag
	bgeo.tag_use = function( e ) {
		tagBox.flushTags( $( this ).closest( '.inside' ).children( '.tagsdiv' ), this );

		// Remove tag after it's added
		$( this ).parent().remove();

		e.preventDefault();
	};

	// Toggle a suggested tag
	bgeo.tag_ignore = function( e ) {
		var $tag = $( this ).parent();
		var $inside = $tag.closest( '.inside' );
		var $ignored_tag_list = $inside.find( '.bgeo-ignored-list' );

		if ( 'None' === $ignored_tag_list.html() ) {
			$ignored_tag_list.html('');
		}//end if

		$tag.appendTo( $ignored_tag_list );

		var tag_name = $tag.find( '.bgeo-use' ).text();
		var taxonomy = $inside.find( '.tagsdiv' ).attr( 'id' );

		delete bgeo.suggested_terms[ taxonomy ][ tag_name ];

		// Get current ignored tags
		var $ignored_tags = $inside.find( '.the-ignored-tags' );
		var ignored_tags_value = $ignored_tags.val();

		// Add newly ignored tag to the list
		var new_value = ignored_tags_value ? ignored_tags_value + ',' + tag_name : tag_name;
		new_value = tagBox.clean( new_value );
		new_value = array_unique_noempty( new_value.split(',') ).join(',');

		// Update the ignored tags value
		$ignored_tags.val( new_value );

		e.preventDefault();
	};

	// Manually refresh the tag list
	bgeo.tag_refresh = function( e ) {
		var params = {
			'action': 'bgeo_enrich',
			'content': $( 'input[name="post_title"]' ).val()  + '\n\n' + $( '#excerpt' ).val() + '\n\n' + $( '.wp-editor-area' ).val(),
			'post_id': bgeo.post_id,
			'nonce': bgeo.nonce
		};

		$( '.bgeo-suggested-list' ).html( 'Refreshing...' );

		$.post( ajaxurl, params, bgeo.enrich_callback, 'json' );

		e.preventDefault();
	};

	$(function() {
		bgeo.init();
	});
})(jQuery);
