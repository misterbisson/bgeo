<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin_Posts
{
	private $bgeo = NULL;

	private $dependencies = array(
		'go-ui' => 'https://github.com/GigaOM/go-ui',
	);
	private $missing_dependencies = array();

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
		add_action( 'admin_init', array( $this , 'admin_init' ) );

		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_bgeo-locationsfromtext', array( $this, 'ajax_locationsfromtext' ) );
		add_action( 'wp_ajax_bgeo-locationlookup', array( $this, 'ajax_locationlookup' ) );
	}//end init

	public function admin_init()
	{
		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );

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
					array( ),
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
					'endpoint'        => admin_url( '/admin-ajax.php?action=bgeo-locationsfromtext' ),
					'nonce'           => wp_create_nonce( 'bgeo' ),
					'post_id'         => $post->ID,
					'geo_suggestions' => (object) array(),
					'post_geos'       => (object) $this->bgeo->get_object_geos( $post->ID ),
				);

				wp_localize_script( 'bgeo-admin-posts', 'bgeo', $localized_values );

				break;

			default:
				return;
		}
	}//end admin_enqueue_scripts

	// should we add our metabox to this post type?
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
			array( $this , 'metabox' ),
			$post_type,
			'normal',
			'high'
		);
	}//end metaboxes

	// the metabox on posts
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
	}//end metaboxe

	// @TODO: this method will need to be refactored based on what we do in update_post_meta()
	public function get_post_meta( $post_id )
	{
		if ( ! $meta = get_post_meta( $post_id, $this->bgeo->id_base, TRUE ) )
		{
			return array();
		} // END if

		return $meta;
	} // END get_post_meta

	// @TODO: this method is incomplete
	public function update_post_meta( $post_id, $meta )
	{

		$terms = array();
		if ( isset( $meta['term'] ) && is_array( $meta['term'] ) )
		{
			foreach ( $meta['term'] as $slug => $unused )
			{
				$term = get_term_by( 'slug', $slug, $this->bgeo->geo_taxonomy_name );

				if ( ! is_wp_error( $term ) )
				{
					$terms[] = $term->term_id;
				}
			}
		}

		wp_set_object_terms( $post_id, $terms, $this->bgeo->geo_taxonomy_name, FALSE );

		// return update_post_meta( $post_id, $this->bgeo->post_meta_key, $meta );
	} // END update_post_meta

	public function save_post( $post_id, $post )
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
	 * post_id is required
	 * text is optional
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
	}//end ajax_locationsfromcontent

	/**
	 * post_id is required
	 * text is optional and can be inferred from the text of the post
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

		return $this->_locationsfromtext( $text );
	}//end locationsfromtext

	public function _locationsfromtext( $text = NULL )
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
		}

		return $locations;
	}//end _locationsfromtext

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
	}//end ajax_locationsfromcontent

	public function locationlookup( $query = NULL )
	{
		// validate that we have a sring, and that it's at least 3 chars
		if (
			! is_string( $query ) ||
			3 > strlen( $query )
		)
		{
			return FALSE;
		}//end if

		// check the placefinder API
		// API results are cached in the underlying method
		$query = 'SELECT * FROM geo.placefinder where text = "' . str_replace( '"', '\'', $query ) . '"';
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
		}

		return array_filter( $locations );
	}//end locationlookup

}//end bGeo_Admin_Posts class