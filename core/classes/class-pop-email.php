<?php

namespace Post_From_Email {

  use Generator;

// Exit if accessed directly.
  if ( ! defined( 'ABSPATH' ) ) {
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
  class Pop_Email {
    static $default_credentials;
    static $template_credentials;
    public $connection;
    public $credentials;

    public function __construct() {
      self::$default_credentials  = array(
        'type'        => 'pop',
        'address'     => 'post-from-email@plumislandmedia.net',
        'host'        => 'mail.plumislandmedia.net',
        'port'        => 995,
        'user'        => 'post-from-email@plumislandmedia.net',
        'pass'        => 'REDACTED',  //TODO
        'ssl'         => true,
        'dkim'        => true,
        'allowlist'   => "happy@example.com\ntrusted@example.com",
        'folder'      => 'INBOX',
        'disposition' => 'save', // TODO debugging. In production should be 'delete' or missing entirely.
        'debug'       => true,   // TODO debugging. In production should be false or missing entirely.
      );
      self::$template_credentials = array(
        'type'        => 'pop',
        'address'     => 'user@example.com',
        'host'        => 'mail.example.com',
        'port'        => 995,
        'user'        => 'user@mail.example.com',
        'pass'        => 'secret',
        'ssl'         => true,
        'dkim'        => true,
        'allowlist'   => "happy@example.com\ntrusted@example.com",
        'folder'      => 'INBOX',
        'disposition' => 'delete', // TODO debugging. In production should be 'delete' or missing entirely.
        'debug'       => false,   // TODO debugging. In production should be false or missing entirely.
      );
    }

    /**
     * Sanitize a hostname.
     *
     * @param string $hostname A hostname (fully-qualified domain name).
     *
     * @return string|null The same hostname, or null if it contains invalid characters.
     */
    public static function sanitize_hostname( $hostname ) {
      $splits = explode( '.', $hostname );
      $result = array();
      foreach ( $splits as $split ) {
        if ( preg_match( '/^[a-z0-9][-a-z0-9]*[a-z0-9]?$/', $split ) ) {
          $result [] = $split;
        } else {
          return null;
        }
      }

      return implode( '.', $result );
    }

    /**
     * Sanitize a username.
     *
     * @param string $username A username
     *
     * @return string The same username, or '' if it contains invalid characters.
     */
    public static function sanitize_username( $username ) {
      if ( false == strpos( $username, '@' ) ) {
        if ( preg_match( '/^[a-zA-Z0-9][-.a-zA-Z0-9]*[a-zA-Z0-9]?/', $username ) ) {
          return $username;
        }
      } else {
        return sanitize_email( $username );
      }

      return '';
    }

    public static function sanitize_credentials( $credentials ) {

      $credentials['host'] = self::sanitize_hostname( $credentials['host'] );
      $credentials['port'] = intval( $credentials['port'] );

      $credentials['address'] = sanitize_email( $credentials['address'] );
      $credentials['user']    = self::sanitize_username( $credentials ['user'] );
      /* Coerce mixed to boolean. */
      $credentials['ssl']  = ! ! $credentials['ssl'];
      $credentials['dkim'] = ! ! $credentials['dkim'];

      $credentials['allowlist'] = self::sanitize_email_list( $credentials['allowlist'] );

      $allowed_ports = self::get_possible_ports( $credentials );
      if ( ! in_array( $credentials['port'], $allowed_ports, true ) ) {
        $credentials['port'] = 0;
      }
      if ( ! is_string( $credentials['folder'] ) || strlen( $credentials['folder'] ) <= 0 ) {
        $credentials['folder'] = 'INBOX';
      }

      return $credentials;
    }

    /**
     * Sanitize a list of email addresses, one per line delimited by newlines.
     *
     * @param string $list The list.
     *
     * @return string The sanitized list.
     */
    public static function sanitize_email_list( $list ) {
      $result = array();
      $list   = is_string( $list ) ? $list : '';
      $list   = str_replace( "\n\r", "\n", $list );
      $list   = str_replace( "\r\n", "\n", $list );
      $list   = str_replace( "\r", "\n", $list );
      $lines  = explode( "\n", $list );
      foreach ( $lines as $line ) {
        if ( strlen( $line ) > 0 ) {
          $clean = sanitize_email( $line );
          if ( strlen( $clean ) > 0 ) {
            $result [] = $clean;
          }
        }
      }

      return implode( "\n", $result );
    }

    /**
     * Retrieve the possible ports for a connection.
     *
     * @param array $credentials Credentials array.
     *
     * @return int[]|null The list of allowed ports.
     */
    public static function get_possible_ports( $credentials ) {
      if ( 'pop' === $credentials['type'] ) {
        return array( 110, 143, 993, 995, 1110, 2221 );
      }

      return null;
    }

    /**
     * Open a connection stream to a mailbox.
     *
     * @param array $credentials Associative array of credentials.
     *
     * @return true|string  True if the connection succeeded. The error will use multiple lines.
     */
    public function login( $credentials = null ) {

      if ( ! extension_loaded( 'imap' ) ) {
        return false;
      }
      $credentials = is_null( $credentials ) ? self::$default_credentials : $credentials;
      $folder      = array_key_exists( 'folder', $credentials ) ? $credentials['folder'] : 'INBOX';

      if ( 'pop' === $credentials['type'] ) {

        $flags    = array();
        $flags [] = '/pop3';
        $flags [] = $credentials['ssl'] ? '/ssl/novalidate-cert' : '';
        $flags [] = isset ( $credentials['debug'] ) && $credentials['debug'] ? '/debug' : '';

        $flags            = implode( '', $flags );
        $mailbox          = '{' . $credentials['host'] . ':' . $credentials['port'] . $flags . '}' . $folder;
        $this->connection = imap_open( $mailbox, $credentials['user'], $credentials['pass'] );

        $result = imap_errors();
        if ( $result ) {
          if ( $this->connection ) {
            /* Unknown whether this code path is necessary */
            array_unshift( $result, 'Error opening ' . $mailbox );
          } else {
            array_unshift( $result, 'Cannot open ' . $mailbox );
          }
          $result = implode( PHP_EOL, array_reverse( $result ) );
        } else {
          $result = true;
        }

        return $result;
      }

      return false;
    }

    /**
     * Fetch mailbox status.
     *
     * @return array
     */
    public function stat() {
      $check = imap_mailboxmsginfo( $this->connection );

      return (array) $check;
    }

    /**
     * List messages.
     *
     * @param string| null $sequence Something like 1:3. Null means all messages.
     *
     * @return array Message overview records.
     */
    public function list_messages( $sequence = null ) {
      if ( ! $sequence ) {
        $MC       = imap_check( $this->connection );
        $sequence = "1:" . $MC->Nmsgs;
      }

      $messages = imap_fetch_overview( $this->connection, $sequence );
      $result   = array();
      foreach ( $messages as $message ) {
        $result[ $message->msgno ] = (array) $message;
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
    public function fetcheader( $message_num ) {
      return imap_fetchheader( $this->connection, $message_num, FT_PREFETCHTEXT );
    }

    /**
     * Delete a message from the mailbox, unless 'disposition' in credentials isn't 'delete'
     *
     * @param int $message_num The number of the message.
     *
     * @return bool
     */
    public function dele( $message_num ) {
      $disposition = isset( $this->credentials['disposition'] ) ? $this->credentials['disposition'] : 'delete';
      if ( 'delete' !== $disposition ) {
        return false;
      }

      return imap_delete( $this->connection, $message_num );
    }

    /**
     * Close the connection.
     *
     * Expunge the deleted messages, unless 'disposition' in credentials isn't 'delete'
     */
    public function close() {

      $flag =
        isset ( $this->$credentials['disposition'] ) && 'delete' !== $this->$credentials['disposition'] ? CL_EXPUNGE : 0;

      imap_close( $this->connection, $flag );
    }

    /**
     * Converts a header string to an array of headers.
     *
     * @param string $header_string
     *
     * @return array Headers in an associative array ['lowercase_header_name'] => 'header_value'].
     */
    public function mail_parse_headers( $header_string ) {
      $header_string = preg_replace( '/\r\n\s+/m', '', $header_string );
      preg_match_all( '/([^: ]+): (.+?(?:\r\n\s.+?)*)?\r\n/m', $header_string, $matches );
      $result = array();
      foreach ( $matches[1] as $index => $field_name ) {
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
    public function mail_mime_to_array( $message_num ) {

      $mail = imap_fetchstructure( $this->connection, $message_num );

      $mail = $this->mail_get_parts( $message_num, $mail, 0 );

      return $mail;
    }

    /**
     * Traverse the parts of the message, returning each one in an array.
     *
     * @param int    $message_num The message number.
     * @param object $part Element of the array returned by imap_fetch_overview.
     * @param string $prefix Message part number prefix, a string with dot-separated integers according to the IMAP specification.
     *
     * @return array
     */
    public function mail_get_parts( $message_num, $part, $prefix ) {

      $attachments            = array();
      $attachments[ $prefix ] = $this->mail_decode_part( $message_num, $part, $prefix );
      if ( isset( $part->parts ) ) // multipart
      {
        $prefix = ( $prefix == "0" ) ? "" : "$prefix.";
        foreach ( $part->parts as $number => $subpart ) {
          $attachments =
            array_merge( $attachments, $this->mail_get_parts( $message_num, $subpart, $prefix . ( $number + 1 ) ) );
        }
      }

      return $attachments;
    }

    /**
     * Decode a message part.
     *
     * @param int    $message_num The message number.
     * @param object $part Element of the array returned by imap_fetch_overview.
     * @param string $section Message part number, a string with dot-separated integers according to the IMAP specification.
     *
     * @return array
     */
    public function mail_decode_part( $message_num, $part, $section ) {

      $attachment = array();
      if ( $part->ifdparameters ) {
        foreach ( $part->dparameters as $object ) {
          $attachment[ strtolower( $object->attribute ) ] = $object->value;
          if ( strtolower( $object->attribute ) == 'filename' ) {
            $attachment['is_attachment'] = true;
            $attachment['filename']      = $object->value;
          }
        }
      }

      if ( $part->ifparameters ) {
        foreach ( $part->parameters as $object ) {
          $attachment[ strtolower( $object->attribute ) ] = $object->value;
          if ( strtolower( $object->attribute ) == 'name' ) {
            $attachment['is_attachment'] = true;
            $attachment['name']          = $object->value;
          }
        }
      }

      $attachment['data'] = imap_fetchbody( $this->connection, $message_num, $section );
      if ( $part->encoding == 3 ) { // 3 = BASE64
        $attachment['data'] = imap_base64( $attachment['data'] );
      } elseif ( $part->encoding == 4 ) { // 4 = QUOTED-PRINTABLE
        $attachment['data'] = imap_qprint( $attachment['data'] );
      }

      $subtype = strtolower( $part->subtype );
      switch ( $subtype ) {
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
      if ( $mime ) {
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
    public function fetch_all() {
      if ( $this->login() ) {
        $messages = $this->list_messages();
        foreach ( $messages as $message ) {
          $result            = array();
          $headers           = $this->fetcheader( $message['msgno'] );
          $result['headers'] = $this->mail_parse_headers( $headers );
          $parts             = $this->mail_mime_to_array( $message['msgno'] );

          foreach ( $parts as $part ) {
            if ( isset( $part['boundary'] ) ) {
              continue;
            }
            if ( isset( $part['filename'] ) ) {
              continue;
            }
            if ( isset( $part['type'] ) ) {
              /* live content! */
              $result[ $part ['type'] ] = $part['data'];
            }
          }
          yield $result;
          $this->dele( $message['msgno'] );
        }
      }
    }
  }
}
