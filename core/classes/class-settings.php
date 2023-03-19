<?php

namespace Post_From_Email {
// Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }

  /**
   * HELPER COMMENT START
   *
   * This class contains all of the plugin related settings.
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
   * This class contains all of the plugin settings.
   * Here you can configure the whole plugin data.
   *
   * @package    POST_FROM_EMAIL
   * @subpackage  Classes/Post_From_Email_Settings
   * @author    Ollie Jones
   * @since    1.0.0
   */
  class Settings {

    /**
     * The plugin name
     *
     * @var    string
     * @since   1.0.0
     */
    private $plugin_name;

    /**
     * Our Post_From_Email_Settings constructor
     * to run the plugin logic.
     *
     * @since 1.0.0
     */
    function __construct() {

      $this->plugin_name = POST_FROM_EMAIL_NAME;
    }

    /**
     * ######################
     * ###
     * #### CALLABLE FUNCTIONS
     * ###
     * ######################
     */

    /**
     * Return the plugin name
     *
     * @access  public
     * @return  string The plugin name
     * @since  1.0.0
     */
    public function get_plugin_name() {
      return apply_filters( 'POST_FROM_EMAIL/settings/get_plugin_name', $this->plugin_name );
    }
  }
}
