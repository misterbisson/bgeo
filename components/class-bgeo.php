<?php
class bGeo
{
	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $version = 1;
	public $id_base = 'bgeo';

	public $meta_defaults = array(
		'word' => '',
		'pronunciation' => '',
		'partofspeech' => 'noun',
	);

	public function __construct()
	{
		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

		add_action( 'init' , array( $this, 'register_post_type' ), 12 );

		add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ));

		// intercept attempts to save the post so we can update our meta
		add_action( 'save_post', array( $this, 'save_post' ) );

		if ( is_admin() )
		{
			$this->admin();
		}

	} // END __construct

	// a singleton for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once dirname( __FILE__ ) . '/class-bgeo-admin.php';
			$this->admin = new bGeo_Admin();
			$this->admin->plugin_url = $this->plugin_url;
		}

		return $this->admin;
	} // END admin

	// a singleton for the tools object
	public function tools()
	{
		if ( ! $this->tools )
		{
			require_once dirname( __FILE__ ) . '/class-bgeo-tools.php';
			$this->tools = new bGeo_Tools();
		}

		return $this->tools;
	} // END tools

	// wrapper method for metaboxes in the admin object, allows lazy loading
	public function metaboxes()
	{
		$this->admin()->metaboxes();
	} // END metaboxes

} // END bGeo class

// Singleton
function bgeo()
{
	global $bgeo;

	if ( ! $bgeo )
	{
		$bgeo = new bGeo();
	}

	return $bgeo;
} // END bgeo