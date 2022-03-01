<?php
   /*
   Plugin Name: randomHomeOrPost
   Plugin URI: https://github.com/vinwang/randomhomeorpost
   Description: Easily create a custom URL to redirect visitors to view random posts or home page. Keyboard hotkey shortcuts also available for browsing random posts.
   Version: 1.1.0
   Author: Vin
   Author URI: https://github.com/vinwang
   License: GPL2
   Text Domain: randomHomeOrPost
   Domain Path: /languages
   */
   
DEFINE("RANDOMHOMEORPOST_VER", "1.1.0");

class randomHomeOrPost {
	
	public function __construct() {
		register_activation_hook(__FILE__, array( &$this, "install") );

		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
		
		add_action( 'wp_footer', array( &$this, 'hotkey_navigation' ) );

		add_action( 'init', array( &$this, 'add_random_rewrite_rules' ) );
		add_action( 'parse_query', array( &$this, 'check_url_query' ) );

        if( is_admin() ) {		
			add_action( 'admin_enqueue_scripts', array( &$this, "admin_scripts" ) );
			add_action( 'admin_menu' , array( &$this, 'create_options_page' ) );
			add_action( 'admin_init' , array( &$this, 'setup_settings' ) );
            add_action( 'admin_init', array( &$this, 'upgrade_options' ) );
			add_filter( 'plugin_action_links', array( &$this, 'rp_plugin_action_links'), 10, 2 );
		}

	}
	
	public function install() {
        update_option( "rp_version", RANDOMHOMEORPOST_VER, true );
		update_option( "rp_slug_option", "random", true );
		update_option( "rp_slug_option_previous", "random", true );
		update_option( "rp_hotkey_enabled", "true", true );
		update_option( "rp_javascript_hotkey", "82", true );
		update_option( "rp_homepage_rate", "30", true );
		$this->add_random_rewrite_rules(true); // Add new rewrite rules and flush rewrite rules
	}
	
	public function load_textdomain() {
		load_plugin_textdomain( 'randomhomeorpost', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' ); 
	}
    
    public function upgrade_options() {
        $rp_version = get_option( "rp_version" );
        
        // Set the plugin version
        if ( ( !$rp_version ) || ( $rp_version == "" ) ) {
            // New in 1.1.1, track plugin version
            update_option( "rp_version", RANDOMHOMEORPOST_VER, true );
        }
    }    
	
	public function admin_scripts() {
		wp_register_style( 'rp_adminCSS', plugins_url( 'css/admin.css', __FILE__ ) );
		wp_enqueue_style( 'rp_adminCSS' );
	}
	
	public function hotkey_navigation() {
		$hotkey_enabled = get_option('rp_hotkey_enabled');
		if ($hotkey_enabled) {
			$hotkey = get_option( 'rp_javascript_hotkey' );
			$random_slug = get_option( 'rp_slug_option' );
			$html = '
				<!-- randomHomeOrPost Plugin Begin -->
				<script type="text/javascript">
					document.onkeydown = change_page;
					function change_page(e) {
						var e = e || event,
						keycode = e.which || e.keyCode;
						var obj = e.target || e.srcElement;
						if( obj.tagName.toLowerCase() == "textarea" ) { return; } // Do nothing in a text area
						if( obj.tagName.toLowerCase() == "input" ) { return; } // Do nothing in an input field
						if ( keycode == ' . $hotkey . ' ) location = "' . get_site_url() . '/' . $random_slug . '/";
					}
				</script>
				<!-- randomHomeOrPost Plugin End -->
			';
			
			echo $html;
		}
	}
	
	public function add_random_rewrite_rules($installed = false) {
		global $wp;
		$random_slug = get_option( 'rp_slug_option' );
		$random_slug_previous = get_option( 'rp_slug_option_previous' );
		$wp->add_query_var('random');
		add_rewrite_rule( $random_slug . '/?$', 'index.php?random=1', 'top' );
		
		if ( ( $random_slug != $random_slug_previous ) || $installed ) {
			// New Slug or new install
			flush_rewrite_rules(); // Only refresh rewrite when new slug is detected
			update_option( "rp_slug_option_previous", $random_slug, true );
			
			// Do not show the updated message on install
			if ( ! $installed ) {
				?>
				<div class="updated fade"><p><strong><?php _e( 'randomHomeOrPost URL slug updated!', 'randomhomeorpost' ); ?></strong></p></div>
				<?php
			}
		}
	}
	
	public function check_url_query() {
		if ( get_query_var( 'random' ) ) {
			add_action( 'template_redirect', array( &$this, 'random_post_redirect' ) );
		}
	}
	
	public function random_post_redirect() {		
		$posts = get_posts( 'post_type=post&orderby=rand&numberposts=1' );
		$homepage = home_url();
        $homeRate = get_option('rp_homepage_rate');
        $postRate = 100 - $homeRate;
        $dataArr = [
            ['url'=>$homepage, 'v'=>$homeRate],
            ['url'=>get_permalink($posts[0]), 'v'=>$postRate],
        ];
        $randArr = [];
		foreach($dataArr as $key=>$val){
            $randArr[$key] = $val['v'];
        }
        $rskey = $this->get_rand($randArr);
        $url = $dataArr[$rskey]['url'];

		wp_redirect( $url, 307 );
		exit;
	}

    public function get_rand($proArr) {
        $result = '';
        $proSum = array_sum($proArr);
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);

        return $result;
    }
	
	//=====================//
	// Admin Page Settings //
	//=====================//
	
	public function create_options_page() {
		add_options_page(
			'randomPost Settings',					// Page Title
			'randomPost Settings',					// Menu Title
			'manage_options',						// Capability
			'rp-settings',							// Menu Slug
			array( &$this, 'rp_settingsPage' )		// Callback Function
		);
	}
	
	public function setup_settings() {
		
		add_settings_section( 
			'rp_general_settings_section',							// Section name
			__('General Settings:','randomhomeorpost'),					// Title
			array( &$this , 'rp_settings_section_callback' ),		// Callback
			'rp_general_settings'									// Which page should this section show on?
		);
	
		add_settings_field(
			'rp_slug_option',							// ID of the field
			__('URL Slug:','randomhomeorpost'),				// Title
			array( &$this , 'url_slug_option' ),		// Callback function
			'rp_general_settings',						// Which page should this option show on
			'rp_general_settings_section'				// Section name to attach to
		);
		
		add_settings_field(
			'rp_hotkey_enabled',								// ID of the field
			__('Hotkey Enabled:','randomhomeorpost'),					// Title
			array( &$this , 'hotkey_enabled_settings' ),		// Callback function
			'rp_general_settings',								// Which page should this option show on
			'rp_general_settings_section'						// Section name to attach to
		);
		
		add_settings_field(
			'rp_javascript_hotkey',								// ID of the field
			__('Hotkey ID:','randomhomeorpost'),						// Title
			array( &$this , 'javascript_hotkey_option' ),		// Callback function
			'rp_general_settings',								// Which page should this option show on
			'rp_general_settings_section'						// Section name to attach to
		);
		add_settings_field(
			'rp_home_rate',								// ID of the field
			__('Homepage Rate:','randomhomeorpost'),						// Title
			array( &$this , 'homepage_rate_option' ),		// Callback function
			'rp_general_settings',								// Which page should this option show on
			'rp_general_settings_section'						// Section name to attach to
		);
		
		register_setting( 'rp_general_settings', 'rp_slug_option' );
		register_setting( 'rp_general_settings', 'rp_hotkey_enabled' );
		register_setting( 'rp_general_settings', 'rp_javascript_hotkey' );
		register_setting( 'rp_general_settings', 'rp_homepage_rate' );
	}

	public function rp_settingsPage() {
	
		?>
		<div class="wrap">

			<h2><span class="dashicons dashicons-randomize rp-icon"></span><?php echo esc_html( get_admin_page_title() ); ?> - v<?php echo RANDOMPOST_VER; ?></h2>
			
			<div class="rp-settings-wrapper">
				<div class="rp-settings-body">

					<form method="post" action="options.php">
						<?php

						settings_fields( 'rp_general_settings' );
						do_settings_sections( 'rp_general_settings' );
						
						submit_button();
						
						?>
					</form>
				</div> <!-- end rp-settings-body -->
				<div class="rp-donate-wrapper">
					<div class="rp-donate-body">
						Thank you for using randomHomeOrPost! 
                        <h2>Need support?</h2>
                        Visit the <a href="https://github.com/vinwang/randomhomeorpost" target="_blank">plugin homepage!</a> Leave a comment with your question and I'll try to help you out!
					</div>
				</div> <!-- end rp-donate -->
			</div> <!-- end rp-wrapper -->
		
		</div>
			  
		<?php
	}
	
	public function rp_settings_section_callback() { 
		?>
		<p>
		<?php _e( 'Change randomHomeOrPost settings.', 'randomhomeorpost'); ?>
		</p>
		<?php
	}
		
	public function url_slug_option() {
		$option = get_option( 'rp_slug_option' );
		$exampleURL = get_site_url() . '/random/';
		
		echo '
			<input type="text" id="rp_slug_option" name="rp_slug_option" autocomplete="off" value="'. $option .'">
			<label for="rp_slug_option"><span class="description">' . __( 'Default: random','randomhomeorpost' ) . '</span>
			<br>
			<span class="description">' . __( 'Changes the URL slug for your random post URL. Be careful when changing this as it might cause problems with your WordPress website.' ) . '</span>
			<br>
			<span class="description">' . __( 'We recommend only using the word <b>random</b>. For example:','randomhomeorpost') . ' <a href="'.$exampleURL.'" target="_blank">'.$exampleURL.'</a></span>
			<br>
			<span class="description">' . __( 'Tip: add this URL to your WordPress menu to invite your visitors to read a random post right from the menu!' ) . '</span>
			</label>
		';
	}
	
	public function hotkey_enabled_settings() {
		$option = get_option('rp_hotkey_enabled');
				
		echo '
			<input type="checkbox" id="rp_hotkey_enabled" name="rp_hotkey_enabled" value="true" '. checked( $option, "true", false ) .' >
			<label for="rp_hotkey_enabled"><span class="description">' . __( 'Default: checked (enabled)<br>Enable or disable the keyboard hotkey. Enabling adds some JavaScript to your site that allows your visitors to press a key on their keyboard to view a random post.', 'randomhomeorpost') . '</span></label>
		';
	}
	
	public function javascript_hotkey_option() {
		$option = get_option( 'rp_javascript_hotkey' );
		
		echo '
			<input type="text" id="rp_javascript_hotkey" name="rp_javascript_hotkey" autocomplete="off" value="'. $option .'">
			<label for="rp_javascript_hotkey"><span class="description">' . __( 'Default: 82 (R key)<br>Change the JavaScript hotkey. When a visitor presses the R key, it will show them random post. The number in this box has to represent the JavaScript character code for the keyboard key mapping. For example: if you want to use P as the hotkey, you would enter 80.','randomhomeorpost') . '</span></label>
			<br><br>
			<label for="rp_javascript_hotkey_help"><span class="description"> '. __( 'To get the ID of the key you want to use, type it into this text box:','randomhomeorpost') . '</span></label>
			<input type="text" id="rp_javascript_hotkey_help" autocomplete="off" onKeyDown="javascript:return displayKeyCode(event)" onKeyPress="javascript:return false">
			<script type="text/javascript">
				function displayKeyCode(e) {
					rp_javascript_hotkey_help.value = e.keyCode;
				}
			</script>
		';
	}
	
	public function homepage_rate_option(){
		$option = get_option('rp_homepage_rate');
				
		echo '
			<input type="text" id="rp_homepage_rate" name="rp_homepage_rate" value="30">
			<label for="rp_homepage_rate"><span class="description">' . __( 'The probability of jumping to the home page, the default is 30%, and the probability of jumping to the content page is 70%.', 'randompost') . '</span></label>
		';
	}
	
	// Add settings link to the plugin page. 
	function rp_plugin_action_links( $links, $file ) {
		static $this_plugin;

		if ( !$this_plugin ) {
			$this_plugin = plugin_basename( __FILE__ );
		}
		
		if ( $file == $this_plugin ) {
			// The "page" query string value must be equal to the slug
			// of the Settings admin page we defined earlier, which in
			// this case equals "myplugin-settings".
			$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page=rp-settings">Settings</a>';
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

}

$rp = new randomHomeOrPost();
   
?>
