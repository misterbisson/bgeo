<?php
class bGeo
{
	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $version = 1;
	public $id_base = 'bgeo';

	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . 'bgeo';

		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

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