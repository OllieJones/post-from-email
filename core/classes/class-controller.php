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
    private $upload_endoint_name;
    private $test_credentials_endpoint_name;

    public function init() {
      $this->version                        = '1';
      $this->namespace                      = POST_FROM_EMAIL_SLUG;
      $this->upload_endoint_name            = 'upload';
      $this->test_credentials_endpoint_name = 'test-credentials';
      $this->add_hooks();
    }

    /**
     * Handle incoming email-to-post from CloudMailin.
     *
     * @param WP_REST_Request $req
     *
     * @return WP_REST_Response|WP_Error
     */
    public function post( WP_REST_Request $req ) {
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-make-post.php';
      $upload = $req->get_json_params();

      $make     = new Make_Post();
      $response = $make->process( $upload );

      return is_wp_error( $response ) ? $response : new WP_REST_Response( $response );
    }

    /**
     * Handle mail-server request credential from browser.
     *
     * @param WP_REST_Request $req
     *
     * @return WP_REST_Response|WP_Error
     */
    public function testcreds( WP_REST_Request $req ) {
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-pop-email.php';
      $creds = (array) json_decode( $req->get_body() );

      $popper = new Pop_Email();
      $creds  = Pop_Email::sanitize_credentials( $creds );
      $result = $popper->login( $creds );
      $result = true === $result ? esc_html__( 'Succeeded. Press Publish to connect.', 'post-from-email' ) : $result;

      return new WP_REST_Response( $result );
    }

    public function post_permission( WP_REST_Request $req ) {
      if ( $req->get_method() === 'POST' ) {
        return true;
      }

      return current_user_can( 'read_private_posts' );
    }

    private function add_hooks() {

      add_action( 'rest_api_init', function () {

        register_rest_route( "$this->namespace/v$this->version", "/$this->upload_endoint_name", [
          [
            'callback'            => [ $this, 'post' ],
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
          ],
        ] );
        register_rest_route( "$this->namespace/v$this->version", "/$this->test_credentials_endpoint_name", [
          [
            'callback'            => [ $this, 'testcreds' ],
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
          ],
        ] );
      } );
    }

  }
}
