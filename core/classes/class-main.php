<?php

namespace Post_From_Email {
// Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) ) {
    exit;
  }


  if ( ! class_exists( 'Main' ) ) :

    /**
     * Main Post_From_Email Class.
     *
     * @package    POST_FROM_EMAIL
     * @subpackage  Classes/Post_From_Email
     * @since    1.0.0
     * @author    Ollie Jones
     */
    final class Main {

      /**
       * The real instance
       *
       * @access  private
       * @since  1.0.0
       * @var    object|Main
       */
      private static $instance;

      /**
       * Settings object.
       *
       * @access  public
       * @since  1.0.0
       * @var    object|Settings
       */
      public $settings;

      /**
       * Throw error on object clone.
       *
       * Cloning instances of the class is forbidden.
       *
       * @access  public
       * @return  void
       * @since  1.0.0
       */
      public function __clone() {
        _doing_it_wrong( __FUNCTION__, __( 'You are not allowed to clone this class.', 'post-from-email' ), '1.0.0' );
      }

      /**
       * Disable unserializing of the class.
       *
       * @access  public
       * @return  void
       * @since  1.0.0
       */
      public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, __( 'You are not allowed to unserialize this class.', 'post-from-email' ), '1.0.0' );
      }

      /**
       * Main Embed Instance.
       *
       * Insures that only one instance of Post_From_Email exists in memory at any one
       * time. Also prevents needing to define globals all over the place.
       *
       * @access    public
       * @return    object|Main  The one true Post_From_Email
       * @since    1.0.0
       * @static
       */
      public static function instance() {
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Main ) ) {
          self::$instance = new Main;
          self::$instance->base_hooks();
          self::$instance->includes();
          self::$instance->settings = new Settings();
          self::$instance->rest     = new Controller();
          self::$instance->rest->init();

          //Fire the plugin logic
          new Run();

          /**
           * Fire a custom action to allow dependencies
           * after the successful plugin setup
           */
          do_action( 'POST_FROM_EMAIL/plugin_loaded' );
        }

        return self::$instance;
      }

      /**
       * Include required files.
       *
       * @access  private
       * @return  void
       * @since   1.0.0
       */
      private function includes() {
        //require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-helpers.php';
        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-settings.php';
        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-controller.php';

        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-run.php';
      }

      /**
       * Add base hooks for the core functionality
       *
       * @access  private
       * @return  void
       * @since   1.0.0
       */
      private function base_hooks() {
        add_action( 'plugins_loaded', [ self::$instance, 'load_textdomain' ] );
      }

      /**
       * Loads the plugin language files.
       *
       * @access  public
       * @return  void
       * @since   1.0.0
       */
      public function load_textdomain() {
        load_plugin_textdomain( 'post-from-email', false, dirname( plugin_basename( POST_FROM_EMAIL_PLUGIN_FILE ) ) . '/languages/' );
      }

    }

  endif; // End if class_exists check.

}
