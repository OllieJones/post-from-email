<?php

namespace Post_From_Email {

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
     * @param \WP_REST_Request $req
     *
     * @return string
     */
    function post( \WP_REST_Request $req ): string {
      error_log( 'entered post method' );
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      $upload = $req->get_json_params();

      $transient_name = POST_FROM_EMAIL_SLUG . '-' . time();
      update_option( $transient_name, json_encode($upload), false ); // TODO debug junk
      error_log( 'received post: option ' . $transient_name );

      return json_encode( (object) [] );

      $uploadCategoryId = $this->maybeMakeCategory( 'Upload',
        'Telemetry data uploaded from a WordPress site',
        'telemetry_upload' );

      $tags = [];
      try {
        $timestamp = time();
        if ( is_array( $upload ) && array_key_exists( 't', $upload ) && array_key_exists( 'data', $upload ) ) {
          /* containerized from the old telemetry site */
          $timestamp = 0 + $upload['t'];
          $payload   = $upload['data'];
        } else {
          $payload = $upload;
        }
        if ( is_array( $payload ) && array_key_exists( 'mysqlVer', $payload ) ) {
          $ver     = (object) $payload['mysqlVer'];
          $tags [] = $ver->unconstrained ? 'Barracuda' : 'Antelope';
          $tags [] = $ver->fork;
          $tags [] = "$ver->fork$ver->major.$ver->minor.$ver->build";
        }
        if ( is_array( $payload ) && array_key_exists( 'wordpress', $payload ) ) {
          $ver     = (object) $payload['wordpress'];
          $plugins = explode( '|', $ver->active_plugins );
          foreach ( $plugins as $plugin ) {
            if ( $plugin !== 'Index WP MySQL For Speed' ) {
              $tags [] = 'P:' . $plugin;
            }
          }
          $tags [] = 'WordPress ' . $ver->wp_version;
          if ( $ver->is_multisite ) {
            $tags[] = 'Multisite:' . $ver->current_blog_id;
          }
        }
      } catch ( \Exception $e ) {
        /* empty, intentionally, don't croak if data is bogus */
      }

      $title    = [];
      $title [] = $payload['id'];
      if ( is_array( $payload ) && array_key_exists( 'monitor', $payload ) ) {
        $title [] = 'Monitor';
        $title [] = $payload['monitor'];
        $tags []  = 'Monitor';
      }
      $payload = '[renderjson]' . base64_encode( json_encode( (object) $payload ) ) . '[/renderjson]';
      $post    = [
        'post_author'    => 1,
        'post_excerpt'   => implode( '|', $tags ),
        'post_date'      => date( "Y-m-d\TH:i:s", $timestamp ),
        'post_date_gmt'  => gmdate( "Y-m-d\TH:i:s", $timestamp ),
        'post_content'   => $payload,
        'post_title'     => implode( ' ', $title ),
        'post_status'    => 'private',
        'post_category'  => [ $uploadCategoryId ],
        'comment_status' => 'open',
        'ping_status'    => 'closed',
        'tags_input'     => $tags,
      ];
      wp_insert_post( $post, true, true );

      return json_encode( (object) [] );
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

    function permission( \WP_REST_Request $req ): bool {
      if ( $req->get_method() === 'POST' ) {
        return true;
      }

      return current_user_can( 'read_private_posts' );
    }

  }
}
