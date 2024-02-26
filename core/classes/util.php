<?php

namespace Post_From_Email;

use WP_Error;

/**
 * Fetch a template for mailbox credentials.
 *
 * @return array
 */
function get_template_credentials() {
  return array(
    'type'        => 'pop',
    'address'     => '',
    'host'        => '',
    'port'        => 995,
    'user'        => '',
    'pass'        => '',
    'ssl'         => true,
    'dkim'        => true,
    'allowlist'   => "",
    'folder'      => 'INBOX',
    'disposition' => 'delete', // TODO debugging. In production should be 'delete' or missing entirely.
    'debug'       => false,   // TODO debugging. In production should be false or missing entirely.
  );
}

/**
 * Retrieve the possible ports for a connection.
 *
 * @param array $credentials Credentials array.
 *
 * @return int[]|null The list of allowed ports.
 */
function get_possible_ports( $credentials ) {
  if ( 'pop' === $credentials['type'] ) {
    return array( 110, 143, 993, 995, 1110, 2221 );
  }

  return null;
}

/**
 * Encode a string in base32.
 *
 * This string is suitable for a filename, cross-platform.
 *
 * @param string $data A string containing only hex digits: output of md5()
 *
 * @return string
 */
function base32_encode( $data ) {
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
 * Sanitize the email server credential array.
 *
 * @param array $credentials The array to sanitize
 *
 * @return array The sanitized array.
 */
function sanitize_credentials( $credentials ) {

  $credentials = is_array( $credentials ) ? $credentials : get_template_credentials();
  if ( empty ( $credentials['timing'] ) || ! is_string( $credentials['timing'] ) ) {
    $credentials['timing'] = 'never';
  }
  $schedules    = array_keys( wp_get_schedules() );
  $schedules [] = 'never';
  if ( ! in_array( $credentials['timing'], $schedules ) ) {
    $credentials['timing'] = 'never';
  }

  if ( empty ( $credentials['disposition'] ) || ! is_string( $credentials['disposition'] ) ) {
    $credentials['disposition'] = 'delete';
  } else {
    /* This setting should be 'keep' or 'delete' */
    $credentials['disposition'] = 'delete' === $credentials['disposition'] ? 'delete' : 'keep';
  }

  if ( empty ( $credentials['webhook'] ) || ! is_string( $credentials['webhook'] ) ) {
    $credentials['webhook'] = 'deny';
  } else {
    /* This setting should be 'allow' or 'deny' */
    $credentials['webhook'] = 'allow' === $credentials['webhook'] ? 'allow' : 'deny';
  }

  $credentials['host'] = sanitize_hostname( $credentials['host'] );
  $credentials['port'] = intval( $credentials['port'] );

  $credentials['address'] = sanitize_email( $credentials['address'] );
  $credentials['user']    = sanitize_username( $credentials ['user'] );
  /* Cope with the quirkiness of unchecked checkboxes */
  if ( isset( $credentials['posted'] ) ) {
    unset ( $credentials['posted'] );
    $credentials['ssl'] = false;
    if ( isset( $credentials['ssl_checked'] ) && 'on' === $credentials['ssl_checked'] ) {
      $credentials['ssl'] = true;
      unset ( $credentials['ssl_checked'] );
    }
    $credentials['dkim'] = false;
    if ( isset( $credentials['dkim_checked'] ) && 'on' === $credentials['dkim_checked'] ) {
      $credentials['dkim'] = true;
      unset ( $credentials['dkim_checked'] );
    }
  }
  /* These are Boolean */
  $credentials['ssl']  = ! ! ( isset ( $credentials['ssl'] ) && $credentials['ssl'] );
  $credentials['dkim'] = ! ! ( isset ( $credentials['dkim'] ) && $credentials['dkim'] );

  $credentials['allowlist'] = sanitize_email_list( $credentials['allowlist'] );

  $allowed_ports = get_possible_ports( $credentials );
  if ( ! in_array( $credentials['port'], $allowed_ports, true ) ) {
    $credentials['port'] = 0;
  }
  if ( ! isset ( $credentials['folder'] ) || ! is_string( $credentials['folder'] ) || strlen( $credentials['folder'] ) <= 0 ) {
    $credentials['folder'] = 'INBOX';
  }

  return $credentials;
}

/**
 * Sanitize a hostname.
 *
 * @param string $hostname A hostname (fully-qualified domain name).
 *
 * @return string|null The same hostname, or null if it contains invalid characters.
 */
function sanitize_hostname( $hostname ) {
  if ( ! is_string( $hostname ) || ( 0 === strlen( $hostname ) ) ) {
    return '';
  }
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
function sanitize_username( $username ) {
  if ( ! is_string( $username ) ) {
    return '';
  }
  if ( false === strpos( $username, '@' ) ) {
    if ( preg_match( '/^[a-zA-Z0-9][-.a-zA-Z0-9]*[a-zA-Z0-9]?/', $username ) ) {
      return $username;
    }
  } else {
    return sanitize_email( $username );
  }

  return '';
}

/**
 * Sanitize a list of email addresses, one per line delimited by newlines.
 *
 * @param string $list The list.
 *
 * @return string The sanitized list.
 */
function sanitize_email_list( $list ) {
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
 * Get a remote object, cached.
 *
 * @param       $url
 * @param array $args
 *
 * @return array|mixed|WP_Error
 * @see wp_safe_remote_get()
 *
 */
function cached_safe_remote_get( $url, $args = array() ) {
  $slug = substr( POST_FROM_EMAIL_SLUG . '-ob-' . $url, 0, 160 );
  /* TODO Cached to avoid hammering origin server ? */
  $response = get_transient( $slug );
  if ( false !== $response ) {
    return $response;
  }

  $response = wp_safe_remote_get( $url, $args );
  if ( ! empty ( $response['http_response'] ) ) {
    unset ( $response['http_response'] );
  }
  set_transient( $slug, $response, WEEK_IN_SECONDS * 2 );

  return $response;
}
