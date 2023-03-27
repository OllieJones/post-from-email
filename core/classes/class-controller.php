<?php

namespace Post_From_Email {

  use WP_Error;
  use WP_REST_Controller;
  use WP_REST_Request;
  use WP_REST_Response;

  class Controller extends WP_REST_Controller {
    /**
     * @var mixed|string
     */
    protected $namespace;
    private $version;
    private $base;

    public function init() {
      $this->version   = '1';
      $this->namespace = POST_FROM_EMAIL_SLUG;
      $this->base      = 'upload';
      $this->add_hooks();
    }

    /**
     * @param WP_REST_Request $req
     *
     * @return WP_REST_Response|WP_Error
     */
    public function post( WP_REST_Request $req ) {
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-make-post.php';
      $upload    = $req->get_json_params();

      $make = new Make_Post();
      return $make->process( $upload );
    }

    public function permission( WP_REST_Request $req ) {
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

  }
}
