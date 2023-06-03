<?php
/**
 * Plugin Name: Art Elements
 * Plugin URI: wpruse.ru
 * Text Domain: art-elements
 * Domain Path: /languages
 * Description: Добавление элемнтов на хуки
 * Version: 1.0.0
 * Author: Artem Abramovich
 * Author URI: https://wpruse.ru/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

const AE_PLUGIN_DIR       = __DIR__;
const AE_PLUGIN_AFILE        = __FILE__;
const AE_PLUGIN_VER       = '1.0.0';
const AE_PLUGIN_SLUG      = 'art-elements';
const AE_PLUGIN_TEPMLATES = 'templates';

define( 'AE_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
define( 'AE_PLUGIN_FILE', plugin_basename( __FILE__ ) );

require AE_PLUGIN_DIR . '/vendor/autoload.php';

function ae() {

	return \Art\Elements\Main::instance();
}


ae();