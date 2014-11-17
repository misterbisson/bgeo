<?php
/*
 * This class allows adds a metabox to the term editor and hooks to created_term and edited_term (like save_post for terms).
 * This is basically the admin UI to a handful of methods in the core bGeo class
 */

class bGeo_Admin_Terms
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

		add_action( 'created_term', array( $this, 'edited_term' ), 5, 3 );
		add_action( 'edited_term', array( $this, 'edited_term' ), 5, 3 );
		// the above are only relevant to interactive term creation and updates
		// term deletion (which can happen without human interaction) is handled in the main bGeo class
	}//end init

	/**
	 * Keep it going on admin_init!
	 */
	public function admin_init()
	{
		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		//add the geo metabox to each of the taxonomies we're registered against
		foreach ( $this->bgeo->options()->taxonomies as $taxonomy )
		{
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'metabox' ), 5, 2 );
		}
	}//end admin_init

	/**
	 * Setup scripts to support term editing
	 */
	public function admin_enqueue_scripts( $hook_suffix )
	{
		switch ( $hook_suffix )
		{
			case 'edit-tags.php':
				wp_enqueue_script(
					'bgeo-admin-terms',
					$this->bgeo->plugin_url . '/js/bgeo-admin-terms.js',
					array( 'bgeo-leaflet' ),
					$this->bgeo->admin()->script_config()->version,
					TRUE
				);
				// script data is localized in the metabox template

				wp_enqueue_style(
					'bgeo-admin-terms',
					$this->bgeo->plugin_url . '/css/bgeo-admin-terms.css',
					array( 'bgeo-leaflet' ),
					$this->bgeo->admin()->script_config()->version
				);
				break;

			default:
				return;
		}//end switch
	}//end admin_enqueue_scripts

	/**
	 * Hooked to edited_term to capture details from the term metabox
	 *
	 * This is like the save_post hook for terms.
	 */
	public function edited_term( $term_id, $unused_tt_id, $taxonomy )
	{
		// check the nonce
		if ( ! $this->bgeo->admin()->verify_nonce() )
		{
			return;
		}

		// check the permissions
		$tax = get_taxonomy( $taxonomy );
		if ( ! current_user_can( $tax->cap->edit_terms ) )
		{
			return;
		}

		// save it
		$this->bgeo->update_geo( $term_id, $taxonomy, stripslashes_deep( $_POST[ $this->bgeo->id_base ] ) );
	}//end edited_term

	/**
	 * Add a metabox showing the map and extra geo fields to the term editor in our taxonomy
	 */
	// the metabox on terms
	public function metabox( $tag, $taxonomy )
	{
		// must have this on the page in one of the metaboxes
		// the nonce is then checked in $this->save_post()
		$this->bgeo->admin()->nonce_field();


		if ( empty( $tag ) || empty( $taxonomy ) )
		{
			echo 'No tag or taxonomy set.';
			return;
		}

		// add the form elements you want to use here.
		// these are regular html form elements, but use $this->get_field_name( 'name' ) and $this->get_field_id( 'name' ) to identify them

		include_once __DIR__ . '/templates/term-metabox.php';

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
}//end class