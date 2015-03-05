<?php
/**
*
* Cameleon; the theme changing plugin for WordPress
*
* @package    Cameleon
* @author     Samuel Mello <sam@clarknikdelpowell.com>
* @version 	  1.6
*/
final class Cameleon {

	/**
	* 	Global variables for class
	*
	* 	@var 	array 	$theme 		Global theme variables set when hijacking our theme (do not set)
	* 	@var 	array 	$sites 		Cache array of sites for looping
	* 	@var 	array 	$settings 	Array of settings to apply across the class
	*/
	public $theme = array();
	public $sites = array();
	public $settings = array(
		'query_arg' 	=> 'cameleon'
	,	'post_type' 	=> array(
			'name' 		=> 'skin'
		,	'plural' 	=> 'skins'
		,	'icon'		=> 'dashicons-art'
		)
	,	'alias_key' 	=> 'skin_alias'
	,	'theme_key' 	=> 'skin_theme'
	,	'nonce'	 		=> 'cameleon-nonce'
	,	'alias_warning' => 'Warning: using existing rewrite rules is prohibited.'
	);


	/**
	*
	* 	Occurrs upon plugin load (instantiation)
	*
	* 	@access 	public
	* 	@param 		array 		$settings 	An array of settings to use
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function __construct($settings = array()) {

		// Merge settings array from construct call
		$this->settings = array_merge($this->settings, $settings);

		// perform hooksa
		$this->hook();

		return;
	}


	/**
	*
	* 	Hooks all public functions in this plugin to wordpress
	*
	* 	@access 	private
	* 	@return 	null
	* 	@since 		1.6 		Separated this from construct()
	*/
	private function hook() {

		// Post Type From Settings
		$ptype = $this->settings['post_type']['name'];

		// Wordpress Actions
		add_action('init', 						array($this, 'register'));
		add_action('add_meta_boxes', 			array($this, 'add_meta_box'));
		add_action('save_post', 				array($this, 'save_meta'));
		add_action('plugins_loaded', 			array($this, 'switch_theme'));
		add_action('save_post_'.$ptype, 		array($this, 'enable_flush'));
		add_action('admin_enqueue_scripts', 	array($this, 'set_scripts'));
		add_action('wp_ajax_check_alias', 		array($this, 'check_alias'));

		// Wordpress Filters
		add_filter('rewrite_rules_array', 		array($this, 'add_rewrites'));
		add_filter('query_vars', 				array($this, 'add_vars'));
		add_filter('post_type_link', 			array($this, 'post_link'),1,2);
		add_filter('post_type_link', 			array($this, 'filter_url'),1,2);
		add_filter('post_type_archive_link', 	array($this, 'filter_url'),1,2);
		add_filter('post_link', 				array($this, 'filter_url'),1,2);
		add_filter('term_link', 				array($this, 'filter_url'),1,2);
		add_filter('page_link', 				array($this, 'filter_url'),1,2);
		add_filter('wp_nav_menu_objects', 		array($this, 'filter_menus'),1,1);

		return;
	}


	/**
	*
	* 	Tests a variable to see if it's a valid array with content
	*
	* 	@access 	private
	* 	@param 		array 		$arr  	The array to be compared
	* 	@return 	boolean 			The success or failure of the operation
	*/
	private function is_valid_array($arr) {
		if ( $arr && is_array($arr) && count($arr)>0 ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	
	/**
	*
	* 	Tests a string to see if it's a valid string with actual length
	*
	* 	@access 	private
	* 	@param 		string 		$str  	The string to be compared
	* 	@return 	boolean 			The success or failure of this function
	* 	@since 		1.0
	*/
	private function is_valid_string($str) {
		if ($str && is_string($str) && strlen($str)>0) {
			return true;
		}
		else {
			return FALSE;
		}
	}


	/**
	*
	* 	FILTER: Adds the query string variable for use in the theme
	*
	* 	@access 	public
	* 	@param 		array 		$vars  	WordPress's query variables
	* 	@return 	array 		$vars 	The Modified array of query variables
	* 	@since 		1.0
	*/
	public function add_vars($vars) {
		$vars[] = $this->settings['query_arg'];
		return $vars;
	}


	/**
	*
	* 	ACTION: Registers wordpress post type for skins & alias settings
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function register() {

		// Get the post type settings from the class
		$psettings = $this->settings['post_type'];

		$name = $psettings['name'];
		$proper = ucwords($name);
		$plural = ucwords($psettings['plural']);

		// Create Labels array for wordpress
		$labels = array(
			'name' 					=> $proper
		,	'singular_name' 		=> $proper
		,	'plural_name' 			=> $plural
		,	'add_new_item' 			=> 'Add New '.$proper
		,	'edit_item' 			=> 'Edit '.$proper
		,	'new_item' 				=> 'New '.$proper
		,	'view_item' 			=> 'View '.$proper
		,	'search_items' 			=> 'Search '.$plural
		,	'not_found' 			=> 'No '.$plural.' found.'
		,	'not_found_in_trash' 	=> 'No '.$plural.' found in Trash.'
		,	'all_items' 			=> $plural
		,	'menu_name' 			=> $plural
		,	'name_admin_bar' 		=> $plural
		);

		// Create args array for wordpress
		$args = array(
			'labels' 				=> $labels
		,	'public' 				=> TRUE
		,	'publicly_queryable' 	=> TRUE
		,	'has_archive' 			=> FALSE
		,	'show_in_nav_menus' 	=> TRUE
		,	'menu_icon' 			=> $psettings['icon']
		,	'hierarchical' 			=> FALSE
		,	'supports' 				=> array('title')
		,	'menu_position' 		=> 10
		,	'show_in_menu' 			=> TRUE
		);

		// Register this post type
		register_post_type($name, $args);
		return;
	}


	/**
	*
	* 	FILTER: Sets post_type links to root instead of under url/post_type/name. This makes the post_type visitable from the admin link.
	*
	* 	@access 	public
	* 	@param 		string 		$post_link  	The post link to filter
	* 	@param 		int 		$id 			The ID fo the post
	* 	@return 	string 		$post_link  	The filtered or non-filtered link
	* 	@since 		1.0
	*/
	public function post_link($post_link,$id = 0) {
		// get the post by id
		$post = get_post($id);

		// get the post type from settings
		$posttype = $this->settings['post_type']['name'];

		// only modify if skin post type
		if (is_object($post) && $post->post_type==$posttype) {
			$post_link = str_replace($posttype.'/','',$post_link);
		}
		// return the link
		return $post_link;
	}


	/**
	*
	* 	FILTER: Filters site url for get_permalink() function. This fixes urls retreived when in a skin
	*
	* 	@access 	public
	* 	@param 		string 		$post_link  	The post link to filter
	* 	@param 		int 		$id 			The ID fo the post
	* 	@return 	string 		$post_link  	The filtered or non-filtered link
	* 	@since 		1.0
	*/
	public function filter_url($post_link,$id=0) {
		// use the current url
		$url = $this->url_validation();

		// if this is a valid theme we are viewing and the url being tested isn't valid
		if ($url['theme_id'] && !$this->is_valid_site($this->get_request_from_url($post_link))) {
			$post_link = $this->replace_url($post_link,$url['alias']);
		}
		// return the link
		return $post_link;
	}


	/**
	*
	* 	FILTER: Filters site url for get_nav_menu() function. This fixes urls retreived when in a skin
	*
	* 	@access 	public
	* 	@param 		array 		$items  	The array of menu items to run through
	* 	@return 	array 		$items  	The array of menu items being sent back to wordpress
	* 	@since 		1.0
	*/
	public function filter_menus($items) {
		// use the current url
		$url = $this->url_validation();

		// if currently in a theme
		if ($url['theme_id']) {
			if ( is_array($items) && count($items) > 0 ) {
				foreach ($items as &$item) {
					// if this is a valid theme we are viewing and the url being tested isn't valid
					if ( !$this->is_valid_site($this->get_request_from_url($item->url)) ) {
						$item->url = $this->replace_url($item->url,$url['alias']);
					}
				}
			}
		}

		// return the array of items
		return $items;
	}


	/**
	*
	* 	Gets the rest of the url after site_url() for site detection
	*
	* 	@access 	private
	* 	@param 		string 		$url  			The url to parse
	* 	@return 	string 		$request_file  	The url returned parsed
	* 	@since 		1.0
	*/
	private function get_request_from_url($url) {
		// replace the root url within the provided url
		$request_file = str_ireplace(site_url() . '/', '', $url);

		// get the end of the string, and if it ends with a / remove it
		if ( substr($request_file, strlen($request_file)-1) === '/' ) {
			$request_file = substr($request_file, 0, strlen($request_file)-1);
		}

		// the modified string
		return $request_file;
	}


	/**
	*
	* 	Replaces url with url + alias
	*
	* 	@access 	private
	* 	@param 		string 		$url  			The string to replace
	* 	@param 		string 		$alias  		The alias to add in
	* 	@return 	array 		$url  			The replaced url
	* 	@since 		1.0
	*/
	private function replace_url($url,$alias) {
		// set url with alias
		$new_url = site_url().'/'.$alias;

		// if the correct url has the new url already in it
		if ( !stristr($url,$new_url) ) {
			if ($url=='/') { 
				$url = $new_url; 
			}
			else  { 
				$url = str_replace(site_url(),$new_url,$url); 
			}
		}

		// return the url
		return $url;
	}


	/**
	*
	* 	Checks for alias detection or re-detects
	*
	* 	@access 	private
	* 	@return 	array 		$ret 		Details from validation
	* 	@since 		1.0
	*/
	private function url_validation() {
		if (isset($this->theme['alias']) && $this->is_valid_string($this->theme['alias'])) {
			$ret['alias'] = $this->theme['alias'];
			$ret['theme_id'] = get_query_var($this->settings['query_arg']);

		}
		else {
			$ret['alias'] = $this->get_site_alias();
			$ret['theme_id'] = $this->validate_theme($ret['alias']);
		}
		return $ret;
	}


	/**
	*
	* 	ACTION: Registers the meta box for theme selection
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function add_meta_box() {
		// get the post type name
		$post_type = $this->settings['post_type']['name'];

		// add the meta box
		add_meta_box(
			$post_type.'_settings',
			'Settings',
			array($this, 'display_meta'),
			$post_type,
			'normal',
			'default'
		);
		return;
	}


	/**
	*
	* 	ACTION: Saves the meta from the display_meta function
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function save_meta() {

		// ge thet current screen
		$current = get_current_screen();

		// only proceed if in skin post type
		if ( $current->base === 'post' && $current->post_type === $this->settings['post_type']['name'] ) {

			// set variables from class settings
			$alias_key = $this->settings['alias_key'];
			$theme_key = $this->settings['theme_key'];
			$nonce = $this->settings['nonce'];
			$post_type = $this->settings['post_type']['name'];

			// if post nonce fails and this is not an auto-save
			if ( !isset($_POST[$nonce]) ) {
				return;
			}
			if ( !wp_verify_nonce($_POST[$nonce], $nonce) ) {
				return;
			}
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
				return;
			}

			// set post id from save
			$post_id = $_POST['post_ID'];

			// do not proceed if this user cannot edit
			if ( !current_user_can('edit_post',$post_id) ) {
				return;
			}
			// if either required options from save aren't set, do not proceed
			if ( !isset($_POST[$theme_key]) || !isset($_POST[$alias_key]) ) {
				return;
			}

			// load all aliases from post submission
 			$aliases = '';
 			if (is_array($_POST[$alias_key])) {
 				for ($i=0; $i<count($_POST[$alias_key]); $i++) {
 					if (!$this->is_valid_string($_POST[$alias_key][$i])) unset($_POST[$alias_key][$i]);
 					else $_POST[$alias_key][$i] = sanitize_title($_POST[$alias_key][$i]);
 				}
 				// store it as json array
 				$aliases = json_encode($_POST[$alias_key]);
 			}

 			// update meta
			update_post_meta($post_id, $alias_key, sanitize_text_field($aliases));
			update_post_meta($post_id, $theme_key, sanitize_text_field($_POST[$theme_key]));
		}
	}


	/**
	*
	* 	ACTION: (AJAX) Validates the Alias to avoid conflicts when saving the post
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function check_alias() {

		// create output array
		$ret = array(
			'status' 	=> 202
		,	'message' 	=> $this->settings['alias_warning']
		,	'errors' 	=> array()
		);

		// get aliases from post form
		$aliases = FALSE;
		if ( isset($_POST['aliases']) ) {
			$aliases = $_POST['aliases'];
		}

		// if there are aliases
		if ( $aliases && is_array($aliases) && count($aliases) > 0 ) {

			// get current aliases
			$current_aliases_meta = get_post_meta($_POST['post'],$this->settings['alias_key'],true);
			$current_aliases = json_decode($current_aliases_meta);

			// load global rewrite rules
			global $wp_rewrite;
			$rules = $wp_rewrite->rewrite_rules();

			// check each alias
			foreach ( $aliases as $name=>$alias ) {

				// if either below condition checks out, change to fail
				$fail = false;
				if ( $this->check_rules($rules, $alias) && !in_array($alias, $current_aliases) ) { 
					$fail = true;
				}
				elseif ( count(array_keys($aliases, $alias)) > 1 ) { 
					$fail = true;
				}

				// if fail, add to errors array
				if ( $fail === true ) { 
					$ret['errors'][] = $name;
				}
			}

			// if any errors, return them to the page with a 500 status (202 otherwise)
			if ( count( $ret['errors'] ) > 0 ) {
				$ret['status'] 	= 500;
				$ret['message'] = 'This alias already exists as a rewrite rule, alias, or is listed twice above. Publish has been disabled.';
			}
		}

		// output json array
		echo json_encode($ret);
		die();
	}


	/**
	*
	* 	Loops through rules and checks for this name to be taken
	*
	* 	@access 	private
	* 	@param 		array 		$rules 		Array of rewrite rules to check against
	* 	@param 		string 		$alias 		The alias to check
	* 	@return 	boolean 	$return 	Whether this passed or failed
	* 	@since 		1.0
	*/
	private function check_rules($rules, $alias) {
		$return = false;
		if ( count($rules) > 0 ) {
			$err = 0;
			foreach ($rules as $rule=>$match) { 
				if ( strpos($rule, $alias.'/') === 0) {
					$err++;
				}
			}
			if ( $err > 0) {
				$return = true;
			}
		}
		return $return;
	}


	/**
	*
	* 	ACTION: Displays the meta HTML when editing a skin.
	*
	* 	@access 	public
	* 	@param 		object 		$post 		The $post variable passed by wordpress
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function display_meta($post) {

		// get class settings
		$alias_key = $this->settings['alias_key'];
		$theme_key = $this->settings['theme_key'];
		$nonce = $this->settings['nonce'];

		// create nonce field
		wp_nonce_field($nonce,$nonce);
		?>
		<table class="form-table cameleon-fields">
		<tr>
			<th class="header"><label>Theme:</label></th>
			<td>
			<select name="<?php echo $theme_key ?>" id="<?php echo $theme_key ?>">
			<option value="">-- Currently Active Theme --</option>
			<?php

			// load themes in select obx
			$themes_unfiltered = wp_get_themes();
			$themes = array();

			if ( is_array($themes_unfiltered) && count($themes_unfiltered) > 0 ) {

				// loop through themes
				foreach ($themes_unfiltered as $themename=>$themedata) {

					// set selected
					$selected = '';
					if ($themename==get_post_meta($post->ID,$theme_key,true)) {
						$selected=' selected="selected"';
					}
					?>
					<option value="<?php echo $themename ?>"<?php echo $selected ?>><?php echo $themedata->name ?></option>
					<?php
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
				<div class="cmln-generated-aliases"><?php echo $this->load_aliases($post->ID,$alias_key); ?></div>
				<div class="cmln-alias-template"><?php echo $this->load_alias_template($alias_key,'',false); ?></div>
				<br/>
				<p class="description cmln-desc"><?php echo $this->settings['alias_warning'] ?></p>
			</td>
		</tr>
		</table>
		<?php
	}


	/**
	*
	* 	ACTION: Loads the alias template for the admin settings page
	*
	* 	@access 	public
	* 	@param 		string 		$alias 		The alias to populate
	* 	@param 		string 		$key 		The meta key being used
	* 	@param 		int 		$idnum 		An ID number for this template (optional)
	* 	@return 	string 		$ret 	The html markup
	* 	@since 		1.0
	*/
	public function load_alias_template($key, $alias='', $idnum = 1) {

		// if optional id number is set to false, just use singular key
		if ( $idnum !== false ) {
			$id = $key.'_'.$idnum;
		}
		else {
			$id = $key;
		}

		// start output buffer
		ob_start();
		?>
		<div class="cmln-alias-field-wrap">
			<input type="text" name="<?php echo $key ?>[]" id="<?php echo $id ?>" class="cmln-alias-field" value="<?php echo $alias ?>" />
			<div class="dashicons dashicons-dismiss cmln-remove-alias" title="Remove This Alias"></div>
		</div>
		<?php

		// return the buffer
		return ob_get_clean();
	}


	/**
	*
	* 	Loads all the current aliases from meta
	*
	* 	@access 	private
	* 	@param 		object 		$pid 	The post ID to load the meta for
	* 	@param 		string 		$key 	The meta key being used
	* 	@return 	string 		$ret 	The html markup
	* 	@since 		1.0
	*/
	private function load_aliases($pid, $key) {
		// set empty string
		$ret = '';

		// get all post meta
		$aliases = get_post_meta($pid,$key,true);

		// load meta as json
		if ($aliases && @$alias_json = json_decode($aliases)) {
			$id = 0;
			foreach ($alias_json as $alias) {
				$ret .= $this->load_alias_template($key,$alias,$id);
				$id++;
			}
		}
		// else just load a default template
		else {
			$ret .= $this->load_alias_template($key);
		}
		return $ret;
	}


	/**
	*
	* 	Gets the list of sites and their associated urls
	*
	* 	@access 	private
	* 	@return 	array 		$urls 		The array of sites
	* 	@since 		1.0
	*/
	private function get_sites() {

		// get all loaded skins in cache
		$cache = $this->sites;

		// if no cache exists
		if ( !is_array($cache) || count($cache) === 0 ) {

			// get all skin post types
			$args = array(
				'posts_per_page' 	=> -1
			,	'post_type'			=> $this->settings['post_type']['name']
			);
			$sites = get_posts($args);

			// create array
			$urls = array();
			if ($this->is_valid_array($sites)) {

				// loop through them
				foreach ($sites as $site) {

					// set each skins details
					$urls[$site->ID][] = $site->post_name;

					// get this skins aliases
					$addons = get_post_meta($site->ID, $this->settings['alias_key'], true);

					// try to decode
					if ($this->is_valid_string($addons) && @json_decode($addons)) {
						@$addons = json_decode($addons);
					}

					// add these aliases as well if valid
					if ( $this->is_valid_array($addons) && count($addons)>0 ) {
						foreach ($addons as $addon) {
							$urls[$site->ID][] = $addon;
						}
					}
				}
			}
		}
		// load the cached skins
		else {
			$urls = $cache;
		}

		// if valid results resturned, set cache and return
		if ($this->is_valid_array($urls)) {
			$this->sites = $urls;
			return $urls;
		}
		// else return false
		else {
			return FALSE;
		}
	}


	/**
	*
	* 	Validates the given site name against the cached (or non caches) site list
	*
	* 	@access 	private
	* 	@param 		string 		$name 		The name of the site to check
	* 	@return 	bool 					The success or failure of the function
	* 	@since 		1.0
	*/
	private function is_valid_site($name) {

		// get the skins
		$sites = $this->get_sites();

		// if sites exist
		if ( $this->is_valid_array($sites) ) {

			// loop each site
			foreach ( $sites as $site_id => $urls ) {

				// for each alis in this skin
				foreach ( $urls as $url ) {

					// if match found return immediately
					if ( $url === $name ) {
						return TRUE;
					}
				}
			}
		}
		return FALSE;
	}


	/**
	*
	* 	FILTER: Adds the rewrite rules into the wordpress rewrite rules array (prepend)
	*
	* 	@access 	public
	* 	@param 		object 		$rules 		Rules passed from wordpress
	* 	@return 	object 		$rules 		Rules after insertions / modifications
	* 	@since 		1.0
	*/
	public function add_rewrites($rules) {
		// create empty new rules array
		$new_rules = array();

		// get all skins
		$sites = $this->get_sites();

		// if skins exist
		if ( $this->is_valid_array($sites) ) {

			// loop through skins
			foreach ($sites as $site=>$urls) {

				// set query arg
				$var = $this->settings['query_arg'].'='.$site;

				// add rule with alises
				foreach ($urls as $url) {
					$new_rules[$url.'/?$'] = 'index.php?'.$var;
					foreach ($rules as $rewrite_match=>$rewrite_to) {
						$new_match = $url.'/'.$rewrite_match;
						$new_rules[$new_match] = $rewrite_to.'&'.$var;
					}
				}
			}

			// if any rules were added, merge the array
			if ( count($new_rules) > 0 ) {
				$rules = array_merge($new_rules,$rules);
			}
		}
		return $rules;
	}


	/**
	*
	* 	Gets the theme for the current skin by getting the theme meta by post name or alias name
	*
	* 	@access 	private
	* 	@return 	mixed 		$themename 		The name of the theme if valid, FALSE on failure
	* 	@since 		1.0
	*/
	private function get_theme() {

		// gets the skin alias
		$alias = $this->get_site_alias();

		// validate this theme
		$id = $this->validate_theme($alias);

		// if valid
		if ($id) {

			// get the post for this skin
			$post = get_post($id);

			// if the view is logged into the admin or if this theme isn't published
			if ( !is_user_logged_in() && $post->post_status != 'publish' ) {
				return FALSE;
			}

			// load aliases for this theme
			$themename = get_post_meta($id, $this->settings['theme_key'], TRUE);

			// if this theme name is valid, return it
			if ($themename && $this->is_valid_string($themename)) {
				$this->theme['alias'] = $alias;
				return $themename;
			}
			else {
				return FALSE;
			}
		}
		else {
			return FALSE;
		}
	}


	/**
	*
	* 	Validates the theme from url alias
	*
	* 	@access 	private
	* 	@param 		string 		The alias retreived from get_site_alias
	* 	@return 	int 		The ID of the site to get the name for
	* 	@since 		1.0
	*/
	private function validate_theme($alias) {

		// load wpdb for custom query and gets skins
		global $wpdb;
		$id = $wpdb->get_var("SELECT ID FROM ".$wpdb->posts." WHERE post_name = '".$alias."' AND post_type = '".$this->settings['post_type']['name']."' LIMIT 1");

		// if a valid result is returned
		if (!$id) {

			// get the aliases
			$metas = $wpdb->get_results("SELECT post_id,meta_value FROM ".$wpdb->postmeta." WHERE meta_key = '".$this->settings['alias_key']."'", ARRAY_A);
			foreach ($metas as $meta) {

				// if json decode succeeds
				@$addons = json_decode($meta['meta_value']);
				if ($this->is_valid_array($addons)) {

					// load aliases
					foreach ($addons as $addon) {
						if ($addon==$alias) $id = $meta['post_id'];
					}
				}
			}
		}
		return $id;
	}


	/**
	*
	* 	Gets the virtual site being used from the url
	*
	* 	@access 	private
	* 	@return 	string 		The alias name from url
	* 	@since 		1.0
	*/
	private function get_site_alias() {
		// get requested url from server
		$url = $_SERVER['REQUEST_URI'];

		// get script location being ran (this will be at the root level
		$script = $_SERVER['PHP_SELF'];

		// get just the root of the site from the file
		$root = substr($script,0,strpos($script,strrchr($script,'/')));

		// remove the root
		$url = str_replace($root,'',$url);

		// parse url start and end
		if ( strpos($url,'/') === 0 ) {
			$url = substr($url,1);
		}
		if ( strrpos($url,'/') === (strlen($url)-1) ) {
			$url = substr($url,0,-1);
		}
		if ( strpos($url,'/') ) {
			$url = substr($url,0,(strpos($url,'/')));
		}
		return $url;
	}


	/**
	*
	* 	ACTION: Builds the theme info for switching and adds the appropriate WordPress filters
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function switch_theme() {

		// do not switch theme on preview
		if (isset($_GET['preview'])) {
			return false;
		}

		// load selected theme name
		$theme_name = $this->get_theme();

		// load current theme
		$current_theme = wp_get_theme();

		// if seleted theme is not valid or if the current theme is the selected theme, do not load
		if (!$theme_name || $theme_name == $current_theme) {
			return false;
		}

		// load the theme from selected theme name
		$theme_data = wp_get_theme($theme_name);

		// if selected theme object is not returned
		if ( !is_object($theme_data) ) {
			return;
		}

		// set the template and stylesheet names
		$template = $theme_data['Template'];
		$stylesheet = $theme_data['Stylesheet'];

		// filter bad data from template name
		$template = preg_replace('|[^a-z0-9_./-]|i', '', $template);
		// if theme is not valid, do not try to load
		if ( validate_file($template) ) {
			return false;
		}

		// filter bad data from stylesheet name
		$stylesheet = preg_replace('|[^a-z0-9_./-]|i', '', $stylesheet);
		// if stylesheet is not valid, do not try to load
		if ( validate_file($stylesheet) ) {
			return false;
		}

		// set current theme vars
		$this->theme['name'] = $theme_data['Name'];
		$this->theme['template'] = $template;
		$this->theme['stylesheet'] = $stylesheet;

		// add filters to make theme function correctly with it's respective settings (Sidebars included)
		add_filter('template', array($this, 'theme_filter_template'));
		add_filter('stylesheet', array($this, 'theme_filter_style'));
		add_filter('sidebars_widgets', array($this, 'theme_filter_widgets'));
		add_filter('theme_mod_{sidebars_widgets}', array($this, 'theme_filter_widgets'));
	}


	/**
	*
	* 	Checks theme template global variable and returns the template name for WordPress's use
	*
	* 	@access 	public
	* 	@return 	string 		The theme template
	* 	@since 		1.0
	*/
	public function theme_filter_template() {
		$theme = $this->theme;
		return (is_array($theme) && isset($theme['template'])) ? $theme['template'] : '';
	}


	/**
	*
	* 	Checks theme template global variable and returns the stylesheet name for WordPress's use
	*
	* 	@access 	public
	* 	@return 	string 		The theme stylesheet	
	* 	@since 		1.0
	*/
	public function theme_filter_style() {
		$theme = $this->theme;
		return (is_array($theme) && isset($theme['stylesheet'])) ? $theme['stylesheet'] : '';
	}


	/**
	*
	* 	FILTER: Gets sidebar widgets for the currently viewed theme instead of from the newly selected theme
	* 	(see widgets.php in the Wordpress install for the function wp_get_sidebars_widgets()
	*
	* 	@access 	public
	* 	@param 		string 		$widgets 		The widgets array sent by WordPress
	* 	@return 	array 		$widgets 		The modified (or un-modified) array of widgets
	* 	@since 		1.0
	*/
	public function theme_filter_widgets($widgets) {
		// get theme name
		$theme = $this->theme;

		// get options for this theme
		$mods = get_option('theme_mods_'.$theme['stylesheet']);

		// if options are found and there are sidebar widgets, return modified
		if ( $mods !== false && isset($mods['sidebars_widgets']['data']) ) {
			return $mods['sidebars_widgets']['data'];
		}
		// else return un-modified
		else {
			return $widgets;
		}
	}


	/**
	*
	* 	ACTION: Enables flush_rules() on post save
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function enable_flush() {
		add_action('wp_loaded', array(get_called_class(), 'flush_rules'));
		return;
	}


	/**
	*
	* 	ACTION: Flushes rewrite rules on save of skin
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function flush_rules() {
		// only do if post save of skin
		if ( isset($_POST['post_type']) && $_POST['post_type'] == $this->settings['post_type']['name'] ) {
			flush_rewrite_rules();
		}
		return;
	}


	/**
	*
	* 	ACTION: Enqueues all styles and scripts
	*
	* 	@access 	public
	* 	@return 	null
	* 	@since 		1.0
	*/
	public function set_scripts() {

		// only enqueue these if on the edit screen of the skin post type
		$current = get_current_screen();
		if ( $current->base==='post' && $current->post_type === $this->settings['post_type']['name'] ) {

			// perform enqueues
			wp_enqueue_script($this->settings['post_type']['name'] . '-edit-js', CMLN_URL . 'js/edit.js','jquery', null, true );
			wp_enqueue_style($this->settings['post_type']['name'] . '-edit-css', CMLN_URL . 'css/edit.css');

			// set localized JS data
			$data['url'] 	= admin_url('admin-ajax.php');
			$data['action'] = 'check_alias';

			// localize the data
			wp_localize_script($this->settings['post_type']['name'] . '-edit-js','cmln',$data);
		}
		return;
	}
}