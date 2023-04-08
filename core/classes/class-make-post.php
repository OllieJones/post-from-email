<?php

namespace Post_From_Email {

  use DateTimeImmutable;
  use DateTimeZone;
  use DOMDocument;
  use DOMXPath;
  use Exception;
  use Generator;
  use WP_Error;
  use WP_REST_Request;
  use WP_REST_Response;

  class Make_Post {
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

    public function __construct( $profile = null, $credentials = null ) {
      $this->init( $profile, $credentials );
    }

    public function init( $profile, $credentials ) {
      $this->version     = '1';
      $this->namespace   = POST_FROM_EMAIL_SLUG;
      $this->base        = 'upload';
      $this->profile     = $profile;
      $this->credentials = $credentials;
    }

    /**
     * Turn an email object into a post.
     *
     * @param array $upload Uploaded email object.
     *
     * @return string|WP_Error
     */
    public function process( $upload ) {

      $valid = is_array( $upload )
               && array_key_exists( 'headers', $upload )
               && array_key_exists( 'html', $upload );
      if ( ! $valid ) {
        return new WP_Error( 'imap', 'Invalid email upload array' );
      }

      /* check the allowlist */
      if ( array_key_exists( 'from', $upload['headers'] ) ) {
        $from = $upload['headers']['from'];
      } else {
        $from = null;
      }
      $allowed = $this->sender_in_allowlist( $from );

      if ( ! $allowed ) {
        /* translators: 1: a sanitized email address */
        $message = __( 'Sender %1$s not in allowed senders', 'post-from-email' );
        $message = sprintf( $message, esc_attr( $from ) );

        return new WP_Error( 'allowlist', $message );
      }

      if ( $this->credentials['dkim'] ) {
        $dkim_verified = $this->verify_dkim_signature( $upload['headers'] );
        if ( ! $dkim_verified ) {
          $message = __( 'Message is not signed. ', 'post-from-email' );

          return new WP_Error( 'dkim', $message );
        }
      }
      if ( array_key_exists( 'to', $upload['headers'] ) ) {
        foreach ( $this->get_properties_from_email( imap_utf8( $upload['headers']['to'] ) ) as $category ) {
          $categories [] = $this->maybe_insert_category( $category, $category, $category );
        }
      }

      $categories = get_the_terms( $this->profile, 'category' );
      $categories = array_map( function ( $item ) {
        return $item->term_id;
      }, $categories );

      $categories [] = $this->maybe_insert_category( 'Email', 'Post From Email', 'post-from-email' );

      $tags = get_the_terms( $this->profile, 'post_tag' );
      $tags = is_array( $tags ) ? $tags : array();
      $tags = array_map( function ( $item ) {
        return $item->term_id;
      }, $tags );

      try {
        $doc                     = new DOMDocument( '1.0', 'utf-8' );
        $doc->preserveWhiteSpace = false;

        $html = mb_convert_encoding( $upload['html'], 'HTML-ENTITIES', "UTF-8" );
        @$doc->loadHTML( $html, LIBXML_NOWARNING );

        $title = $this->getElementContents( $doc, '/html/head/title', '' );
        if ( 0 === strlen( $title ) ) {
          $title = imap_utf8( $upload['headers']['subject'] );
        }

        /* A unique filename-safe (all lower case) tag for an email */
        $tag = $this->base32_encode( md5( serialize( $upload['headers'] ), true ) );

        /* Use the date from the email header if available.  */

        $date                   = $upload['headers']['date'];
        $post_date_local        = new DateTimeImmutable( $date, wp_timezone() );
        $post_date_local_string = $post_date_local->format( "Y-m-d\TH:i:s" );
        $post_date_utc          = $post_date_local->setTimezone( new DateTimeZone( 'UTC' ) );
        $post_date_utc_string   = $post_date_utc->format( "Y-m-d\TH:i:s" );

        $meta_key   = POST_FROM_EMAIL_SLUG . '-source';
        $content    = array();
        $content [] = '[';
        $content [] = POST_FROM_EMAIL_SLUG;
        $content [] = ' tag="';
        $content [] = $tag;
        $content [] = '" ';
        $content [] = ' meta_tag="';
        $content [] = $meta_key;
        $content [] = '" ';
        $content [] = ']';
        $post       = array(
          'post_author'    => $this->profile->post_author,
          /* TODO make this a number of whole words */
          'post_excerpt'   => substr( $upload['plain'], 0, 160 ),
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
        $id         = wp_insert_post( $post, true, true );
        if ( is_wp_error( $id ) ) {
          return $id;
        }

        update_post_meta( $id, $meta_key, $doc->saveHTML(), 'post' );
      } catch ( Exception $ex ) {
        return new WP_Error( 'imap', $ex->getMessage() );
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
     * Encode a string in base32
     *
     * @param string $data A string containing only hex digits: output of md5()
     *
     * @return string
     */
    private function base32_encode( $data ) {
      $chars = '0123456789abcdefghjkmnpqrstvwxyz';
      $mask  = 0b11111;

      $dataSize      = strlen( $data );
      $res           = '';
      $remainder     = 0;
      $remainderSize = 0;

      for ( $i = 0; $i < $dataSize; $i ++ ) {
        $b             = ord( $data[ $i ] );
        $remainder     = ( $remainder << 8 ) | $b;
        $remainderSize += 8;
        while ( $remainderSize > 4 ) {
          $remainderSize -= 5;
          $c             = $remainder & ( $mask << $remainderSize );
          $c             >>= $remainderSize;
          $res           .= $chars[ $c ];
        }
      }
      if ( $remainderSize > 0 ) {
        $remainder <<= ( 5 - $remainderSize );
        $c         = $remainder & $mask;
        $res       .= $chars[ $c ];
      }

      return $res;
    }

    /**
     * Check whether the sender is in the allowlist.
     *
     * @param string $sender From address.
     *
     * @return bool True if the message should be allowed
     */
    private function sender_in_allowlist( string $sender ): bool {
      $allowed = false;
      if ( is_array( $this->credentials )
           && array_key_exists( 'allowlist', $this->credentials )
           && is_string( $this->credentials['allowlist'] )
           && strlen( $this->credentials['allowlist'] ) > 0 ) {
        $allows = explode( "\n", Pop_Email::sanitize_email_list( $this->credentials['allowlist'] ) );

        if ( $sender ) {
          foreach ( $allows as $allow ) {
            if ( strlen( $allow ) > 0 ) {
              if ( str_contains( strtolower( $sender ), strtolower( $allow ) ) ) {
                return true;
              }
            }
          }
        }
      } else {
        $allowed = true;
      }

      return $allowed;
    }

    /**
     * Verify the DKIM signature if required by the credentials.
     *
     * @param array $headers Email headers.
     *
     * @return bool Falso if we should reject the message.
     */
    private function verify_dkim_signature( $headers ) {
      if ( array_key_exists( 'dkim', $this->credentials ) && $this->credentials['dkim'] ) {
        return Pop_Email::verify_dkim_signature( $headers );
      } else {
        return true;
      }
    }
  }
}
