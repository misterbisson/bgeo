<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bGeo_Admin
{
	private $bgeo = NULL;
	public $posts = NULL;
	public $terms = NULL;
	public $script_config = NULL;

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
		add_action( 'admin_init', array( $this , 'admin_init' ) );

		$this->terms();
		$this->posts();
	}//end __construct

	// a accessor for the posts object
	public function posts()
	{
		if ( ! $this->posts )
		{
			require_once __DIR__ . '/class-bgeo-admin-posts.php';
			$this->posts = new bGeo_Admin_Posts( $this->bgeo );
		}

		return $this->posts;
	} // END posts

	// a accessor for the terms object
	public function terms()
	{
		if ( ! $this->terms )
		{
			require_once __DIR__ . '/class-bgeo-admin-terms.php';
			$this->terms = new bGeo_Admin_Terms( $this->bgeo );
		}

		return $this->terms;
	} // END terms

	/**
	 * Start things up!
	 */
	public function admin_init()
	{
		$this->upgrade();

		// common to both terms and posts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Setup scripts and check dependencies for the admin interface
	 */
	public function admin_enqueue_scripts( $hook_suffix )
	{
		$this->check_dependencies();

		if ( $this->missing_dependencies )
		{
			return;
		}//end if

		// make sure go-ui has been instantiated and its resources registered
		go_ui();
	}//end admin_enqueue_scripts

	/**
	 * check plugin dependencies
	 */
	public function check_dependencies()
	{
		foreach ( $this->dependencies as $dependency => $url )
		{
			if ( function_exists( str_replace( '-', '_', $dependency ) ) )
			{
				continue;
			}//end if

			$this->missing_dependencies[ $dependency ] = $url;
		}//end foreach

		if ( $this->missing_dependencies )
		{
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}//end if
	}//end check_dependencies

	/**
	 * hooked to the admin_notices action to inject a message if depenencies are not activated
	 */
	public function admin_notices()
	{
		?>
		<div class="error">
			<p>
				You must <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">activate</a> the following plugins before using bGeo:
			</p>
			<ul>
				<?php
				foreach ( $this->missing_dependencies as $dependency => $url )
				{
					?>
					<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $dependency ); ?></a></li>
					<?php
				}//end foreach
				?>
			</ul>
		</div>
		<?php
	}//end admin_notices

	public function script_config()
	{
		if ( ! $this->script_config )
		{
			$this->script_config = (object) apply_filters( 'go-config', array( 'version' => $this->bgeo->version ), 'go-script-version' );
		}

		return $this->script_config;
	}

	public function nonce_field()
	{
		wp_nonce_field( plugin_basename( __FILE__ ) , $this->bgeo->id_base .'-nonce' );
	}

	public function verify_nonce()
	{
		return wp_verify_nonce( $_POST[ $this->bgeo->id_base .'-nonce' ] , plugin_basename( __FILE__ ));
	}

	public function get_field_name( $field_name )
	{
		return $this->bgeo->id_base . '[' . $field_name . ']';
	}

	public function get_field_id( $field_name )
	{
		return $this->bgeo->id_base . '-' . $field_name;
	}

	public function upgrade()
	{
		$options = get_option( $this->bgeo->id_base );

		// initial activation and default options
		if(
			! isset( $options['version']  ) ||
			$this->bgeo->version > $options['version']
		)
		{
			// create the table
			$this->create_table();

			// set the options
			$options['version'] = $this->bgeo->version;
		}

		// replace the old options with the new ones
		update_option( $this->bgeo->id_base , $options );
	}

	function create_table()
	{
		global $wpdb;

		if ( ! empty( $wpdb->charset ))
		{
			$charset_collate = 'DEFAULT CHARACTER SET '. $wpdb->charset;
		}
		if ( ! empty( $wpdb->collate ))
		{
			$charset_collate .= ' COLLATE '. $wpdb->collate;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		return dbDelta("
			CREATE TABLE " . $this->bgeo->table . " (
				`term_taxonomy_id` bigint(20) unsigned NOT NULL,
				`point` point NOT NULL DEFAULT '',
				`bounds` geometrycollection NOT NULL DEFAULT '',
				`area` int(10) unsigned NOT NULL,
				`api` char(5) DEFAULT NULL,
				`api_id` char(32) DEFAULT NULL,
				`api_raw` text,
				`belongtos` text,
				PRIMARY KEY (`term_taxonomy_id`),
				SPATIAL KEY `point` (`point`),
				SPATIAL KEY `bounds` (`bounds`),
				KEY `api_and_api_id` (`api`(1),`api_id`(3))
			) ENGINE=MyISAM $charset_collate
		");
	}

}//end bGeo_Admin class