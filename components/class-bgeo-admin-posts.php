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
		add_action( 'wp_ajax_bgeo_locationsfromtext', array( $this, 'ajax_locationsfromtext' ) );
	}

	public function admin_init()
	{
		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );

		//add the geo metabox to the posts
		// add_action( 'add_meta_boxes', array( $this, 'post_metaboxes' ), 10, 1 );
	}

	/**
	 * Setup scripts and check dependencies for the admin interface
	 */
	public function admin_enqueue_scripts( $hook_suffix )
	{
		switch ( $hook_suffix )
		{
			case 'post.php':
/*
				wp_enqueue_script(
					'bgeo-admin-posts',
					$this->bgeo->plugin_url . '/js/bgeo-admin-posts.js',
					array( 'jquery', 'handlebars' ),
					$this->bgeo->admin()->script_config()->version,
					TRUE
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
					'post_id'          => $post->ID,
					'nonce'            => wp_create_nonce( 'bgeo' ),
					'ignored_by_tax'   => isset( $meta['ignored-tags'] ) ? $meta['ignored-tags'] : array(),
					'suggested_terms'  => array(),
				);

				wp_localize_script( 'bgeo-admin-posts', 'bgeo', $localized_values );
				add_action( 'admin_footer-post.php', array( $this, 'action_admin_footer_post' ) );
*/
				break;

			default:
				return;
		}
	}//end admin_enqueue_scripts

	/**
	 * Set handlebars.js templates
	 */
	public function action_admin_footer_post()
	{
		global $action;

		if ( 'edit' !== $action )
		{
			return;
		}//end if
		?>
		<script id="bgeo-handlebars-tags" type="text/x-handlebars-template">
			<div class="bgeo">
				<div>
					<a href="#" class="bgeo-taggroup bgeo-suggested">Suggested tags</a>
					<a href="#" class="bgeo-refresh">Refresh</a>
					<div class="bgeo-taglist bgeo-suggested-list">Refreshing...</div>
				</div>
				<div>
					<a href="#" class="bgeo-taggroup bgeo-ignored" style="display: none;">Ignored tags</a>
					<div style="display: none;" class="bgeo-taglist bgeo-ignored-list"></div>
				</div>
			</div>
		</script>
		<script id="bgeo-handlebars-nonce" type="text/x-handlebars-template">
			<input type="hidden" id="bgeo-nonce" name="bgeo-nonce" value="{{nonce}}" />
		</script>
		<script id="bgeo-handlebars-ignore" type="text/x-handlebars-template">
			<textarea name="tax_ignore[{{taxonomy}}]" class="the-ignored-tags" id="tax-ignore-{{taxonomy}}">{{ignored_taxonomies}}</textarea>
		</script>
		<script id="bgeo-handlebars-tag" type="text/x-handlebars-template">
			<span><a class="bgeo-ignore" title="Ignore tag"><i class="fa fa-times-circle"></i></a>&nbsp;<a class="bgeo-use">{{name}}</a></span>
		</script>
		<?php
	}//end action_admin_footer_post

	// should we add our metabox to this post type?
	public function post_metaboxes( $post_type )
	{
		add_meta_box(
			$this->get_field_id( 'post_metabox' ),
			'Locations',
			array( $this , 'post_metabox' ),
			$post_type,
			'normal',
			'high'
		);
	}

	// the metabox on posts
	public function post_metabox( $post )
	{
		// must have this on the page in one of the metaboxes
		// the nonce is then checked in $this->save_post()
		$this->nonce_field();


		if( empty( $tag ) || empty( $taxonomy ) )
		{
			echo 'No tag or taxonomy set.';
			return;
		}

		// add the form elements you want to use here.
		// these are regular html form elements, but use $this->get_field_name( 'name' ) and $this->get_field_id( 'name' ) to identify them

		include_once __DIR__ . '/templates/metabox-details.php';

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
	}

	public function get_post_meta( $post_id )
	{
		if ( ! $meta = get_post_meta( $post_id, $this->bgeo->id_base, TRUE ) )
		{
			return array();
		} // END if

		return $meta;
	} // END get_post_meta

	public function update_post_meta( $post_id, $meta )
	{
		return update_post_meta( $post_id, $this->bgeo->post_meta_key, $meta );
	} // END update_post_meta

	/**
	 * post_id is required
	 * text is optional
	 */
	public function ajax_locationsfromtext()
	{
		// Check nonce
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'bgeo' ) )
		{
			wp_send_json_error( array( 'message' => 'You do not have permission to be here.' ) );
		}// end if

		// content may be passed in via POST
		$text = NULL;
		$post_id = NULL;

		if ( isset( $_REQUEST['text'] ) )
		{
			$text = wp_kses_data( $_REQUEST['text'] );
		}//end if

		if ( isset( $_REQUEST['post_id'] ) )
		{
			$post_id = absint( $_REQUEST['post_id'] );
		}//end if

		if ( NULL === $post_id )
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

/*
this needs to be passed to the next method from $text

		// Override post content for this request if needed
		if ( $content )
		{
			$post->post_content = $content;
		}//end if
*/

		/*
		get the api result
		$result = api result;
		*/

		if ( is_wp_error( $result ) )
		{
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}//end if

		/*
		save the suggestions back to the post
		$result = save;
		*/

		if ( is_wp_error( $result ) )
		{
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}//end if

		// Send the response back
		wp_send_json( $enrich->response );
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
			}

			// assemble everything and apply filters so other plugins can get involved
			$text = apply_filters(
				'bgeo_text',
				$post->post_title . "\n\n" . $post->post_excerpt . "\n\n" . $post->post_content . "\n\n" . implode( "\n", $terms ),
				$post
			);

			// check the API
			$locations = NULL;
/*
			$meta = $this->get_post_meta( $this->post->ID );
			$meta['enrich']            = json_encode( $this->response );
			$meta['enrich_unfiltered'] = json_encode( $this->response_raw );
			update_post_meta( $this->post->ID, $this->bgeo->post_meta_key, $meta );
*/
		}//end if
	}

}//end bGeo_Admin_Posts class