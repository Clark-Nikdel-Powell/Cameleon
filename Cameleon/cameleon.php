<?php
/*
	Plugin Name: Cameleon
	Plugin URI: https://github.com/Clark-Nikdel-Powell/Cameleon
	Version: 1.6.0
	Description: Virtual Microsite Creator & Multi-Theme Enabler for WordPress
	Author: Samuel Mello
	Author URI: http://clarknikdelpowell.com/agency/people/sam

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2 (or later),
	as published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('CMLN_LOCAL',    ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1'));
define('CMLN_PATH',     plugin_dir_path(__FILE__));
define('CMLN_URL',      CMLN_LOCAL ? plugins_url().'/Cameleon/' : plugin_dir_url(__FILE__));

// include the class
require_once(CMLN_PATH.'class.Cameleon.php');


$settings = array(
	// The query args
	'query_arg' 	=> 'cameleon'
	// the post type settings
,	'post_type' 	=> array(
		// key name of post type (lowercase, no spaces, etc)
		'name' 		=> 'skin'
		// the plural name of the post name
	,	'plural' 	=> 'skins'
		// the icon to use in the menu bar
	,	'icon'		=> 'dashicons-art'
	)
	// the meta key for aliases
,	'alias_key' 	=> 'skin_alias'
	// the meta key for skins
,	'theme_key' 	=> 'skin_theme'
	// the nonce name / id
,	'nonce'	 		=> 'cameleon-nonce'
	// The message to display when aliases are used already
,	'alias_warning' => 'Warning: using existing rewrite rules is prohibited.'
);

// instantiate plugin
$cmln = new Cameleon($settings);