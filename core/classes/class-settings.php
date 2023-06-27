<?php

namespace Post_From_Email {

	// Exit if accessed directly.
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * HELPER COMMENT START
	 *
	 * This class contains all the plugin related settings.
	 * Everything that is relevant data and used multiple times throughout
	 * the plugin.
	 *
	 * To define the actual values, we recommend adding them as shown above
	 * within the __construct() function as a class-wide variable.
	 * This variable is then used by the callable functions down below.
	 * These callable functions can be called everywhere within the plugin
	 * as followed using the get_plugin_name() as an example:
	 *
	 * POST_FROM_EMAIL->settings->get_plugin_name();
	 *
	 * HELPER COMMENT END
	 */

	/**
	 * Class Post_From_Email_Settings
	 *
	 * This class contains all the plugin settings.
	 * Here you can configure the whole plugin data.
	 *
	 * @package    POST_FROM_EMAIL
	 * @subpackage  Classes/Post_From_Email_Settings
	 * @author    Ollie Jones
	 */
	class Settings {

		/**
		 * The plugin name
		 *
		 * @var    string
		 */
		private $plugin_name;

		/**
		 * Our Post_From_Email_Settings constructor
		 * to run the plugin logic.
		 *
		 */
		function __construct() {

			$this->plugin_name = POST_FROM_EMAIL_NAME;
			$this->initialize();
		}

		/**
		 * Return the plugin name
		 *
		 * @access  public
		 * @return  string The plugin name
		 */
		public function get_plugin_name() {
			return apply_filters( 'POST_FROM_EMAIL/settings/get_plugin_name', $this->plugin_name );
		}

		private function initialize() {
      /* action link for plugins page */
      add_filter( 'plugin_action_links_' . plugin_basename( POST_FROM_EMAIL_PLUGIN_FILE ), [ $this, 'action_link' ] );

    }

    /**
     * Filters the list of action links displayed for a specific plugin in the Plugins list table.
     *
     * The dynamic portion of the hook name, `$plugin_file`, refers to the path
     * to the plugin file, relative to the plugins directory.
     *
     * @param string[] $actions An array of plugin action links. By default, this can include
     *                              'activate', 'deactivate', and 'delete'. With Multisite active
     *                              this can also include 'network_active' and 'network_only' items.
     * @param string $plugin_file Path to the plugin file relative to the plugins directory.
     * @param array $plugin_data An array of plugin data. See `get_plugin_data()`
     *                              and the {@see 'plugin_row_meta'} filter for the list
     *                              of possible values.
     * @param string $context The plugin context. By default this can include 'all',
     *                              'active', 'inactive', 'recently_activated', 'upgrade',
     *                              'mustuse', 'dropins', and 'search'.
     *
     * @since 2.7.0
     * @since 4.9.0 The 'Edit' link was removed from the list of action links.
     *
     * @noinspection PhpDocSignatureInspection
     * @noinspection GrazieInspection
     */
    public function action_link( $actions ) {
      $mylinks = [
        '<a id="' . POST_FROM_EMAIL_PROFILE . '" href="' . admin_url( 'edit.php?post_type=' . POST_FROM_EMAIL_PROFILE ) . '">' . __( 'Edit templates' ) . '</a>',
      ];

      return array_merge( $mylinks, $actions );
    }
  }
}
