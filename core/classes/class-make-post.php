<?php

namespace Post_From_Email;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use Exception;
use Generator;
use WP_Error;
use WP_Post;

class Make_Post {

  const OK_MESSAGE = 128;
  const INVALID_MESSAGE = 1;
  const MISSING_SIGNATURE = 2;
  const INVALID_SIGNATURE = 3;
  const FROM_NOT_IN_ALLOWLIST = 4;
  const SOURCE_NOT_IN_ALLOWLIST = 5;
  const POST_CREATION_FAILURE = 6;
  const POST_CREATION_EXCEPTION = 7;
  /**
   * The log item.
   *
   * @var Log_Post
   */
  public $log_item;
  /**
   * @var mixed|string
   */
  protected $namespace;
  private $version;
  private $base;
  /**
   * The profile (template)
   *
   * @var \WP_POST
   */
  private $profile;
  /**
   * The credentials array
   *
   * @var array
   */
  private $credentials;
  /**
   * The uploaded email message
   *
   * @var array
   */
  private $upload;

  /**
   * @param array|null   $upload The uploaded email message.
   * @param WP_POST|null $profile The profile custom post to use as a template.
   * @param array|null   $credentials The mailbox-access credentials.
   */
  public function __construct( array $upload, WP_POST $profile = null, array $credentials = null ) {
    require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-log-post.php';
    require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/util.php';
    $this->init( $upload, $profile, $credentials );
    $this->log_item         = new Log_Post( $profile->ID );
    $this->log_item->source = $credentials['host'];
  }

  public function init( $upload, $profile, $credentials ) {
    $this->version     = '1';
    $this->namespace   = POST_FROM_EMAIL_SLUG;
    $this->base        = 'upload';
    $this->upload      = $upload;
    $this->profile     = $profile;
    $this->credentials = $credentials;
  }

  /**
   * Check that an email object passes filters, etc.
   */
  public function check() {
    $result = array();
    $valid  = is_array( $this->upload )
              && array_key_exists( 'headers', $this->upload )
              && array_key_exists( 'html', $this->upload );
    if ( ! $valid ) {
      $error = array(
        'code'    => self::INVALID_MESSAGE,
        'message' => __( 'Incomplete or invalid email message', 'post-from-email' ),
      );

      $this->log_item->errors [] = $error;
      $this->log_item->valid     = 0;

      return array( $result );
    }

    /* check the allowlist */
    if ( array_key_exists( 'from', $this->upload['headers'] ) ) {
      $from                 = $this->upload['headers']['from'];
      $this->log_item->from = $from;
    } else {
      $from = null;
    }
    $allowed = $this->sender_in_allowlist( $from );

    if ( ! $allowed ) {
      /* translators: 1: a sanitized email address */
      $message   = __( 'Message ignored because %1$s is not in the list of allowed senders', 'post-from-email' );
      $message   = sprintf( $message, esc_attr( $from ) );
      $result [] = array(
        'code'    => self::FROM_NOT_IN_ALLOWLIST,
        'message' => $message,
      );
    }

    if ( $this->credentials['dkim'] ) {
      $dkim_checked = $this->check_dkim_signature_exists( $this->upload['headers'] );
      if ( ! $dkim_checked ) {
        $message                   = __( 'Message ignored because it was not signed. ', 'post-from-email' );
        $error                     = array(
          'code'    => self::MISSING_SIGNATURE,
          'message' => $message,
        );
        $result []                 = $error;
        $this->log_item->errors [] = $error;
      } else {
        $dkim_verified = $this->verify_dkim_signature( $this->upload['headers'] );
        if ( ! $dkim_verified ) {
          $message                   = __( 'Message ignored because its signature was invalid. ', 'post-from-email' );
          $error                     = array(
            'code'    => self::INVALID_SIGNATURE,
            'message' => $message,
          );
          $result []                 = $error;
          $this->log_item->errors [] = $error;
        }
      }
    }

    return count( $result ) > 0 ? $result : true;
  }

  /**
   * Turn an email object into a post.
   *
   * @return string|WP_Error
   */
  public function process() {

    if ( false && array_key_exists( 'to', $this->upload['headers'] ) ) {  //TODO plus addressing?
      foreach ( $this->get_properties_from_email( imap_utf8( $this->upload['headers']['to'] ) ) as $category ) {
        $categories [] = $this->maybe_insert_category( $category, $category, $category );
      }
    }

    $categories = wp_list_pluck( get_the_terms( $this->profile, 'category' ), 'term_id' );
    $tags       = wp_list_pluck( get_the_terms( $this->profile, 'post_tag' ), 'term_id' );

    try {
      $html = mb_convert_encoding( $this->upload['html'], 'HTML-ENTITIES', "UTF-8" );

      /* A unique filename-safe (all lower case) tag for an email */
      $html_object_tag = base32_encode( md5( $html, true ) );
      $internal_errors = libxml_use_internal_errors( true );
      $doc = new DOMDocument( '1.0', 'utf-8' );
      $doc->preserveWhiteSpace = false;
      $doc->loadHTML( $html, LIBXML_NOWARNING );
      libxml_use_internal_errors( $internal_errors );

      $title = $this->getElementContents( $doc, '/html/head/title', '' );
      if ( 0 === strlen( $title ) ) {
        if ( array_key_exists( 'headers', $this->upload ) && array_key_exists( 'subject', $this->upload['headers'] ) ) {
          $title = imap_utf8( $this->upload['headers']['subject'] );
        }
      }
      $this->log_item->subject = $title;

      /* Use the date from the email header if available.  */
      $date                   =
        array_key_exists( 'headers', $this->upload ) && array_key_exists( 'date', $this->upload['headers'] )
          ? $this->upload['headers']['date']
          : 'now';
      $post_date_local        = ( new DateTimeImmutable( $date ) )->setTimezone( wp_timezone() );
      $post_date_local_string = $post_date_local->format( "Y-m-d\TH:i:s" );
      $post_date_utc_string   = $post_date_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( "Y-m-d\TH:i:s" );

      if ( array_key_exists( 'plain', $this->upload ) ) {
        $excerpt = $this->upload['plain'];
      } else {
        $excerpt = $this->getElementContents( $doc, '/html/body', '' );
        $excerpt = preg_replace( '/\s+/mu', ' ', $excerpt );
      }

      $source_meta = POST_FROM_EMAIL_SLUG . '-source';
      $content     = array();
      $content []  = '[';
      $content []  = POST_FROM_EMAIL_SLUG;
      $content []  = ' tag="';
      $content []  = $html_object_tag;
      $content []  = '" ';
      $content []  = ' meta_tag="';
      $content []  = $source_meta;
      $content []  = '" ';
      $content []  = ']';
      $post        = array(
        'post_author'    => $this->profile->post_author,
        /* TODO make this a number of whole words */
        'post_excerpt'   => substr( $excerpt, 0, 160 ),
        'post_date'      => $post_date_local_string,
        'post_date_gmt'  => $post_date_utc_string,
        'post_content'   => implode( '', $content ),
        'post_title'     => $title,
        'post_status'    => $this->profile->post_status,
        'post_category'  => $categories,
        'comment_status' => $this->profile->ping_status,
        'ping_status'    => $this->profile->comment_status,
        'tags_input'     => $tags,
      );
      $id          = wp_insert_post( $post, true, true );
      if ( is_wp_error( $id ) ) {
        $this->log_item->errors [] = array(
          'code'    => self::POST_CREATION_FAILURE,
          'message' => $id->get_error_message(),
        );
        $this->log_item->valid     = 0;

        return $id;
      }
      update_post_meta( $id, $source_meta, $html, 'post' );
      $this->update_links( $id, $this->profile->ID );
      $this->log_item->post_id = $id;
      $this->log_item->valid   = 1;
    } catch ( Exception $ex ) {
      $this->log_item->errors [] = array(
        'code'    => self::POST_CREATION_EXCEPTION,
        'message' => $ex->getMessage(),
      );
      $this->log_item->valid     = 0;

      return new WP_Error( 'imap', $ex->getMessage() );
    } finally {
      $this->log_item->store();
    }

    return 'OK';
  }

  /**
   * @return int|mixed|string|string[]|WP_Error|null
   */
  private function maybe_insert_category( $category, $description, $nicename ) {
    $uploadCategoryId = term_exists( $category );
    if ( ! $uploadCategoryId ) {
      $uploadCategoryId = wp_insert_category( [
        'cat_name'             => $category,
        'category_description' => $description,
        'category_nicename'    => $nicename,
      ] );
    }

    return $uploadCategoryId;
  }

  /**
   * Retrieve an element's text contents from a DOMDocument.
   *
   * @param DOMDocument $doc The document.
   * @param string      $doc_path The xpath of the desired element. We return the first element found.
   * @param string      $default The default value if the element isn't found.
   *
   * @return mixed
   */
  private function getElementContents( DOMDocument $doc, $doc_path, $default = 'unknown' ) {
    $result = $default;
    try {
      $xpath = new DOMXPath( $doc );
      $els   = $xpath->query( $doc_path );
      foreach ( $els as $el ) {
        $result = $el->textContent;
        /* Return the first element found. */
        break;
      }
    } catch ( Exception $ex ) {
      /* empty, intentionally */
    }

    return $result;
  }

  /**
   * See if the mail was sent to address+category|category|category@example.com .
   *
   * @param string $to Email address.
   *
   * @return Generator
   */
  private function get_properties_from_email( $to ) {

    $splits = explode( '@', $to, 2 );
    $to     = $splits[0];
    $splits = explode( '+', $to, 2 );
    if ( 2 === count( $splits ) && strlen( $splits[1] ) > 0 ) {
      $categories = $splits[1];
      $categories = explode( '|', $categories );
      foreach ( $categories as $category ) {
        if ( strlen( $category ) > 0 ) {
          yield $category;
        }
      }
    }
  }

  /**
   * Check whether the sender is in the allowlist.
   *
   * @param string $sender From address.
   *
   * @return bool True if the message should be allowed
   */
  private function sender_in_allowlist( $sender ) {
    $allowed                 = false;
    $this->log_item->allowed = 0;
    if ( is_array( $this->credentials )
         && array_key_exists( 'allowlist', $this->credentials )
         && is_string( $this->credentials['allowlist'] )
         && strlen( $this->credentials['allowlist'] ) > 0 ) {
      $allows = explode( "\n", sanitize_email_list( $this->credentials['allowlist'] ) );

      if ( $sender ) {
        foreach ( $allows as $allow ) {
          if ( strlen( $allow ) > 0 ) {
            if ( str_contains( strtolower( $sender ), strtolower( $allow ) ) ) {
              $this->log_item->allowed = 1;

              return true;
            }
          }
        }
      }
    } else {
      $this->log_item->allowed = - 1;
      $allowed                 = true;
    }

    return $allowed;
  }

  /**
   * Check for the existence of the DKIM signature.
   *
   * @param array $headers Email headers.
   *
   * @return bool False if we should ignore the message.
   */
  private function check_dkim_signature_exists( $headers ) {
    if ( array_key_exists( 'dkim', $this->credentials ) && $this->credentials['dkim'] ) {
      $result                 = Pop_Email::check_dkim_signature_exists( $headers );
      $this->log_item->signed = $result ? 1 : 0;

      return $result;
    } else {
      $this->log_item->signed = - 1;

      return true;
    }
  }

  /**
   * Verify the DKIM signature.
   *
   * @param array $headers Email headers.
   *
   * @return bool Falso if we should ignore the message.
   */
  private function verify_dkim_signature( $headers ) {
    if ( array_key_exists( 'dkim', $this->credentials ) && $this->credentials['dkim'] ) {
      $result                 = Pop_Email::verify_dkim_signature( $headers );
      $this->log_item->signed = $result ? 1 : 0;

      return $result;
    } else {
      $this->log_item->signed = - 1;

      return true;
    }
  }

  /**
   * Use metadata to link the post to the profile responsible for its creation.
   *
   * @param $post_id
   * @param $profile_id
   *
   * @return void
   */
  private function update_links( $post_id, $profile_id ) {

    $my_profile_key = POST_FROM_EMAIL_SLUG . '-profile';
    update_post_meta( $post_id, $my_profile_key, $profile_id );

    $my_posts_key = POST_FROM_EMAIL_SLUG . '-posts';
    $myposts      = get_post_meta( $profile_id, $my_posts_key, true );
    $myposts      = is_array( $myposts ) ? $myposts : array();
    $myposts []   = $post_id;
    update_post_meta( $profile_id, $my_posts_key, $myposts );
  }
}
