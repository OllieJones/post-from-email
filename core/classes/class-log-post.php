<?php

namespace Post_From_Email {

  use Exception;
  use http\Exception\RuntimeException;

  /**
   * A post log entry.
   * Arrays of these, as objects, go into postmeta POST_FROM_EMAIL_SLUG . '-log'
   */
  class Log_Post {

    /**
     * Maximum number of entries in a stored array of these items.
     */
    const MAX_LOG_LENGTH = 50;

    /**
     * @var string The meta key for storing arrays of these log items.
     */
    public static $log_meta;
    /**
     * @var int The profile ID making a post.
     */
    public $profile_id;
    /**
     * @var int -1 if no messages found, 0 if something invalid happened, 1 if valid.
     */
    public $valid = 0;
    /**
     * @var int The post ID
     */
    public $post_id = - 1;
    /**
     * @var string From string in message
     */
    public $from = '';
    /**
     * @var string Subject of message.
     */
    public $subject = '';
    /**
     * @var int Passing the allowlist: -1:not checked (no allowlist is active)  0:did not pass  1:passed
     */
    public $allowed = - 1;
    /**
     * @var int Passing the signature: -1:not checked  0:did not pass  1:passed
     */
    public $signed = - 1;
    /**
     * @var string Source of the message (Constant Contact, Mailchimp, etc).
     */
    public $source = '';
    /**
     * @var array Errors encountered.
     */
    public $errors = array();
    /**
     * @var int Timestamp of item.
     */
    public $time;

    public function __construct( $profile_id ) {
      $this->profile_id = $profile_id;
      $this->time       = time();
      self::$log_meta = POST_FROM_EMAIL_SLUG . '-log';
    }

    /**
     * Get the items in a log for a profile.
     *
     * @param $profile_id
     *
     * @return \Generator
     */
    public static function get( $profile_id ) {
      if (! self::$log_meta) {
        self::$log_meta = POST_FROM_EMAIL_SLUG . '-log';
      }
      $log = get_post_meta( $profile_id, self::$log_meta, true );
      $log = is_array( $log ) ? $log : array();

      foreach ( $log as $item ) {
        yield $item;
      }
    }

    /**
     * Store this item in the log array for the profile, at the beginning of the array.
     *
     * @param int|null $profile_id The post id of the profile.
     *
     * @return void
     */
    public function store( $profile_id = null ) {

      $profile_id = $profile_id ? $profile_id : $this->profile_id;

      $log = get_post_meta( $profile_id, self::$log_meta, true );
      $log = is_array( $log ) ? $log : array();
      if ( count( $log ) > self::MAX_LOG_LENGTH - 1 ) {
        $log = array_slice( $log, 0, self::MAX_LOG_LENGTH - 1 );
      }
      array_unshift( $log, $this );
      update_post_meta( $profile_id, self::$log_meta, $log );
    }
  }
}
