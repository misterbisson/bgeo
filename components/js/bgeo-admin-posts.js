(function($){
	'use strict';

	bgeo.init = function() {
		$(document).on( 'click', '.bgeo-taggroup', bgeo.tags_toggle );
		$(document).on( 'click', '.bgeo-use', bgeo.tag_use );
		$(document).on( 'click', '.bgeo-refresh', bgeo.tag_refresh );

		bgeo.first_run = true;

	  	bgeo.setup_templates();
		bgeo.prep_metaboxes();

		$( '#post input:first' ).after( bgeo.templates.nonce( { nonce: bgeo.nonce } ) );

		// Call the API
		bgeo.locationsfromtext();
	};

	// Initialize some templates for use later
	bgeo.setup_templates = function() {
		bgeo.templates = {
			tags:   Handlebars.compile( $( '#bgeo-handlebars-tags' ).html() ),
			nonce:  Handlebars.compile( $( '#bgeo-handlebars-nonce' ).html() ),
			tag:    Handlebars.compile( $( '#bgeo-handlebars-tag' ).html() ),
		};
	};

	// Prep the tag metaboxes with the initial interface
	bgeo.prep_metaboxes = function() {
		$( '.the-tags' ).each(function() {
			var taxonomy = $( this ).attr( 'id' ).substr( 10 );

			// Add suggestions interface to metaboxes
			if ( bgeo.local_taxonomies.hasOwnProperty( taxonomy ) ) {
				$( '#tagsdiv-' + taxonomy + ' .inside' ).append( bgeo.templates.tags );
			}//end if
		});
	};

	// Call the API and get the suggested tags
	bgeo.locationsfromtext = function() {
		var params = {
			'action': 'bgeo-locationsfromtext',
			'post_id': bgeo.post_id,
			'nonce': bgeo.nonce
		};

		$.getJSON( ajaxurl, params, bgeo.locationsfromtext_callback );
	};

	// Handle response from API
	bgeo.locationsfromtext_callback = function( data, text_status, xhr ) {
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
				bgeo.locationsfromtext_taxonomy( taxonomy, taxonomies[ taxonomy ] );
			} else {
				bgeo.locationsfromtext_taxonomy( taxonomy, false );
			}
		});

		$(document).trigger( 'bgeo.complete' );

		bgeo.first_run = false;
	};

	// Handle suggestions for a given taxonomy
	bgeo.locationsfromtext_taxonomy = function( taxonomy, bgeo_objects ) {
		var $inside = $( '#tagsdiv-' + taxonomy + ' .inside');

		if ( false === bgeo_objects ) {
			$inside.find( '.bgeo-suggested-list' ).html( 'No suggestions found' );
			return;
		}

		if ( ! bgeo.suggested_terms.hasOwnProperty( taxonomy ) ) {
			bgeo.suggested_terms[ taxonomy ] = {};
		}//end if

		// build list of existing tags
		var existing_tags_hash = {};

		$.each( $inside.find( '.the-tags' ).val().split(','), function( key, tag ){
			existing_tags_hash[ tag.trim() ] = true;
		});

		// compile suggested tags
		$.each( bgeo_objects, function( idx, obj ) {
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

	// Use a geography tag
	bgeo.tag_use = function( e ) {
		tagBox.flushTags( $( this ).closest( '.inside' ).children( '.tagsdiv' ), this );

		// Remove tag after it's added
		$( this ).parent().remove();

		e.preventDefault();
	};

	// Manually refresh the tag list
	bgeo.tag_refresh = function( e ) {
		var params = {
			'action': 'bgeo-locationsfromtext',
			'content': $( 'input[name="post_title"]' ).val()  + '\n\n' + $( '#excerpt' ).val() + '\n\n' + $( '.wp-editor-area' ).val(),
			'post_id': bgeo.post_id,
			'nonce': bgeo.nonce
		};

		$( '.bgeo-suggested-list' ).html( 'Refreshing...' );

		$.post( ajaxurl, params, bgeo.locationsfromtext_callback, 'json' );

		e.preventDefault();
	};

	$(function() {
		bgeo.init();
	});
})(jQuery);
