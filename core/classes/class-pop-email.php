<?php

namespace Post_From_Email {

  use Generator;
  use WP_Post;
  use WP_Query;

// Exit if accessed directly.
  if( ! defined( 'ABSPATH' ) ) {
    exit;
  }


  /**
   * Class Pop_Email
   *
   * Retrieve messages from a POP server
   *
   * @package    POST_FROM_EMAIL
   * @subpackage  Classes/Pop_Email
   * @author    Ollie Jones
   */
  class Pop_Email
  {
    static $template_credentials;
    public $connection;
    public $credentials;

    public function __construct()
    {
      self::$template_credentials = array(
        'type' => 'pop',
        'address' => '',
        'host' => '',
        'port' => 995,
        'user' => '',
        'pass' => '',
        'ssl' => true,
        'dkim' => true,
        'allowlist' => "",
        'folder' => 'INBOX',
        'disposition' => 'delete', // TODO debugging. In production should be 'delete' or missing entirely.
        'debug' => false,   // TODO debugging. In production should be false or missing entirely.
      );
    }

    /**
     * Cronjob to check the registered mailboxes.
     *
     * @param int $batchsize The number of messages to process per registered mailbox in each run.
     *
     * @return void
     */
    public static function check_mailboxes( $batchsize = 10, $profile_id = null )
    {

      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/util.php';

      foreach( self::get_active_mailboxes() as $profile => $credentials ) {
        /** @var WP_POST $profile */
        if( null !== $profile_id && $profile_id !== $profile->ID ) {
          /* If we're just doing one profile, skip the others. */
          continue;
        }
        require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-log-post.php';
        $log = new Log_Post( $profile->ID );
        $popper = new Pop_Email();

        if( $credentials && is_string( $credentials['host'] ) ) {
          $log->source = $credentials['host'];
        }
        if( ! $credentials || ! isset ( $credentials['user'] ) ) {
          $log->valid = 0;
          $log->errors [] = array( 'code' => 0, 'message' => __( 'No username' ), 'post-from-email' );
          $log->store();
          continue;
        }
        $login = $popper->login( $credentials );
        if( true !== $login ) {
          $log->valid = 0;
          $log->errors [] = array( 'code' => 0, 'message' => __( 'Email server login failure:' ), 'post-from-email' );
          $log->store();
          continue;
        }
        try {
          $processed = 0;
          $count = $batchsize;
          foreach( $popper->fetch_all() as $email ) {
            $processed ++;
            require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-make-post.php';
            $post = new Make_Post( $email, $profile, $credentials );
            try {
              $validity = $post->check();
              if( true === $validity ) {
                $result = $post->process();
                if( ! is_wp_error( $result ) ) {
                  $popper->dele( $email['msgno'] );
                }
              } else {
                $post->log_item->valid = 0;
                $post->log_item->store();
              }
              /* Stop when batch size reached. */
              if( 0 === $count -- ) {
                break;
              }
            } finally {
              unset ( $post );
            }
          }
          if( 0 === $processed ) {
            $log->valid = - 1;
            $error =
              array(
                'code' => 0,
                'message' => __( 'No new messages found when checking mailbox.', 'post-from-email' ),
              );
            $log->errors [] = $error;
            $log->store();
          }
        } finally {
          $popper->close();
          unset ( $popper );
        }
      }
    }

    /**
     * Check for the existence of the DKIM signature
     *
     * @param array $headers The email message headers.
     *
     * @return bool
     *
     */
    public static function check_dkim_signature_exists( $headers )
    {
      return array_key_exists( 'dkim_signature', $headers );
    }

    /**
     * Verify the DKIM signature
     *
     * @param array $headers The email message headers.
     *
     * @return bool
     * @todo Do this right. https://github.com/pimlie/php-dkim/blob/master/DKIM/Verify.php
     * @todo Do this as part of message fetch, not later.
     */
    public static function verify_dkim_signature( $headers )
    {
      return true;
    }

    /**
     * Encapsulate the WP_Query to get mailbox profiles.
     * @return Generator
     */
    public static function get_active_mailboxes()
    {
      $args = array(
        'post_type' => POST_FROM_EMAIL_PROFILE,
        'status' => array( 'publish', 'private' ),
        'orderby' => 'none',
        'nopaging' => true,
        'ignore_sticky_posts' => true,
      );
      $profiles = new WP_Query( $args );
      try {
        $posts = $profiles->get_posts();
        foreach( $posts as $profile ) {
          $credentials =
            sanitize_credentials( get_post_meta( $profile->ID, POST_FROM_EMAIL_SLUG . '_credentials', true ) );
          if( is_array( $credentials )
            && 'never' !== $credentials['timing']
            && is_string( $credentials['host'] )
            && strlen( $credentials['host'] ) > 0
          ) {
            yield $profile => $credentials;
          }
        }
      } finally {
        wp_reset_postdata();
      }
    }

    /**
     * Open a connection stream to a mailbox.
     *
     * @param array $credentials Associative array of credentials.
     *
     * @return true|string  True if the connection succeeded. The error will use multiple lines.
     */
    public function login( $credentials = null )
    {

      if( ! extension_loaded( 'imap' ) ) {
        return __( 'The imap extension to php is not installed. It is required to access your mailbox. Please contact your hosting provider.', 'post-from-email' );
      }
      $credentials = is_null( $credentials ) ? self::$template_credentials : $credentials;
      $folder = array_key_exists( 'folder', $credentials ) ? $credentials['folder'] : 'INBOX';

      if( ! isset ( $credentials['host'] ) || 0 === strlen( $credentials['host'] ) ) {
        return __( 'Please provide a POP Server hostname.', 'post-from-email' );
      }
      if( ! isset ( $credentials['user'] ) || 0 === strlen( $credentials['user'] ) ) {
        return __( 'Please provide a user name. This is often the same as the email address.', 'post-from-email' );
      }
      if( ! isset ( $credentials['pass'] ) || 0 === strlen( $credentials['pass'] ) ) {
        return __( 'Please provide a password.', 'post-from-email' );
      }

      if( 'pop' === $credentials['type'] ) {

        $flags = array();
        $flags [] = '/pop3';
        $flags [] = $credentials['ssl'] ? '/ssl/novalidate-cert' : '';
        $flags [] = isset ( $credentials['debug'] ) && $credentials['debug'] ? '/debug' : '';

        $flags = implode( '', $flags );
        $mailbox = '{' . $credentials['host'] . ':' . $credentials['port'] . $flags . '}' . $folder;
        $this->connection = @imap_open( $mailbox, $credentials['user'], $credentials['pass'], 0, 0 );

        /* We sometimes get "mailbox is empty" so-called errors */
        $result = $this->localize_imap_errors( $this->explain_map_errors( @imap_errors() ) );
        if( ! $this->connection ) {
          $result = implode( PHP_EOL, array_reverse( $result ) );
        } else {
          $result = true;
        }

        $this->credentials = $credentials;

        return $result;
      }

      return false;
    }

    /**
     * Fetch mailbox status.
     *
     * @return array
     */
    public function stat()
    {
      $check = @imap_mailboxmsginfo( $this->connection );

      return (array)$check;
    }

    /**
     * List messages.
     *
     * @param string| null $sequence Something like 1:3. Null means all messages.
     *
     * @return array Message overview records.
     */
    public function list_messages( $sequence = null )
    {
      if( ! $sequence ) {
        $MC = @imap_check( $this->connection );
        if( ! $MC ) {
          $errors = imap_errors();

          return array();
        }
        $sequence = "1:" . $MC->Nmsgs;
      }

      $messages = @imap_fetch_overview( $this->connection, $sequence );
      $result = array();
      foreach( $messages as $message ) {
        $result[ $message->msgno ] = (array)$message;
      }

      return $result;
    }

    /**
     * Fetch a message's headers.
     *
     * @param int $message_num The number of the message.
     *
     * @return false|string The message's RFC822 headers in one string.
     */
    public function fetcheader( $message_num )
    {
      return @imap_fetchheader( $this->connection, $message_num, FT_PREFETCHTEXT );
    }

    /**
     * Delete a message from the mailbox, unless 'disposition' in credentials isn't 'delete'
     *
     * @param int $message_num The number of the message.
     *
     * @return bool
     */
    public function dele( $message_num )
    {
      $disposition = isset( $this->credentials['disposition'] ) ? $this->credentials['disposition'] : 'delete';
      if( 'delete' !== $disposition ) {
        return false;
      }

      return @imap_delete( $this->connection, $message_num );
    }

    /**
     * Close the connection.
     *
     * Expunge the deleted messages, unless 'disposition' in credentials isn't 'delete'
     */
    public function close()
    {

      $expunge = true;
      if( isset ( $this->credentials['disposition'] ) && 'delete' !== $this->credentials['disposition'] ) {
        $expunge = false;
      }
      $flag = $expunge ? CL_EXPUNGE : 0;

      /* clear any remaining errors to avoid php warnings on close */
      @imap_errors();
      @imap_close( $this->connection, $flag );
    }

    /**
     * Converts a header string to an array of headers.
     *
     * @param string $header_string
     *
     * @return array Headers in an associative array ['lowercase_header_name'] => 'header_value'].
     */
    public function mail_parse_headers( $header_string )
    {
      $header_string = preg_replace( '/\r\n\s+/m', '', $header_string );
      preg_match_all( '/([^: ]+): (.+?(?:\r\n\s.+?)*)?\r\n/m', $header_string, $matches );
      $result = array();
      foreach( $matches[1] as $index => $field_name ) {
        $result[ strtolower( $field_name ) ] = $matches[2][ $index ];
      }

      return $result;
    }

    /**
     * Retrieve message contents into an array, one message part per array element
     *
     * @param int $message_num
     *
     * @return array Associative array describing message parts.
     */
    public function mail_mime_to_array( $message_num )
    {

      $mail = @imap_fetchstructure( $this->connection, $message_num );

      return $this->mail_get_parts( $message_num, $mail, 0 );
    }

    /**
     * Traverse the parts of the message, returning each one in an array.
     *
     * @param int $message_num The message number.
     * @param object $part Element of the array returned by imap_fetch_overview.
     * @param string $prefix Message part number prefix, a string with dot-separated integers according to the IMAP specification.
     *
     * @return array
     */
    public function mail_get_parts( $message_num, $part, $prefix )
    {

      $attachments = array();
      $attachments[ $prefix ] = $this->mail_decode_part( $message_num, $part, $prefix );
      if( isset( $part->parts ) ) // multipart
      {
        $prefix = ( $prefix == "0" ) ? "" : "$prefix.";
        foreach( $part->parts as $number => $subpart ) {
          $attachments =
            array_merge( $attachments, $this->mail_get_parts( $message_num, $subpart, $prefix . ( $number + 1 ) ) );
        }
      }

      return $attachments;
    }

    /**
     * Decode a message part.
     *
     * @param int $message_num The message number.
     * @param object $part Element of the array returned by imap_fetch_overview.
     * @param string $section Message part number, a string with dot-separated integers according to the IMAP specification.
     *
     * @return array
     */
    public function mail_decode_part( $message_num, $part, $section )
    {

      $attachment = array();
      if( $part->ifdparameters ) {
        foreach( $part->dparameters as $object ) {
          $attachment[ strtolower( $object->attribute ) ] = $object->value;
          if( strtolower( $object->attribute ) == 'filename' ) {
            $attachment['is_attachment'] = true;
            $attachment['filename'] = $object->value;
          }
        }
      }

      if( $part->ifparameters ) {
        foreach( $part->parameters as $object ) {
          $attachment[ strtolower( $object->attribute ) ] = $object->value;
          if( strtolower( $object->attribute ) == 'name' ) {
            $attachment['is_attachment'] = true;
            $attachment['name'] = $object->value;
          }
        }
      }

      $attachment['data'] = @imap_fetchbody( $this->connection, $message_num, $section );
      if( $part->encoding == 3 ) { // 3 = BASE64
        $attachment['data'] = @imap_base64( $attachment['data'] );
      } elseif( $part->encoding == 4 ) { // 4 = QUOTED-PRINTABLE
        $attachment['data'] = @imap_qprint( $attachment['data'] );
      }

      $subtype = strtolower( $part->subtype );
      switch( $subtype ) {
        case 'plain':
        case 'html':
          $mime = 'text/' . $subtype;
          break;
        case 'jpeg':
        case 'png':
        case 'webp':
        case 'gif':
        case 'svg':
          $mime = 'image/' . $subtype;
          break;
        case 'octet-stream':
          $mime = 'application/' . $subtype;
          break;
        default:
          $mime = null;
          break;
      }
      if( $mime ) {
        $attachment['type'] = $subtype;
        $attachment['mime'] = $mime;
      }

      return $attachment;
    }

    /**
     * Fetch all messages.
     *
     * @return Generator A sequence of objects with ->headers, ->html,  ->plain properties.
     */
    public function fetch_all()
    {
      if( $this->connection ) {
        $messages = $this->list_messages();
        foreach( $messages as $message ) {
          $result = array();
          $result['msgno'] = $message['msgno'];
          $headers = $this->fetcheader( $message['msgno'] );
          $result['headers'] = $this->mail_parse_headers( $headers );
          $parts = $this->mail_mime_to_array( $message['msgno'] );

          foreach( $parts as $part ) {
            if( isset( $part['boundary'] ) ) {
              continue;
            }
            if( isset( $part['filename'] ) ) {
              continue;
            }
            if( isset( $part['type'] ) ) {
              /* live content! */
              $result[ $part ['type'] ] = $part['data'];
            }
          }
          yield $result;
        }
      }
    }

    private function explain_map_errors( $errors )
    {
      if( ! is_array( $errors ) ) {
        return $errors;
      }
      $result = array();
      $explainers = array(
        /* translators: Explanation for the imap error 'No such host as example.com' */
        'No such host as' => esc_attr__( 'Is your POP Server correct?', 'post-from-email' ),
        /* translators: Explanation for the imap error 'TLS/SSL failure for mail.example.com: SSL negotiation failed' */
        'SSL negotiation failed' => esc_attr__( 'Should you use a secure connection? Is your Port correct?', 'post-from-email' ),
        /* translators: For the imap error 'Can't connect to example.com,1110: Connection timed out' */
        'Connection timed out' => esc_attr__( 'Is your Port correct? Is your POP Server correct?', 'post-from-email' ),
        /* translators: For the imap error 'Can not authenticate to POP3 server: [AUTH] Authentication failed.' */
        'Authentication failed' => esc_attr__( 'Are your Username and Password both correct?', 'post-from-email' ),
        /* translators: For the imap error 'Can not authenticate to POP3 server: POP3 connection broken in response' */
        'POP3 connection broken' => esc_attr__( 'Your POP Server may be temporarily overloaded. Try again later.', 'post-from-email' ),
      );
      foreach( $errors as $error ) {
        $found = false;
        foreach( $explainers as $str => $explanation ) {
          if( str_contains( $error, $str ) ) {
            $space = ' ';
            if( ! str_ends_with( $error, '.' ) ) {
              $space = '. ';
            }
            $error .= $space . $explanation;
            $found = true;
            break;
          }
        }
        if( $found ) {
          $result [] = esc_attr__( 'Connection failed', 'post-from-email' ) . '. ' . $error;
        }
      }

      return $result;
    }

    private function localize_imap_errors( $errors )
    {
      if( ! is_array( $errors ) ) {
        return $errors;
      }
      $result = array();
      $localizers = array(
        /* translators: For the imap error 'No such host as example.com' */
        'Mailbox is empty' => esc_attr__( 'Mailbox is empty', 'post-from-email' ),
        /* translators: For the imap error 'No such host as example.com' */
        'No such host as' => esc_attr__( 'No such host as', 'post-from-email' ),
        /* translators: For the imap error 'TLS/SSL failure for mail.example.com: SSL negotiation failed' */
        'TLS/SSL failure for' => esc_attr__( 'TLS/SSL failure for', 'post-from-email' ),
        /* translators: For the imap error 'TLS/SSL failure for mail.example.com: SSL negotiation failed' */
        'SSL negotiation failed' => esc_attr__( 'SSL negotiation failed', 'post-from-email' ),
        /* translators: For the imap error 'Can't connect to example.com,1110: Connection timed out' */
        'Can\'t connect to' => esc_attr__( 'Cannot connect to', 'post-from-email' ),
        /* translators: For the imap error 'Can't connect to example.com,1110: Connection timed out' */
        'Connection timed out' => esc_attr__( 'Connection timed out', 'post-from-email' ),
        /* translators: For the imap error 'Can not authenticate to POP3 server: [AUTH] Authentication failed.' */
        'Can not authenticate to POP3 server' => esc_attr__( 'Can not authenticate to POP3 server', 'post-from-email' ),
        /* translators: For the imap error 'Can not authenticate to POP3 server: [AUTH] Authentication failed.' */
        'Authentication failed' => esc_attr__( 'Authentication failed', 'post-from-email' ),
        /* translators: For the imap error 'Can not authenticate to POP3 server: POP3 connection broken in response' */
        'POP3 connection broken in response' => esc_attr__( 'POP3 connection broken in response', 'post-from-email' ),
      );
      foreach( $errors as $error ) {
        foreach( $localizers as $str => $replacement ) {
          $error = str_replace( $str, $replacement, $error );
        }
        $result [] = $error;
      }

      return $result;
    }
  }
}
