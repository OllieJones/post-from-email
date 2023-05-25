<?php

namespace Post_From_Email;

// Exit if accessed directly.
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Main Post_From_Email Class.
 *
 * @package    POST_FROM_EMAIL
 * @subpackage  Classes/Post_From_Email
 * @author    Ollie Jones
 */
final class Main {

  const CLEAN_EVENT_HOOK = POST_FROM_EMAIL_SLUG . '-clean';
  const CHECK_MAILBOXES_EVENT_HOOK = POST_FROM_EMAIL_SLUG . '-check-mailboxes';
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
   * Profile (custom post type) object.
   *
   * @access  public
   * @var    Profile
   */
  public $profile;

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
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-controller.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-run.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-pop-email.php';
      self::$instance = new Main;
      if ( is_admin() ) {
        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-settings.php';
        self::$instance->settings = new Settings();
        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-profile.php';
        self::$instance->profile = new Profile();
      }
      self::$instance->rest = new Controller();
      self::$instance->rest->init();

      //Fire the plugin logic
      new Run();

      register_activation_hook( POST_FROM_EMAIL_PLUGIN_FILE, array( self::$instance, 'on_activation' ) );
      register_deactivation_hook( POST_FROM_EMAIL_PLUGIN_FILE, array( self::$instance, 'unschedule_hooks' ) );
    }
    add_action( 'plugins_loaded', [ self::$instance, 'cronjobs' ] );

    return self::$instance;
  }

  public static function unschedule_mailbox_check( $profile_id ) {
    $args           = array( $profile_id );
    $scheduled_time = wp_next_scheduled( self::CHECK_MAILBOXES_EVENT_HOOK, $args );
    if ( false !== $scheduled_time ) {
      wp_unschedule_event( $scheduled_time, self::CHECK_MAILBOXES_EVENT_HOOK, $args );
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
  public function clean_cache_directory() {
    $dirs    = wp_upload_dir();
    $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
    if ( ! @file_exists( $dirname ) ) {
      @mkdir( $dirname );
    }
    $files = scandir( $dirname );
    foreach ( $files as $file ) {
      if ( is_string( $file ) && str_ends_with( $file, '.html' ) ) {
        $path = get_transient( POST_FROM_EMAIL_SLUG . '-file-' . $file );
        if ( ! $path ) {
          $pathname = $dirname . DIRECTORY_SEPARATOR . $file;
          @unlink( $pathname );
        }
      }
    }
  }

  /**
   * Cronjob to check the registered mailboxes.
   *
   * @param int $profile_id Post ID of the email profile to check.
   *
   * @return void
   */
  public function check_mailboxes( $profile_id ) {

    Pop_Email::check_mailboxes( 10, $profile_id );
  }

  /**
   * Configure the cronjobs.
   *
   * @return void
   */
  public function cronjobs() {
    add_action( 'plugins_loaded', [ self::$instance, 'load_textdomain' ] );
    add_filter( 'cron_schedules', [ self::$instance, 'more_schedules' ] );
    /* Handle the cleanup of the html cache in cron. */
    add_action( self::CLEAN_EVENT_HOOK, array( $this, 'clean_cache_directory' ), 10, 0 );
    if ( ! wp_next_scheduled( self::CLEAN_EVENT_HOOK ) ) {
      wp_schedule_event( time() + MINUTE_IN_SECONDS, 'twicedaily', self::CLEAN_EVENT_HOOK );
    }

    $this->schedule_mailbox_checks();
  }

  public function more_schedules( $schedules ) {
    $schedules['twicehourly'] =
      array( 'display' => __( 'Twice Hourly', 'post-from-email' ), 'interval' => 30 * MINUTE_IN_SECONDS );
    $schedules['every2hours'] =
      array( 'display' => __( 'Every 2 Hours', 'post-from-email' ), 'interval' => 2 * HOUR_IN_SECONDS );
    $schedules['every3hours'] =
      array( 'display' => __( 'Every 3 Hours', 'post-from-email' ), 'interval' => 3 * HOUR_IN_SECONDS );
    $schedules['every4hours'] =
      array( 'display' => __( 'Every 4 Hours', 'post-from-email' ), 'interval' => 4 * HOUR_IN_SECONDS );
    $schedules['every6hours'] =
      array( 'display' => __( 'Four Times Daily', 'post-from-email' ), 'interval' => 6 * HOUR_IN_SECONDS );
    $schedules['every8hours'] =
      array( 'display' => __( 'Three Times Daily', 'post-from-email' ), 'interval' => 8 * HOUR_IN_SECONDS );

    return $schedules;
  }

  public function on_activation() {

  }

  /**
   * Unschedule this plugin's cron hooks upon deactivation
   *
   * @return void
   */
  public function unschedule_hooks() {
    wp_unschedule_hook( self::CLEAN_EVENT_HOOK );
    wp_unschedule_hook( self::CHECK_MAILBOXES_EVENT_HOOK );
  }

  private function schedule_mailbox_checks() {
    /* Handle polling mailboxes for new posts in cron */
    add_action( self::CHECK_MAILBOXES_EVENT_HOOK, array( $this, 'check_mailboxes' ), 10, 1 );

    foreach ( Pop_Email::get_active_mailboxes() as $profile => $credentials ) {
      /** @var WP_POST $profile */
      $args       = array( $profile->ID );
      $recurrence = $credentials['timing'];
      if ( ! wp_next_scheduled( self::CHECK_MAILBOXES_EVENT_HOOK, $args ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::CHECK_MAILBOXES_EVENT_HOOK, $args );
      }
    }
  }
}
