<?php

/**
*
* Cameleon; the theme changing plugin for WordPress
* 
* @package    cameleon
* @author     Samuel Mello <sam@clarknikdelpowell.com
*/

class cameleon {

	/**
	* Global variables for class
	*
	* @param string $varname 	Key used in the $_GET string to pass info to WordPress
	* @param string $posttype 	The name of the post type to create in WordPress for our virtual sites
	* @param string $addonskey 	Name of the meta key in our post type that stores add-on alias directories
	* @param string $themekey 	Name of the meta key in our post type that stores which theme to use
	* @param array 	$theme 		Global theme variables set when hijacking our theme
	* @param array 	$rewrites 	Array of rewrites to implement for each virtual site
	*/
	private static $varname = '';
	private static $posttype = '';
	private static $addonskey = '';
	private static $themekey = '';
	private static $theme = array();
	private static $rewrites = array(
		 '/(.+?)/(.+?)/?$' 	=> '?post_type=$matches[1]&post_name=$matches[2]'
		,'/(.+?)/?$' 		=> '?post_type=$matches[1]'
		,'/?$'				=> ''
	);

	/**
	* 
	* Adds Wordpress actions and filters
	*
	* @param string $varname
	* @param string $posttype
	* @param string $addonskey
	* @param string $themekey
	*/
	public static function initialize($varname,$posttype,$addonskey,$themekey) {

		if (static::is_valid_string($varname)
			&& static::is_valid_string($posttype)
			&& static::is_valid_string($addonskey)
		) {

			static::$varname = $varname;
			static::$posttype = $posttype;
			static::$addonskey = $addonskey;

			$class = get_called_class();

			add_action('init', array($class, 'add_rewrites'));
			add_action('plugins_loaded', array($class, 'switch_theme'));
			add_action('wp_loaded', array($class, 'flush_rules'));

			add_filter('query_vars', array($class, 'add_vars'));
			add_filter('the_content', array($class, 'content_filter'));
		}
	}

	/**
	* 
	* Adds the query string variable
	*
	* @param array $vars  WordPress's query variables
	* @return array
	*/
	public static function add_vars($vars) {
		$vars[] = static::$varname;
		return $vars;
	}

	/**
	* 
	* Tests a variable to see if it's a valid array with content
	*
	* @param array $arr  the array to be compared
	* @return boolean
	*/
	private static function is_valid_array($arr) {
		if ($arr && is_array($arr) && count($arr)>0) return true;
		else return false;
	}

	/**
	* 
	* Tests a string to see if it's a valid string with actual length
	*
	* @param string $str  The string to be compared
	* @return boolean
	*/
	private static function is_valid_string($str) {
		if ($str && is_string($str) && strlen($str)>0) return true;
		else return false;
	}

	/**
	* 
	* Gets the list of sites and their associated urls
	*
	* @return array
	*/
	private static function get_sites() {

		$args = array(
			 'posts_per_page' => -1
			,'post_type'		=> static::$posttype
		);

		$sites = get_posts($args);
		if (static::is_valid_array($sites)) {

			$urls = array();
			foreach ($sites as $site) {

				$urls[$site->ID][] = $site->post_name;
				$addons = get_post_meta($site->ID, static::$addonskey, true);
				
				if (static::is_valid_string($addons) && unserialize($addons)) $addons = unserialize($addons);
				if (static::is_valid_array($addons)) {
					foreach ($addons as $addon) {
						$urls[$site->ID][] = $addon;
					}
				}
			}
		}

		if (static::is_valid_array($urls)) return $urls;
		else return false;
	}

	/**
	* 
	* Adds the rewrite rules into WordPress
	*/
	public static function add_rewrites() {
		$rootpage = 'index.php';
		$sites = static::get_sites();
		if ($sites) {
			foreach ($sites as $site=>$urls) {
				foreach ($urls as $url) {
					foreach (static::$rewrites as $rewrite_match=>$rewrite_to) {

						$var = static::$varname.'='.$site;
						if (strlen($rewrite_to)>0) $var = '&'.$var;
						else $var = '?'.$var;
						
						add_rewrite_rule(
							 $url.$rewrite_match
							,$rootpage.$rewrite_to.$var
							,'top'
						);
					}
				}
			}
		}
	}

	/**
	* 
	* Gets the theme for the current microsite
	* by getting the theme meta by post name or alias name
	*
	* @return string
	*/
	private static function get_theme() {

		$url = $_SERVER['REQUEST_URI'];

		if (strpos($url,'/')===0) $url = substr($url,1);
		if (strrpos($url,'/')===(strlen($url)-1)) $url = substr($url,0,(strlen($url)-1));
		if (strpos($url,'/')) $url = substr($url,0,(strpos($url,'/')));

		global $wpdb;
		$page_id = $wpdb->get_var("SELECT ID FROM ".$wpdb->posts." WHERE post_name = '".$url."' AND post_type = '".static::$posttype."' LIMIT 1");
		if (!$page_id) {
			$metas = $wpdb->get_results("SELECT post_id,meta_value FROM ".$wpdb->postmeta." WHERE meta_key = '".static::$addonskey."'", ARRAY_A);
			foreach ($metas as $meta) {
				$addons = unserialize($meta['meta_value']);
				if (static::is_valid_array($addons)) {
					foreach ($addons as $addon) {
						if ($addon==$url) $page_id = $meta['post_id'];
					}
				}
			}
		}
		if ($page_id) {
			$themename = get_post_meta($page_id, static::$themekey, true);
			if ($themename && static::is_valid_string($themename)) return $themename;
			else return false;
		}
		else return false;
	}

	/**
	* 
	* Builds the theme info for switching and adds the appropriate WordPress filters
	*/
	public static function switch_theme() {

	    if (isset($_GET['preview'])) return false;
	    
	    $theme_name = static::get_theme();
	    $current_theme = get_current_theme();

	    if (!$theme_name || $theme_name==$current_theme) return false;

	    $theme_data = wp_get_theme($theme_name);
	    if (!is_object($theme_data)) return;
	         
	    $template = $theme_data['Template'];
	    $stylesheet = $theme_data['Stylesheet'];

	    $template = preg_replace('|[^a-z0-9_./-]|i', '', $template);
	    if (validate_file($template)) return false; 
	 
	    $stylesheet = preg_replace('|[^a-z0-9_./-]|i', '', $stylesheet);
	    if (validate_file($stylesheet)) return false;
	 
	    static::$theme['name'] = $theme_data['Name'];
	    static::$theme['template'] = $template; 
	    static::$theme['stylesheet'] = $stylesheet;

		$class = get_called_class();

	    add_filter('template', array($class, 'theme_filter_template'));
	    add_filter('stylesheet', array($class, 'theme_filter_style'));

	    add_filter('sidebars_widgets', array($class, 'theme_filter_widgets'));
	    add_filter('theme_mod_{sidebars_widgets}', array($class, 'theme_filter_widgets'));

	}

	/**
	* 
	* Checks theme template global variable and returns the template name for WordPress's use
	*
	* @return string
	*/
	public static function theme_filter_template() {
		$theme = static::$theme;
		return (is_array($theme) && isset($theme['template'])) ? $theme['template'] : '';
	}

	/**
	* 
	* Checks theme template global variable and returns the stylesheet name for WordPress's use
	*
	* @return string
	*/
	public static function theme_filter_style() {
		$theme = static::$theme;
		return (is_array($theme) && isset($theme['stylesheet'])) ? $theme['stylesheet'] : '';
	}

	/**
	* 
	* Gets sidebar widgets for the currently viewed theme instead of from the database option
	* (see widgets.php in the Wordpress install for the function wp_get_sidebars_widgets()
	*
	* @param string $widgets The widgets array sent by WordPress
	* @return array
	*/
	public static function theme_filter_widgets($widgets) {
		$theme = static::$theme;
		$mods = get_option('theme_mods_'.$theme['stylesheet']);
		if ($mods !== false && isset($mods['sidebars_widgets']['data'])) return $mods['sidebars_widgets']['data'];
		else return $widgets;
	}

	/**
	* 
	* Flushes rewrite rules on save of microsite
	*/
	public static function flush_rules() {
		if (isset($_POST['post_type']) && $_POST['post_type']==static::$posttype) {
			flush_rewrite_rules();
		}
	}

	/**
	* 
	* Used to test output - currently not in use
	*
	* @param string $content  the content of the post
	* @return string
	*/
	public static function content_filter($content) {
		return $content;
	}
}
         
