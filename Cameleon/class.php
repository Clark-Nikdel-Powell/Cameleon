<?php

/**
*
* Cameleon; the theme changing plugin for WordPress
* 
* @package    Cameleon
* @author     Samuel Mello <sam@clarknikdelpowell.com
* @version 	  1.1
*
*
* Upcoming features:
*  - URL rewrite checking & notification (for notifying of existing rewrites)
*  - Override theme switch when registered post type url rewrite is used (fixes conflicts)
* 
*/

class Cameleon {

	/**
	* Global variables for class
	*
	* @param array 	$settings 	Array of settings to apply across the class
	* @param array 	$theme 		Global theme variables set when hijacking our theme (do not set)
	* @param array 	$rewrites 	Array of rewrites to implement for each virtual site (add as needed)
	*/
	private static $settings = array(
		 'query_arg' => 'cameleon'
		,'post_type' => 'skin'
		,'alias_key' => 'skin_alias'
		,'theme_key' => 'skin_theme'
		,'menu_icon' => 'dashicons-art'
		,'nonce'	 => 'cameleon-nonce'
		,'alias_warning' => 'Warning: using existing rewrite rules is prohibited.'
	);

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
	* @param array $settings An array of settings to use
	*/
	public static function initialize($settings=array()) {

		static::$settings = array_replace(static::$settings,$settings);

		$class = get_called_class();

		add_action('init', array($class, 'add_rewrites'));
		add_action('init', array($class, 'register'));
		add_action('add_meta_boxes', array($class, 'add_meta_box'));
		add_action('save_post', array($class, 'save_meta'));
		add_action('plugins_loaded', array($class, 'switch_theme'));
		add_action('wp_loaded', array($class, 'flush_rules'));
		add_action('admin_enqueue_scripts', array($class, 'set_scripts'));
		add_action('wp_ajax_check_alias', array($class, 'check_alias'));

		add_filter('query_vars', array($class, 'add_vars'));
		add_filter('post_type_link', array($class,'post_link'), 1, 3);
	}

	/**
	* 
	* Adds the query string variable
	*
	* @param array $vars  WordPress's query variables
	* @return array
	*/
	public static function add_vars($vars) {
		$vars[] = static::$settings['query_arg'];
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
	* Registers WordPress post type for skins
	*/
	public static function register() {

		$settings = static::$settings;
		$type = ucwords($settings['post_type']);
		$labels = array(
			'name' => $type
			,'singular_name' => $type
			,'plural_name' => $type.'s'
			,'add_new_item' => 'Add New '.$type
			,'edit_item' => 'Edit '.$type
			,'new_item' => 'New '.$type
			,'view_item' => 'View '.$type
			,'search_items' => 'Search '.$type.'s'
			,'not_found' => 'No '.$type.'s found.'
			,'not_found_in_trash' => 'No '.$type.'s found in Trash.'
			,'all_items' => $type.'s'
			,'menu_name' => $type.'s'
			,'name_admin_bar' => $type.'s'
		);
		$args = array(
			 'labels' => $labels
			,'public' => true
			,'publicly_queryable' => false
			,'has_archive' => false
			,'show_in_nav_menus' => false
			,'menu_icon' => $settings['menu_icon']
			,'hierarchical' => false
			,'supports' => array('title')
			,'menu_position' => 10
			,'show_in_menu' => true
		);
		register_post_type($settings['post_type'], $args);
	}

	/**
	* 
	* Sets post links to root instead of under url/post_type/name
	*/
	public static function post_link($post_link,$id=0) {
		$post = get_post($id);
		$posttype = static::$settings['post_type'];
		if (is_object($post) && $post->post_type==$posttype) {
			return str_replace($posttype.'/','',$post_link);
		}
		return $post_link;
	}

	/**
	* 
	* Adds the meta boxes to store meta info for skins
	*/
	public static function add_meta_box() {
		$post_type = static::$settings['post_type'];
		add_meta_box(
			$post_type.'_settings',
			'Settings',
			array(get_called_class(), 'display_meta'),
			$post_type,
			'normal',
			'default'
		);		
	}

	/**
	* 
	* Saves the meta from the display_meta function
	*/
	public static function save_meta() {

		$current = get_current_screen();
		if ($current->base==='post' && $current->post_type===static::$settings['post_type']) {

			$alias_key = static::$settings['alias_key'];
			$theme_key = static::$settings['theme_key'];
			$nonce = static::$settings['nonce'];
			$post_type = static::$settings['post_type'];
			$post_id = $_POST['post_ID'];

			if (!isset($_POST[$nonce])) return;
			if (!wp_verify_nonce($_POST[$nonce], $nonce)) return;
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
			if (!current_user_can('edit_post',$post_id)) return;
			if (!isset($_POST[$theme_key]) || !isset($_POST[$alias_key])) return;
 			
 			$aliases = '';
 			if (is_array($_POST[$alias_key])) {
 				for ($i=0; $i<count($_POST[$alias_key]); $i++) {
 					if (!static::is_valid_string($_POST[$alias_key][$i])) unset($_POST[$alias_key][$i]);
 					else $_POST[$alias_key][$i] = sanitize_title($_POST[$alias_key][$i]);
 				}
 				$aliases = json_encode($_POST[$alias_key]);
 			}

			update_post_meta($post_id, $alias_key, sanitize_text_field($aliases));
			update_post_meta($post_id, $theme_key, sanitize_text_field($_POST[$theme_key]));
		}
	}

	/**
	* 
	* Validates the Alias to avoid conflicts
	*/
	public static function check_alias() {
		global $wp_rewrite;

		$ret['status'] = 202;
		$ret['message'] = static::$settings['alias_warning'];
		$ret['errors'] = array();

		$aliases = $_POST['aliases'];
		if (is_object($wp_rewrite) && isset($aliases) && count($aliases)>0) {

			$current_aliases_meta = get_post_meta($_POST['post'],static::$settings['alias_key'],true);
			$current_aliases = json_decode($current_aliases_meta);

			$rules = $wp_rewrite->rules;
			$rules_e = $wp_rewrite->extra_rules;
			$rules_e_top = $wp_rewrite->extra_rules_top;
			$end = '/?$';

			foreach ($aliases as $name=>$alias) {
				if ((isset($rules[$alias.$end]) || isset($rules_e[$alias.$end]) || isset($rules_e_top[$alias.$end])) && !in_array($alias,$current_aliases)) $ret['errors'][] = $name;
				elseif (count(array_keys($aliases,$alias))>1) $ret['errors'][] = $name;
			}
			if (count($ret['errors'])>0) {
				$ret['status'] = 500;
				$ret['message'] = 'This alias already exists as a rewrite rule, alias, or is listed twice above. Publish has been disabled.';
			}
		}
		echo json_encode($ret);
		die();
	}

	/**
	* 
	* Displays the meta HTML when editing a skin.
	*
	* @param object $post The $post variable passed by WordPress
	*/
	public static function display_meta($post) {

		$alias_key = static::$settings['alias_key'];
		$theme_key = static::$settings['theme_key'];
		$nonce = static::$settings['nonce'];

		wp_nonce_field($nonce,$nonce);
		?>
		<table class="form-table cameleon-fields">
		<tr>
			<th class="header"><label>Theme:</label></th>
			<td>
			<select name="<?php echo $theme_key ?>" id="<?php echo $theme_key ?>">
			<option value="">-- Currently Active Theme --</option>
			<?php

			$themes_unfiltered = get_themes();
			$themes = array();

			if (count($themes_unfiltered)>0) {
				foreach ($themes_unfiltered as $themename=>$themedata) {
					$selected = '';
					if ($themedata->template==get_post_meta($post->ID,$theme_key,true)) $selected=' selected="selected"';
					?><option value="<?php echo $themedata->template ?>"<?php echo $selected ?>><?php echo $themedata->name ?></option><?php
				}
			}
			?>
			</select>
			</td>
		</tr>
		<tr>
			<th class="header"><label>Aliases:</label></th>
			<td>
				<div class="dashicons dashicons-plus-alt cmln-add-alias" title="Add New Alias"></div>
				<div class="cmln-generated-aliases"><?php echo static::load_aliases($post->ID,$alias_key); ?></div>
				<div class="cmln-alias-template"><?php echo static::load_alias_template($alias_key,'',false); ?></div>
				<br/>
				<p class="description cmln-desc"><?php echo static::$settings['alias_warning'] ?></p>
			</td>
		</tr>
		</table>
		<?php
	}

	/**
	* 
	* Loads the alias template for the admin settings page
	*
	* @param string $alias The alias to populate
	* @param string $key The meta key being used
	* @return string
	*/
	public static function load_alias_template($key,$alias='',$idnum=1) {

		if ($idnum!==false) $id = $key.'_'.$idnum;
		else $id = $key;

		ob_start();
		?>
		<div class="cmln-alias-field-wrap">
			<input type="text" name="<?php echo $key ?>[]" id="<?php echo $id ?>" class="cmln-alias-field" value="<?php echo $alias ?>" />
			<div class="dashicons dashicons-dismiss cmln-remove-alias" title="Remove This Alias"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	* 
	* Loads all the current aliases from meta
	*
	* @param object $pid The post ID to load the meta for
	* @param string $key The meta key being used
	* @return string
	*/
	private static function load_aliases($pid,$key) {
		$ret = '';
		$aliases = get_post_meta($pid,$key,true);
		if ($aliases && $alias_json = json_decode($aliases)) {
			$id = 0;
			foreach ($alias_json as $alias) {
				$ret .= static::load_alias_template($key,$alias,$id);
				$id++;
			}
		}
		else $ret .= static::load_alias_template($key);
		return $ret;
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
			,'post_type'		=> static::$settings['post_type']
		);

		$sites = get_posts($args);
		if (static::is_valid_array($sites)) {

			$urls = array();
			foreach ($sites as $site) {

				$urls[$site->ID][] = $site->post_name;
				$addons = get_post_meta($site->ID, static::$settings['alias_key'], true);
				
				if (static::is_valid_string($addons) && @json_decode($addons)) @$addons = json_decode($addons);
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

						$var = static::$settings['query_arg'].'='.$site;
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
		$page_id = $wpdb->get_var("SELECT ID FROM ".$wpdb->posts." WHERE post_name = '".$url."' AND post_type = '".static::$settings['post_type']."' LIMIT 1");
		if (!$page_id) {
			$metas = $wpdb->get_results("SELECT post_id,meta_value FROM ".$wpdb->postmeta." WHERE meta_key = '".static::$settings['alias_key']."'", ARRAY_A);
			foreach ($metas as $meta) {
				@$addons = json_decode($meta['meta_value']);
				if (static::is_valid_array($addons)) {
					foreach ($addons as $addon) {
						if ($addon==$url) $page_id = $meta['post_id'];
					}
				}
			}
		}
		if ($page_id) {
			$themename = get_post_meta($page_id, static::$settings['theme_key'], true);
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
		if (isset($_POST['post_type']) && $_POST['post_type']==static::$settings['post_type']) {
			flush_rewrite_rules();
		}
	}

	/**
	* 
	* Enqueues styles and scripts
	*/
	public static function set_scripts() {

		$current = get_current_screen();
		if ($current->base==='post' && $current->post_type===static::$settings['post_type']) {

			wp_enqueue_script(static::$settings['post_type'].'-edit-js', CMLN_URL.'js/edit.js','jquery', null, true );
			wp_enqueue_style(static::$settings['post_type'].'-edit-css', CMLN_URL.'css/edit.css');

			$data['url'] = admin_url('admin-ajax.php');
			$data['action'] = 'check_alias';

			wp_localize_script(static::$settings['post_type'].'-edit-js','cmln',$data);
		}
	}
}
		 
