<?php
/*
Plugin Name: Buzzsprout Podcasting
Plugin URI: http://www.buzzsprout.com/wordpress
Description: This plugin fetches content from a Buzzsprout feed URL, from which user can pick an episode and add it into the post
Version: 1.2.1
Author: Buzzsprout
Author URI: http://www.buzzsprout.com
*/

class Buzzsprout_Podcasting{
	
	const PLUGIN_NAME = 'Buzzsprout Podcasting';
	const PLUGIN_SLUG = 'buzzsprout-podcasting';
	const PLUGIN_TEXT_DOMAIN = 'buzzsprout-podcasting-domain';
	const PLUGIN_VERSION = 1.2;
	
	/**
	 * @desc Initializes the plugin
	 * 
	 */
	public static function initialize(){
		add_filter( 'media_upload_tabs', array( __CLASS__, 'register_media_tab' ) );
		add_action( 'media_upload_buzzsprout_podcasting', array( __CLASS__, 'add_media_tab' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_print_styles-media-upload-popup', array( __CLASS__, 'enqueue_media_tab_style' ) );
		add_action('admin_notices', array( __CLASS__, 'buzzsprout_admin_notice' ) );
		add_shortcode('buzzsprout', array( __CLASS__, 'buzzsprout_shortcode_handler' ) );
	}
	
	/**
	 * @desc Registers the Buzzsprout Podcasting media tab
	 * @param array $tabs All media tabs
	 * @return array All media tabs including the newly added Buzzsprout Podcasting
	 */
	public static function register_media_tab($tabs) {
	    $new_tab = array( 'buzzsprout_podcasting' => __( 'Buzzsprout Podcasting', self::PLUGIN_TEXT_DOMAIN ) );
	    return array_merge( $tabs, $new_tab );
	}
	
	/**
	 * @desc    Adds the media tab
	 * @return  wp_iframe()
	 */
	public static function add_media_tab(){
		return wp_iframe( array( __CLASS__, 'media_tab_content' ) );
	}
	
	/**
	 * @desc    Enqueue scripts to be used throughout the admin pages
	 * @return  void
	 */
	public static function enqueue_scripts(){
		wp_enqueue_style( 'buzzsprout-podcasting-admin', plugins_url( '/css/admin.css', __FILE__ ), false, self::PLUGIN_VERSION );
		wp_enqueue_script( 'buzzsprout-podcasting-admin', plugins_url('/js/admin-onload.js', __FILE__ ), array( 'jquery', 'media-upload' ) );
	}
	
	/**
	 * @desc    Enqueue styles and scripts to use for the media upload thickbox
	 * @return  void
	 */
	public static function enqueue_media_tab_style(){
		wp_enqueue_style( 'buzzsprout-podcasting-admin', plugins_url( '/css/admin.css', __FILE__ ), false, self::PLUGIN_VERSION );
		wp_enqueue_script( 'buzzsprout-podcasting-box', plugins_url('/js/box.js', __FILE__ ), array( 'jquery' ) );
	}
	
	/**
	 * @desc    Content for the Buzzsprout Podcasting media tab within the media uploader thickbox
	 * @return  mixed Media tab content
	 */
	public static function media_tab_content(){
		media_upload_header(); 
		$buzzsprout_options = get_option( self::PLUGIN_SLUG ); ?>
		<div class="box">
		<?php if ( !$buzzsprout_options ): ?>
			<p class="major-info error"><?php printf( __( 'You have not specified a valid Buzzsprout feed URL yet. Please use the form under %s %s to do so.', self::PLUGIN_TEXT_DOMAIN ), __( self::PLUGIN_NAME ),'<a href="' . admin_url( 'options-general.php?page=buzzsprout-podcasting' ) . '" target="_blank">' . __( 'Settings page', self::PLUGIN_TEXT_DOMAIN ).'</a>' ); ?></p>
		<?php elseif( !self::is_feed_valid( $buzzsprout_options['feed-uri'] ) ): error_log(self::is_feed_valid( $buzzsprout_options['feed-uri'] ) ); ?>
			<p class="major-info error"><?php printf(__('A valid Buzzsprout feed URL cannot be found. Please use the form under %s to update your settings.', self::PLUGIN_TEXT_DOMAIN), '<a href="'.admin_url('options-general.php?page=buzzsprout-podcasting').'">'.__('Settings', self::PLUGIN_TEXT_DOMAIN).'</a>'); ?></p>
		<?php else:
			$rss = fetch_feed( $buzzsprout_options['feed-uri'] );
			$maxitems = $rss->get_item_quantity($buzzsprout_options['number-episodes']); 
			$items = $rss->get_items( 0, $maxitems ); ?>
			<h2><?php _e( 'Pick an item', self::PLUGIN_TEXT_DOMAIN ); ?></h2>
			<ul>
			<?php if ($maxitems == 0): ?>
				<li class="error"><?php _e( 'No feed items can be retrieved.', self::PLUGIN_TEXT_DOMAIN ); ?></li>
			<?php else: ?>
			<?php foreach ( $items as $item ): // Loop through each feed item and display each item as a hyperlink. ?>
				<li>
				    <a class="buzzp-item" href="#" title="<?php echo esc_attr( __('Click to add this episode into the post', self::PLUGIN_TEXT_DOMAIN ) ); ?>" data-short-tag="<?php echo self::buzzsprout_item_create_short_tag($item->get_permalink(), $buzzsprout_options['include-flash']); ?>"><?php echo $item->get_title(); ?></a>
				</li>
			<?php endforeach; ?>
			<?php endif; ?>
			</ul>
		<?php endif; ?>
		</div><?php
	}
	
	/**
	 * @desc    Adds the Buzzsprout Podcasting Options menu item
	 * @return  void
	 */
	public static function add_options_page(){
		add_options_page(self::PLUGIN_NAME, self::PLUGIN_NAME, 'manage_options', self::PLUGIN_SLUG, array(__CLASS__, 'options_page_content'));
	}
	
	/**
	 * @desc    Registers the settings, settings section, and settings fields for the Buzzsprout Options page
	 * @return  void
	 */
	public static function register_settings(){
		register_setting( self::PLUGIN_SLUG, self::PLUGIN_SLUG, array( __CLASS__, 'buzzsprout_options_validate' ) );
		add_settings_section( 'buzzsprout_settings', __( 'Buzzsprout Settings', self::PLUGIN_TEXT_DOMAIN ), array( __CLASS__, 'buzzsprout_settings_section_cb'), self::PLUGIN_SLUG );
		add_settings_field( 'buzzsprout_feed_address', __( 'Buzzsprout feed address (URL)', self::PLUGIN_TEXT_DOMAIN ), array( __CLASS__, 'buzzsprout_feed_address_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
		add_settings_field( 'buzzsprout_include_flash', __( 'Include audio player?', self::PLUGIN_TEXT_DOMAIN ), array( __CLASS__, 'buzzsprout_include_flash_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
		add_settings_field( 'buzzsprout_number_episodes', __( 'Number of Episodes to return', self::PLUGIN_TEXT_DOMAIN ), array( __CLASS__, 'buzzsprout_number_episodes_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
	}

	/**
	 * @desc    Displays the Buzzsprout Podcasting Options page content
	 * @return  mixed Options page content
	 */
	public static function options_page_content(){ ?>
	<div class="wrap buzzp">
		<div id="icon-options-buzzp" class="icon32"></div>
		<h2><?php _e( self::PLUGIN_NAME, self::PLUGIN_TEXT_DOMAIN )?></h2>
		<p><?php _e("Buzzsprout is the only solution you need for publishing, hosting, promoting and tracking your podcast on the web. It eliminates the hassles and technical know-how required with self-managed podcast publishing. Whether you're just starting out or have been podcasting for years, Buzzsprout is the easiest way to get your podcast online.", self::PLUGIN_TEXT_DOMAIN); ?></p>
		<p><?php printf(__('You can learn more about Buzzsprout and create your own FREE account at %s.', self::PLUGIN_TEXT_DOMAIN ), '<a href="http://www.buzzsprout.com" target="_blank">http://www.buzzsprout.com</a>'); ?></p>    
		<form action="options.php" method="post">
			<?php settings_fields(self::PLUGIN_SLUG); ?>
			<?php do_settings_sections(self::PLUGIN_SLUG); ?>
			<p class="submit">
				<input class="button-primary" name="submit" type="submit" value="<?php echo( esc_attr( __( 'Save Changes', self::PLUGIN_TEXT_DOMAIN) ) ); ?>" />
			</p>
		</form>
    
		<h3><?php _e('How it works', self::PLUGIN_TEXT_DOMAIN)?></h3>

		<p class="how-it-works">
			<?php _e( 'The Buzzsprout Podcasting plugin drops a new option into your "Add Media" window. Click the add media icon and then select the "Buzzsprout Podcasting" tab to select the episode you would like to include within your post.', self::PLUGIN_TEXT_DOMAIN ); ?>
			<img src="<?php echo esc_url( plugins_url( '/images/help-toolbar1.png', __FILE__ ) ); ?>" alt="<?php echo esc_attr( __( 'Toolbar', self::PLUGIN_TEXT_DOMAIN ) ); ?>" />
			<img src="<?php echo esc_url( plugins_url( '/images/help-toolbar2.png', __FILE__ ) ); ?>" alt="<?php echo esc_attr( __( 'Toolbar', self::PLUGIN_TEXT_DOMAIN ) ); ?>" />
		</p>
		<p class="how-it-works">
			<?php _e( 'Once you select the episode you would like to include, a shortcode will be added to your post. You can feel free to move this around, to wherever you would like the episode to appear within your post.', self::PLUGIN_TEXT_DOMAIN ); ?>
			<img src="<?php echo esc_url( plugins_url( '/images/help-shortcode.png', __FILE__ ) ); ?>" alt="<?php echo esc_attr( __( 'Shortcode', self::PLUGIN_TEXT_DOMAIN ) ); ?>"
		</p>
	</div>
	<?php
	}
	
	/**
	 * @desc    Validates the buzzsprout options settings
	 * @param   $input Array of the buzzsprout_podcasting_options
	 * @return  array Clean array of the buzzsprout podcasting options
	 */
	public static function buzzsprout_options_validate($input){
		$new_input = array();
		$new_input['feed-uri'] = esc_url_raw(strip_tags( $input['feed-uri'] ) );
		$new_input['include-flash'] = ($input['include-flash'] == 'on') ? true : false;
		$new_input['number-episodes'] = strip_tags( $input['number-episodes'] );
		return $new_input;
	}
	
	/**
	 * @desc    Adds a description to the Buzzsprout Settings section on the Buzzsprout Podcasting Options page
	 * @return  void
	 */
	public static function buzzsprout_settings_section_cb(){
		return '';
	}
	
	/**
	 * @desc    Displays the feed address input for the Buzzsprout Podcasting Options page
	 * @return  void
	 */
	public static function buzzsprout_feed_address_cb(){ 
		$buzzsprout_options = get_option(self::PLUGIN_SLUG);
		?>
			<input style="width: 300px" type="text" name="<?php echo esc_attr( self::PLUGIN_SLUG.'[feed-uri]'); ?>" value="<?php echo esc_attr($buzzsprout_options['feed-uri']); ?>" />
			<span class="guide">
				<?php printf( __('%s then click on "Promotion" section.', self::PLUGIN_TEXT_DOMAIN ), '<a href="http://www.buzzsprout.com/login" target="_blank">'.__('Login to your account.', self::PLUGIN_TEXT_DOMAIN).'</a>'); ?>
			</span><?php
	}
	
	/**
	 * @desc    Displays the include flash input for the Buzzsprout Podcasting Options page
	 * @return  void
	 */
	public static function buzzsprout_include_flash_cb(){
		$buzzsprout_options = get_option(self::PLUGIN_SLUG); ?>
		<input type="checkbox" name="<?php echo esc_attr( self::PLUGIN_SLUG.'[include-flash]'); ?>" <?php checked($buzzsprout_options['include-flash']); ?> /> Yes
		<?php
	}

	/**
	 * @desc    Displays the number episodes select box for the Buzzsprout Podcasting Options page
	 * @return  void
	 */
	public static function buzzsprout_number_episodes_cb(){
		$buzzsprout_options = get_option(self::PLUGIN_SLUG); ?>
		<p>
			<select name="<?php echo esc_attr( self::PLUGIN_SLUG.'[number-episodes]'); ?>">
			<?php	for ($i = 5; $i < 21; $i += 5):
					printf('<option value="%s"%s>%s</option>%s', $i, selected($buzzsprout_options['number-episodes'], $i), $i, PHP_EOL);
				endfor;
				printf('<option value="%s"%s>%s</option>%s', 9999, selected($buzzsprout_options['number-episodes'], 9999), __('All', self::PLUGIN_TEXT_DOMAIN), PHP_EOL); ?>
			</select>
			<br class="clear" />
		</p><?php
	}
	
	/**
	 * @desc
	 * @param string $url
	 * @return bool Is valid or not
	 */
	public static function is_feed_valid($url){
		if (!trim($url)) return false;
		return preg_match('|^http(s)?://(www\.)?buzzsprout\.com/[0-9]+\.rss$|i', $url);
	}
	
	/**
	 * @desc Gets the subcription ID (from the RSS URL)
	 * 
	 * @param mixed $feed_uri
	 * @return mixed
	 */
	public static function get_subscription_id($feed_uri = false){

	    // if a feed URI is not provided, try getting it from DB
	    if (!$feed_uri){
		$buzzsprout_otions = get_option(self::PLUGIN_SLUG);
		
		if(!$buzzsprout_otions)
			return false;
			
		$feed_uri = $buzzsprout_otions['feed-uri'];
	    }

	    if (!preg_match_all('|^https?://(www\.)?buzzsprout\.com/([0-9]+)\.rss$|i', $feed_uri, $matches)) return false;
	    return isset($matches[2][0]) ? $matches[2][0] : false;
	}
	
	/**
	 * @desc Handles the [buzzsprout] shortcode
	 * 
	 * @param mixed $atts
	 * @return The parsed HTML
	 */
	public static function buzzsprout_shortcode_handler($atts){
	    extract(shortcode_atts(array(
		'episode'   => 0,
		'player'    => 'true',
	    ), $atts));

	    // as player=true is preferred, we only disable player if the value is exclusively 'false'
	    $parsed_html = sprintf(
		'<script src="http://www.buzzsprout.com/%s/%s.js?%s" type="text/javascript" charset="utf-8"></script>',
		self::get_subscription_id(), $episode, $player != 'false' ? 'player=small' : ''
	    );

	    return $parsed_html;
	}
	
	/**
	 * Create a short tag to add into the post
	 * 
	 * @param string Link of the buzz media file
	 * @param mixed Whether player should be enabled
	 */
	function buzzsprout_item_create_short_tag($buzz_item_link, $player){
	    // http://www.buzzsprout.com/96/1917-ep-9-rams-vs-titans.mp3
	    if (!preg_match_all('|^https?://(www\.)?buzzsprout\.com/[0-9]+/([0-9]+).*|i', $buzz_item_link, $matches)) return false;

	    if (!isset($matches[2][0])) return false;

	    $tag = sprintf("[buzzsprout episode='%s' player='%s']", trim($matches[2][0]), $player ? 'true' : 'false');
	    return $tag;
	}
	
	
	/**
	 * @desc Check if the feed provided is valid and if so, the Settings updated message will be displayed, if not, the Invalid Buzzsprout Feed URL error message will be displayed. 
	 *
	 */
	function buzzsprout_admin_notice(){
		global $pagenow;
		if ($pagenow == 'options-general.php' && $_GET['page'] == self::PLUGIN_SLUG) {
			if ( (isset($_GET['updated']) && $_GET['updated'] == 'true') || (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') ) {

				$errors = get_settings_errors();
				
				$buzzsprout_options = get_option( self::PLUGIN_SLUG );
				if(!self::is_feed_valid($buzzsprout_options['feed-uri'])){
					$buzzsprout_options['feed-uri'] = '';
					update_option(self::PLUGIN_SLUG, $buzzsprout_options);
					$error_message = __('Invalid Buzzsprout Feed URL');
					add_settings_error('general', 'settings_updated', $error_message, 'error');
				} else {
					$original_message = $errors[0]['message'];
					add_settings_error('general', 'settings_updated', $original_message, 'updated');
				}
			}
		}
	}
}
add_action('init', array('Buzzsprout_Podcasting', 'initialize'));