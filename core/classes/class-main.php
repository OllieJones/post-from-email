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
     * @author    Ollie Jones
     */
    final class Main {

      const CLEAN_EVENT_HOOK = POST_FROM_EMAIL_SLUG . '-clean';
      /**
       * The real instance
       *
       * @access  private
       * @var    object|Main
       */
      private static $instance;

      /**
       * Settings object.
       *
       * @access  public
       * @var    object|Settings
       */
      public $settings;

      /**
       * Main Embed Instance.
       *
       * Insures that only one instance of Post_From_Email exists in memory at any one
       * time. Also prevents needing to define globals all over the place.
       * This class is an unenforced singleton.
       *
       * @access    public
       * @return    object|Main  The one true Post_From_Email
       * @static
       */
      public static function instance() {
        if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Main ) ) {
	        self::$instance = new Main;
	        self::$instance->base_hooks();
	        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-controller.php';
	        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-run.php';
	        if ( is_admin() ) {
		        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-settings.php';
		        self::$instance->settings = new Settings();
	        }
	        self::$instance->rest = new Controller();
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
       * Configure the hooks.
       *
       * @return void
       */
      private function base_hooks() {
        add_action( 'plugins_loaded', [ self::$instance, 'load_textdomain' ] );
        /* handle cron cache cleanup */
        add_action( self::CLEAN_EVENT_HOOK, array( $this, 'clean' ), 10, 0 );
        if ( ! wp_next_scheduled( self::CLEAN_EVENT_HOOK ) ) {
          wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEAN_EVENT_HOOK );
        }
      }

      /**
       * Loads the plugin language files.
       *
       * @access  public
       * @return  void
       */
      public function load_textdomain() {
        load_plugin_textdomain( 'post-from-email', false, dirname( plugin_basename( POST_FROM_EMAIL_PLUGIN_FILE ) ) . '/languages/' );
      }

      /**
       * Cronjob to clean up cache directory.
       *
       * This erases files from the cache directory that don't have corresponding
       * transients.
       *
       * @return void
       */
      public function clean () {
        $dirs    = wp_upload_dir();
        $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
        if ( ! @file_exists( $dirname ) ) {
          @mkdir( $dirname );
        }
        $files = scandir ( $dirname );
        foreach ($files as $file) {
          if (is_string ($file) && str_ends_with ($file, '.html') ) {
            $path = get_transient( POST_FROM_EMAIL_SLUG . '-file-' . $file );
            if ( ! $path ) {
              $pathname = $dirname . DIRECTORY_SEPARATOR . $file;
              @unlink( $pathname );
              if ( WP_DEBUG_LOG ) {
                error_log (POST_FROM_EMAIL_NAME . ': removed cache file ' . $pathname );
              }
            }
          }
        }
      }

    }

  endif; // End if class_exists check.

}
