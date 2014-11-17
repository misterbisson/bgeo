<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin_Posts
{
	private $bgeo = NULL;

	/**
	 * constructor
	 */
	public function __construct( $bgeo )
	{
		$this->bgeo = $bgeo;
		add_action( 'init', array( $this, 'init' ) );
	}//end __construct

	/**
	 * Start things up!
	 */
	public function init()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_bgeo-locationsfromtext', array( $this, 'ajax_locationsfromtext' ) );
		add_action( 'wp_ajax_bgeo-locationlookup', array( $this, 'ajax_locationlookup' ) );
	}//end init

	/**
	 * Keep the ball rolling on admin_init!
	 */
	public function admin_init()
	{
		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		//add the geo metabox to the posts
		add_action( 'add_meta_boxes', array( $this, 'metaboxes' ), 10, 1 );
	}

	/**
	 * Setup scripts and check dependencies for the admin interface
	 */
	public function admin_enqueue_scripts( $hook_suffix )
	{
		switch ( $hook_suffix )
		{
			case 'post.php':

				wp_enqueue_script(
					'bgeo-admin-posts',
					$this->bgeo->plugin_url . '/js/bgeo-admin-posts.js',
					array( 'jquery', 'handlebars' ),
					$this->bgeo->admin()->script_config()->version,
					TRUE
				);

				wp_enqueue_script(
					'bgeo-angular',
					'https://ajax.googleapis.com/ajax/libs/angularjs/1.3.2/angular.min.js',
					array(),
					$this->bgeo->admin()->script_config()->version,
					FALSE
				);

				wp_enqueue_style(
					'bgeo-admin-posts',
					$this->bgeo->plugin_url . '/css/bgeo-admin-posts.css',
					array( 'fontawesome' ),
					$this->bgeo->admin()->script_config()->version
				);

				$post = get_post();
				$meta = $this->get_post_meta( $post->ID );

				$localized_values = array(
					'endpoint'        => admin_url( '/admin-ajax.php?action=bgeo-' ),
					'nonce'           => wp_create_nonce( 'bgeo' ),
					'post_id'         => $post->ID,
					'geo_suggestions' => (object) array(),
					'post_geos'       => (object) $this->bgeo->get_object_primary_geos( $post->ID ),
				);

				wp_localize_script( 'bgeo-admin-posts', 'bgeo', $localized_values );

				break;

			default:
				return;
		}//end switch
	}//end admin_enqueue_scripts

	/**
	 * Conditionally adds our metabox to posttypes specified in the whitelist
	 */
	public function metaboxes( $post_type )
	{
		// is this in our post type whitelist?
		if ( ! in_array( $post_type, $this->bgeo->post_types ) )
		{
			return;
		}

		add_meta_box(
			$this->bgeo->admin()->get_field_id( 'post-metabox' ),
			'Locations',
			array( $this, 'metabox' ),
			$post_type,
			'normal',
			'high'
		);
	}//end metaboxes

	/**
	 * the metabox on posts
	 */
	public function metabox( $post )
	{
		// must have this on the page in one of the metaboxes
		// the nonce is then checked in $this->save_post()
		$this->bgeo->admin()->nonce_field();


		// add the form elements you want to use here.
		// these are regular html form elements, but use $this->get_field_name( 'name' ) and $this->get_field_id( 'name' ) to identify them

		include_once __DIR__ . '/templates/post-metabox.php';

		// be sure to use proper validation on user input displayed here
		// http://codex.wordpress.org/Data_Validation

		// use checked() or selected() for checkboxes and select lists
		// http://codex.wordpress.org/Function_Reference/selected
		// http://codex.wordpress.org/Function_Reference/checked
		// there are other convenience methods in WP, as well

		// when saved, the form elements will be passed to
		// $this->save_post(), which simply checks permissions and
		// captures the $_POST var, and then passes it to
		// bgeo()->update_meta(), where the data is sanitized and
		// validated before saving
	}//end metabox

	/**
	 * Gets the taxonomy term IDs from geo objects, including the terms specified in the belongtos
	 */
	public function get_term_ids_from_geo( $geo )
	{
		if (
			! is_object( $geo ) ||
			! isset( $geo->term_id )
		)
		{
			return array();
		}

		// add this term, and its immediate belongtos to the post
		$terms = array();
		$terms[] = (int) $geo->term_id;

		if ( ! isset( $geo->belongtos ) )
		{
			return $terms;
		}

		foreach ( $geo->belongtos as $belongto )
		{
			$geo = $this->bgeo->get_geo_by_api_id( $belongto->api, $belongto->api_id );

			if ( ! is_wp_error( $geo ) )
			{
				$terms[] = (int) $geo->term_id;
			}
		}

		return $terms;
	}//end get_term_ids_from_geo

	/**
	 * A convenience method for core get_post_meta()
	 */
	public function get_post_meta( $post_id )
	{
		if ( ! $meta = get_post_meta( $post_id, $this->bgeo->id_base, TRUE ) )
		{
			return (object) array( 'primary' => array() );
		} // END if

		return (object) $meta;
	}// END get_post_meta

	/**
	 * Takes as may be saved from the post metabox and saves it to the post.
	 * For backwards compatibility with existing WP geodata practice, it also gets location information specified there.
	 *
	 * @TODO: this method should accept data directly from $this->get_post_meta() and losslessly save it.
	 */
	public function update_post_meta( $post_id, $meta )
	{
		$term_ids = $primary_geos = array();

		// get the location from core geo meta, if it exists
		if (
			( $core_location = $this->bgeo->admin()->postmeta()->update_location_from_core_geo_meta( $post_id ) ) &&
			is_object( $core_location ) &&
			isset( $core_location->term_id )
		)
		{
			$primary_geos[] = $core_location;
			$term_ids = array_merge( $term_ids, $this->get_term_ids_from_geo( $core_location ) );
		}

		// get the locations from the post form
		if ( isset( $meta['term'] ) && is_array( $meta['term'] ) )
		{
			foreach ( $meta['term'] as $slug => $unused )
			{
				$geo = $this->bgeo->get_geo_by( 'slug', $slug );
				$primary_geos[] = $geo;
				$term_ids = array_merge( $term_ids, $this->get_term_ids_from_geo( $geo ) );
			}
		}

		// now save everything
		wp_set_object_terms( $post_id, $term_ids, $this->bgeo->geo_taxonomy_name, FALSE );
		update_post_meta( $post_id, $this->bgeo->id_base, (object) array(
			'primary' => $primary_geos,
		) );
	}// END update_post_meta

	/**
	 * Hooked to save_post to capture the contents of our metabox and save that data to the post
	 */
	public function save_post( $unused_post_id, $post )
	{
		// Check nonce
		if ( ! $this->bgeo->admin()->verify_nonce() )
		{
			return;
		}// end if

		// Check that this isn't an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		{
			return;
		}// end if

		if ( ! is_object( $post ) )
		{
			return;
		}// end if

		// check post type is in our whitelist
		if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, $this->bgeo->post_types ) )
		{
			return;
		}// end if

		// Don't run on post revisions (almost always happens just before the real post is saved)
		if ( wp_is_post_revision( $post->ID ) )
		{
			return;
		}// end if

		// Check the permissions
		if ( ! current_user_can( 'edit_post', $post->ID ) )
		{
			return;
		}// end if

		$this->update_post_meta( $post->ID, stripslashes_deep( $_POST['bgeo'] ) );

	}// END save_post

	/**
	 * An ajax wrapper for locationsfromtext()
	 */
	public function ajax_locationsfromtext()
	{
/*
		// Check nonce
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'bgeo' ) )
		{
			wp_send_json_error( array( 'message' => 'You do not have permission to be here.' ) );
		}// end if
*/
		// text may be passed in via POST
		// ...is taken from post content otherwise
		$text = NULL;
		$post_id = NULL;

		if ( isset( $_REQUEST['text'] ) )
		{
			// sanitization here is sort of redundent, but better to be sure here
			$text = wp_kses( $_REQUEST['text'], array() );
		}//end if

		if ( isset( $_REQUEST['post_id'] ) )
		{
			$post_id = absint( $_REQUEST['post_id'] );
		}//end if

		if ( ! $post_id )
		{
			wp_send_json_error( array( 'message' => 'No post_id provided.' ) );
		}//end if

		if ( ! ( $post = get_post( $post_id ) ) )
		{
			wp_send_json_error( array( 'message' => 'This is not a valid post.' ) );
		}//end if

		if ( ! current_user_can( 'edit_post', $post_id ) )
		{
			wp_send_json_error( array( 'message' => 'You do not have permission to edit this post.' ) );
		}//end if

		$locations = $this->locationsfromtext( $post_id, $text );

		if ( FALSE === $locations )
		{
			wp_send_json_error( array( 'message' => 'There was an API error.' ) );
		}//end if

		wp_send_json( $locations );
	}//end ajax_locationsfromtext

	/**
	 * Gets suggested locations for a post based on the textual content of the post, or explicitely provided text.
	 *
	 * post_id is required
	 * text is optional, it will be taken from post content, excerpt, title, and tags if not provided.
	 *
	 * Uses _locationsfromtext(), see that for more info.
	 */
	public function locationsfromtext( $post_id, $text = NULL )
	{

		if ( ! ( $post = get_post( $post_id ) ) )
		{
			return FALSE;
		}//end if

		// Get the text for this request if none was provided
		if ( ! $text )
		{
			// get the public taxonomies associated with this post type
			$taxonomies = get_taxonomies(
				array(
					'object_type' => $post->post_type,
					'public' => TRUE,
				),
				'names',
				'and'
			);

			// get the terms for this post from all public taxonomies
			$terms = wp_get_object_terms(
				$post->ID,
				$taxonomies,
				array(
					'fields' => 'names',
				)
			);

			// sanity check our terms result, prevents WP_Errors from slipping through
			if ( ! is_array( $terms ) )
			{
				$terms = array();
			}//end if

			// assemble everything and apply filters so other plugins can get involved
			// note that HTML is stripped before sending the text to the API
			$text = apply_filters(
				'bgeo_text',
				$post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post->post_content . "\n\n" . implode( "\n", $terms ),
				$post
			);
		}//end if

		$locations = apply_filters(
			'bgeo_locationsfromtext',
			$this->_locationsfromtext( $text ),
			$post->ID,
			$text
		);

		usort( $locations, array( $this, 'sort_by_relevance' ) );

		return $locations;
	}//end locationsfromtext

	/**
	 * A private helper method used by locationsfromtext()
	 *
	 * Calls the query to the YQL geo table to extract location entities from unstructured text,
	 * then turn those into geo objects.
	 *
	 * The matches can return many low quality results, consider using the go-opencalais connector to improve relevance ranking.
	 */
	private function _locationsfromtext( $text = NULL )
	{

		// We need at least 3 chars to call this api
		if ( 3 > strlen( $text ) )
		{
			return FALSE;
		}//end if

		// check the API
		// API results are cached in the underlying method
		// @TODO: reorder the final sanitization and move it out of the following line
		$query = 'SELECT * FROM geo.placemaker WHERE documentContent = "' . str_replace( '"', '\'', wp_kses( remove_accents( wp_trim_words( $text, 900, '' ) ), array() ) ) . '" AND documentType="text/plain"';
		$raw_entities = bgeo()->yahoo()->yql( $query );

		if ( ! isset( $raw_entities->matches->match ) )
		{
			return FALSE;
		}//end if

		if ( ! is_array( $raw_entities->matches->match ) )
		{
			$raw_entities->matches->match = array( $raw_entities->matches->match );
		}//end if

		$locations = array();
		foreach ( $raw_entities->matches->match as $raw_location )
		{
			// attempt to get the term for this woeid
			$location = bgeo()->new_geo_by_woeid( $raw_location->place->woeId );

			if ( ! $location || is_wp_error( $location ) )
			{
				continue;
			}//end if

			// remove the raw woe object to conserve space
			unset( $location->api_raw );

			$location->relevance = 1;

			$locations[ $location->term_taxonomy_id ] = $location;

			// prefetch the belongto terms
			// @TODO: should this move to the save_post hook?
			if ( isset( $location->belongtos ) && is_array( $location->belongtos ) )
			{
				foreach ( $location->belongtos as $belongto )
				{
					if ( 'woeid' != $belongto->api )
					{
						continue;
					}

					bgeo()->new_geo_by_woeid( $belongto->api_id );
				}
			}//end if
		}//end foreach

		return $locations;
	}//end _locationsfromtext

	/**
	 * A callback to sort terms by relevance
	 */
	public function sort_by_relevance( $a, $b )
	{
		if ( $a->relevance == $b->relevance )
		{
			return 0;
		}

		return $a->relevance < $b->relevance ? 1 : -1;
	}//end sort_by_relevance

	/**
	 * An ajax wrapped for the location search method
	 */
	public function ajax_locationlookup()
	{
/*
		// Check nonce
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'bgeo' ) )
		{
			wp_send_json_error( array( 'message' => 'You do not have permission to be here.' ) );
		}// end if
*/
		// the query string is required
		$query = NULL;
		if ( isset( $_GET['query'] ) )
		{
			// sanitization here is sort of redundent, but better to be sure here
			$query = trim( wp_kses( $_GET['query'], array() ) );
		}//end if

		// the query string must be 3 chars or longer
		if ( 3 > strlen( $query ) )
		{
			wp_send_json_error( array( 'message' => 'There was an API error.' ) );
		}//end if

		$locations = $this->locationlookup( $query );

		if ( FALSE === $locations )
		{
			wp_send_json_error( array( 'message' => 'There was an API error.' ) );
		}//end if

		wp_send_json( $locations );
	}//end ajax_locationlookup

	/**
	 * Get a location specified by an unstructure string
	 * Uses Yahoo!'s YQL geo tables.
	 *
	 * If it can resolve the string to a location, it will be returned as a geo object.
	 *
	 * Examples:
	 *
	 * locationlookup( '504 Broadway San Francisco, CA' ); // returns a geo object for this specific address
	 * locationlookup( 'San Francisco' ); // returns a geo object with the WOEID for the city of SF
	 * locationlookup( '37.798, -122.40567', TRUE ); // returns a geo that should be same/similar to the address used in the first example
	 */
	public function locationlookup( $query = NULL, $reverse_geocode = FALSE )
	{
		// validate that we have a sring, and that it's at least 3 chars
		if (
			! is_string( $query ) ||
			3 > strlen( $query )
		)
		{
			return FALSE;
		}//end if

		if ( $reverse_geocode )
		{
			$reverse_qflag = ' AND gflags="R"';
		}
		else
		{
			$reverse_qflag = '';
		}

		// check the placefinder API
		// API results are cached in the underlying method
		$query = 'SELECT * FROM geo.placefinder WHERE text = "' . str_replace( '"', '\'', $query ) . '"' . $reverse_qflag;
		$raw_result = bgeo()->yahoo()->yql( $query );

		if ( ! isset( $raw_result->Result ) )
		{
			return FALSE;
		}//end if

		if ( ! is_array( $raw_result->Result ) )
		{
			$raw_result->Result = array( $raw_result->Result );
		}//end if

		// get locations from the placemaker api (the two APIs are unioned below
		// this API returns better results for colloquial queries, like "west coast
		$locations = (array) $this->_locationsfromtext( $text );

		// iterate through placefinder API results and add those to the return set
		foreach ( $raw_result->Result as $raw_location )
		{
			// attempt to get the term for this yaddr
			$location = bgeo()->new_geo_by_yaddr( $raw_location );

			if ( ! $location || is_wp_error( $location ) )
			{
				continue;
			}//end if

			// remove the raw woe object to conserve space
			unset( $location->api_raw );

			$locations[ $location->term_taxonomy_id ] = $location;

			// prefetch the belongto terms
			// @TODO: should this move to the save_post hook?
			if ( isset( $location->belongtos ) && is_array( $location->belongtos ) )
			{
				foreach ( $location->belongtos as $belongto )
				{
					if ( 'woeid' != $belongto->api )
					{
						continue;
					}

					bgeo()->new_geo_by_woeid( $belongto->api_id );
				}
			}//end if
		}//end foreach

		return array_filter( $locations );
	}//end locationlookup
}//end class