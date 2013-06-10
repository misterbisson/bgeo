<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin extends bGeo
{
	public function __construct()
	{
		add_action( 'admin_init', array( $this , 'admin_init' ) );
	}

	public function admin_init()
	{
		global $pagenow;

		// only continue if we're on a page related to our post type
		// is there a better way to do this?
		if ( 
			! ( // matches the new post page
				'post-new.php' == $pagenow &&
				isset ( $_GET['post_type'] ) && 
				$this->post_type_name == $_GET['post_type'] 
			) &&
			! ( // matches the editor for our post type
				'post.php' == $pagenow &&
				isset ( $_GET['post'] ) && 
				$this->get_post( $_GET['post'] ) 
			) 
		)
		{
			return;
		}

		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );

		$this->upgrade();
	}

	public function upgrade()
	{
		$options = get_option( $this->post_type_name );

		// initial activation and default options
		if( ! isset( $options['version'] ) )
		{
			$this->init_partsofspeech();

			// init the var
			$options = array();

			// set the options
			$options['active'] = TRUE;
			$options['version'] = $this->version;
		}

		// replace the old options with the new ones
		update_option( $this->post_type_name, $options );
	}

	// register and enqueue any scripts needed for the dashboard
	public function admin_enqueue_scripts()
	{
		wp_register_style( $this->id_base . '-admin' , $this->plugin_url . '/css/' . $this->id_base . '-admin.css' , array() , $this->version );
		wp_enqueue_style( $this->id_base . '-admin' );
		
		wp_register_script( $this->id_base . '-admin', $this->plugin_url . '/js/' . $this->id_base . '-admin.js', array( 'jquery' ), $this->version, true );
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

	// the Details metabox
	public function metabox_details( $post )
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
		// captures the $_POST var, and then to go_analyst()->update_meta(),
		// where the data is sanitized and validated before saving
	}

	// register our metaboxes
	public function metaboxes()
	{
		add_meta_box( $this->get_field_id( 'details' ), 'Details', array( $this, 'metabox_details' ), $this->post_type_name , 'normal', 'default' );
	}

}//end GO_Analyst_Admin class