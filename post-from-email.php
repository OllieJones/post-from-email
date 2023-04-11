<?php
/**
 * Post from Email
 *
 * @package       post-from-email
 * @author        Ollie Jones
 * @license       gplv2
 *
 * @wordpress-plugin
 * Plugin Name:   Post from Email
 * Plugin URI:    https://github.com/OllieJones/post-from-email
 * Description:   Create WordPress posts from email messages.
 * Version:       0.3.4
 * Author:        Ollie Jones
 * Author URI:    https://github.com/OllieJones
 * Text Domain:   post-from-email
 * Domain Path:   /languages
 * License:       GPLv2
 * License URI:   https://www.gnu.org/licenses/gpl-2.0.html
 *
 * You should have received a copy of the GNU General Public License
 * along with Post from Email. If not, see <https://www.gnu.org/licenses/gpl-2.0.html/>.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * HELPER COMMENT START
 *
 * This file contains the main information about the plugin.
 * It is used to register all components necessary to run the plugin.
 *
 * The comment above contains all information about the plugin
 * that are used by WordPress to differenciate the plugin and register it properly.
 * It also contains further PHPDocs parameter for a better documentation
 *
 * The function POST_FROM_EMAIL() is the main function that you will be able to
 * use throughout your plugin to extend the logic. Further information
 * about that is available within the subclasses.
 *
 * HELPER COMMENT END
 */

// Plugin name
const POST_FROM_EMAIL_NAME = 'Post from Email';

// Plugin version
const POST_FROM_EMAIL_VERSION = '0.3.4';

// Plugin Root File
const POST_FROM_EMAIL_PLUGIN_FILE = __FILE__;

// Plugin base
define( 'POST_FROM_EMAIL_PLUGIN_BASE', plugin_basename( POST_FROM_EMAIL_PLUGIN_FILE ) );

// Plugin slug
define( 'POST_FROM_EMAIL_SLUG', explode( DIRECTORY_SEPARATOR, POST_FROM_EMAIL_PLUGIN_BASE)[0] );

// Plugin Folder Path
define( 'POST_FROM_EMAIL_PLUGIN_DIR', plugin_dir_path( POST_FROM_EMAIL_PLUGIN_FILE ) );

// Plugin Folder URL
define( 'POST_FROM_EMAIL_PLUGIN_URL', plugin_dir_url( POST_FROM_EMAIL_PLUGIN_FILE ) );

// Plugin's custom post type for source profile (email address)
const POST_FROM_EMAIL_PROFILE = POST_FROM_EMAIL_SLUG . '-prof';

/**
 * Load the main class for the core functionality
 */
require_once POST_FROM_EMAIL_PLUGIN_DIR . 'core/classes/class-main.php';

Post_From_Email\Main::instance();
