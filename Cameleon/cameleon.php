<?php
/*
    Plugin Name: Cameleon
    Plugin URI: http://clarknikdelpowell.com
    Version: 0.2.0
    Description: Virtual Microsite Creator & Multi-Theme Enabler for WordPress
    Author: Samuel Mello
    Author URI: http://clarknikdelpowell.com/agency/people/sam

    Copyright 2014+ Clark/Nikdel/Powell (email : sam@clarknikdelpowell.com)

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

define('CMLN_LOCAL', ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1'));
define('CMLN_PATH', plugin_dir_path(__FILE__));
define('CMLN_URL', CMLN_LOCAL ? plugins_url().'/Cameleon/' : plugin_dir_url(__FILE__));

/* Include Class */
require_once(CMLN_PATH.'class.php');

/* Initialize Class with Settings */
cameleon::initialize('fdoc_site','microsite','fdoc_microsite_add_on_urls','fdoc_microsite_theme');