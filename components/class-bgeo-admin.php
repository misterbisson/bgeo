<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin extends bGeo
{
	public function __construct()
	{
		add_action( 'admin_init', array( $this , 'admin_init' ) );

		add_action( 'created_term', array( $this , 'edited_term' ), 5, 3 );
		add_action( 'edited_term', array( $this , 'edited_term' ), 5, 3 );
		add_action( 'delete_term', array( $this , 'delete_term' ), 5, 4 );
	}

	public function admin_init()
	{
		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );

		$this->upgrade();

		//add the geo metabox to each of the taxonomies we're registered against
		foreach ( bgeo()->options()->taxonomies as $taxonomy )
		{
			add_action( $taxonomy . '_edit_form_fields', array( $this , 'metabox' ), 5, 2 );
		}
	}

	// register and enqueue any scripts needed for the dashboard
	public function admin_enqueue_scripts()
	{
		wp_register_style( $this->id_base . '-admin' , bgeo()->plugin_url . '/css/' . $this->id_base . '-admin.css' , array( $this->id_base . '-leaflet' ) , $this->version );
		wp_enqueue_style( $this->id_base . '-admin' );

		wp_register_script( $this->id_base . '-admin', bgeo()->plugin_url . '/js/' . $this->id_base . '-admin.js', array( $this->id_base . '-leaflet' ), $this->version, TRUE );
		wp_enqueue_script( $this->id_base . '-admin');

	}//end admin_enqueue_scripts

	public function nonce_field()
	{
		wp_nonce_field( plugin_basename( __FILE__ ) , $this->id_base .'-nonce' );
	}

	public function verify_nonce()
	{
		return wp_verify_nonce( $_POST[ $this->id_base .'-nonce' ] , plugin_basename( __FILE__ ));
	}

	public function get_field_name( $field_name )
	{
		return $this->id_base . '[' . $field_name . ']';
	}

	public function get_field_id( $field_name )
	{
		return $this->id_base . '-' . $field_name;
	}

	public function edited_term( $term_id, $tt_id, $taxonomy )
	{

		// check the nonce
		if( ! $this->verify_nonce() )
		{
			return;
		}

		// check the permissions
		$tax = get_taxonomy( $taxonomy );
		if( ! current_user_can( $tax->cap->edit_terms ) )
		{
			return;
		}

		// save it
		$this->update_geo( $term_id, $taxonomy, stripslashes_deep( $_POST[ $this->id_base ] ) );
	}

	public function delete_term( $term, $tt_id, $taxonomy, $deleted_term )
	{
		// check the permissions
		$tax = get_taxonomy( $taxonomy );
		if( ! current_user_can( $tax->cap->edit_terms ) )
		{
			return;
		}

		// delete it
		$this->delete_geo( $term_id, $taxonomy, $deleted_term );
	}

	// the metabox
	public function metabox( $tag, $taxonomy )
	{
		// must have this on the page in one of the metaboxes
		// the nonce is then checked in $this->save_post()
		$this->nonce_field();

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
		// captures the $_POST var, and then to bgeo()->update_meta(),
		// where the data is sanitized and validated before saving
	}

	public function upgrade()
	{
		$options = get_option( $this->id_base );

		// initial activation and default options
		if( ! isset( $options['version'] ) )
		{
			// create the table
			$this->create_table();

			// set the options
			$options['version'] = $this->version;
		}

		// replace the old options with the new ones
		update_option( $this->id_base , $options );
	}

	function create_table()
	{
		global $wpdb;

		$charset_collate = '';
		if ( version_compare( mysql_get_server_info() , '4.1.0', '>=' ))
		{
			if ( ! empty( $wpdb->charset ))
			{
				$charset_collate = 'DEFAULT CHARACTER SET '. $wpdb->charset;
			}
			if ( ! empty( $wpdb->collate ))
			{
				$charset_collate .= ' COLLATE '. $wpdb->collate;
			}
		}

		require_once ABSPATH . 'wp-admin/upgrade-functions.php';

		dbDelta("
			CREATE TABLE " . bgeo()->table . " (
				`term_taxonomy_id` bigint(20) unsigned NOT NULL,
				`point` point NOT NULL DEFAULT '',
				`bounds` geometrycollection NOT NULL DEFAULT '',
				`area` int(10) unsigned NOT NULL,
				PRIMARY KEY (`term_taxonomy_id`),
				SPATIAL KEY `point` (`point`),
				SPATIAL KEY `bounds` (`bounds`)
			) ENGINE=MyISAM $charset_collate
		");
	}

}//end bGeo_Admin class