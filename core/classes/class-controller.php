<?php

namespace Post_From_Email {

  use Exception;

  class Controller extends \WP_REST_Controller {
    /**
     * @var mixed|string
     */
    protected $namespace;
    private int $version;
    private string $base;

    public function init() {
      $this->version   = '1';
      $this->namespace = POST_FROM_EMAIL_SLUG;
      $this->base      = 'upload';
      $this->add_hooks();
    }

    /**
     * @param \WP_REST_Request $req
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function post( \WP_REST_Request $req ) {
      error_log( 'entered post method' );
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      $timestamp = time();
      $upload    = $req->get_json_params();

      $uploadCategoryId = $this->maybeMakeCategory( 'Email',
        'From Email',
        'from_email' );

      $tags  = [];
      $valid = is_array( $upload )
               && array_key_exists( 'headers', $upload )
               && array_key_exists( 'envelope', $upload )
               && array_key_exists( 'html', $upload );
      if ( ! $valid ) {
        return $this->error( 'Invalid object' );
      }
      try {
        $doc = new \DOMDocument( '1.0', 'utf-8' );

        $doc->preserveWhiteSpace = false;
        $doc->loadHTML( $upload['html'] );

        $title = $this->getElementContents( $doc, '/html/head/title', '' );
        if ( 0 === strlen( $title ) ) {
          $title = $upload['headers']['subject'];
        }

        $tag = $this->base32_encode( md5( $upload['headers']['message_id'] ) );

        /* Use the date from the email header if available.  */
        try {
          $post_date = new \DateTimeImmutable( $upload['headers']['date'] );
          $post_date = $post_date->getTimestamp();
        } catch ( Exception $e ) {
          $post_date = $timestamp;
        }

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
        $post       = [
          'post_author'    => 1,
          'post_excerpt'   => substr( $upload['plain'], 0, 160 ),
          'post_date'      => date( "Y-m-d\TH:i:s", $post_date ),
          'post_date_gmt'  => gmdate( "Y-m-d\TH:i:s", $post_date ),
          'post_content'   => implode( '', $content ),
          'post_title'     => $title,
          'post_status'    => 'private',   //TODO
          'post_category'  => [ $uploadCategoryId ],
          'comment_status' => 'closed',
          'ping_status'    => 'closed',
          'tags_input'     => $tags,
        ];
        $id         = wp_insert_post( $post, true, true );
        if ( is_wp_error( $id ) ) {
          return $id;
        }

        update_post_meta( $id, $meta_key, $doc->saveHTML() );
      } catch ( Exception $ex ) {
        return new \WP_Error( $ex->getMessage() );
      }

      return new \WP_REST_Response( 'OK' );
    }

    public function permission( \WP_REST_Request $req ): bool {
      if ( $req->get_method() === 'POST' ) {
        return true;
      }

      return current_user_can( 'read_private_posts' );
    }

    private function add_hooks() {

      add_action( 'rest_api_init', function () {

        register_rest_route( "$this->namespace/v$this->version", "/$this->base", [
          [
            'callback'            => [ $this, 'post' ],
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
          ],
        ] );
      } );
    }

    /**
     * @return int|mixed|string|string[]|\WP_Error|null
     */
    private function maybeMakeCategory( $category, $description, $nicename ) {
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
     * Generate a REST error message.
     *
     * @return \WP_Error
     */
    private function error( $message ) {
      status_header( 400 );
      return new \WP_Error(400, $message );
    }

    /**
     * Retrieve an element's text contents from a DOMDocument.
     *
     * @param \DOMDocument $doc The document.
     * @param string       $doc_path The xpath of the desired element.
     * @param string       $default The default value if the element isn't found.
     *
     * @return mixed
     */
    private function getElementContents( \DOMDocument $doc, $doc_path, $default = 'unknown' ) {
      $result = $default;
      try {
        $xpath = new \DOMXPath( $doc );
        $els   = $xpath->query( $doc_path );
        foreach ( $els as $el ) {
          $result = $el->textContent;
        }
      } catch ( Exception $ex ) {
        /* empty, intentionally */
      }

      return $result;
    }

    /**
     * Encode a string in base32
     *
     * @param string $data A string containing only hex digits: output of md5()
     *
     * @return string
     */
    private function base32_encode( $data ) {
      $data  = hex2bin( $data );
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
  }
}
