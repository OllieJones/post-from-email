<?php

namespace Post_From_Email {

  use Generator;
  use WP_Error;
  use WP_Post;
  use WP_Query;
  use WP_REST_Controller;
  use WP_REST_Request;
  use WP_REST_Response;

  class Controller extends WP_REST_Controller {
    /**
     * @var mixed|string
     */
    protected $namespace;
    private $version;
    private $upload_endpoint_name;
    private $test_credentials_endpoint_name;
    private $urlpost_endpoint_name;

    /**
     * Initial setup for REST API endpoints.
     *
     * @return void
     */
    public function init() {
      $this->version                        = '1';
      $this->namespace                      = POST_FROM_EMAIL_SLUG;
      $this->upload_endpoint_name           = 'upload';
      $this->test_credentials_endpoint_name = 'test-credentials';
      $this->urlpost_endpoint_name          = 'urlpost';
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
      $upload            = $req->get_json_params();
      $chosen_profile_id = $req->get_param( 'profile_id' );
      $chosen_profile_id = is_numeric( $chosen_profile_id ) ? (int) $chosen_profile_id : null;

      foreach ( $this->get_active_mailboxes( $chosen_profile_id ) as $profile => $credentials ) {
        /** @var WP_POST $profile */
        if ( null !== $chosen_profile_id && $chosen_profile_id !== $profile->ID ) {
          continue;
        }
        $post                   = new Make_Post( $upload, $profile, $credentials );
        $post->log_item->source = 'webhook';
        $validity               = $post->check();
        $response               = true;
        if ( true === $validity ) {
          $response = $post->process();
          if ( is_wp_error( $response ) ) {
            $validity = array( Make_Post::POST_CREATION_FAILURE => $response->get_error_message() );
          }
        } else {
          $post->log_item->valid = 0;
          $post->log_item->store();
        }
        if ( true === $validity ) {
          return $response;
        }
      }

      $err = new WP_Error();
      if ( WP_DEBUG ) {
        /* Careful: error messages here can leak back to unauthorized senders */
        $err->add( 400, 'no webhook-enabled profiles', array( 'status' => 400 ) );
      } else {
        $err->add( 401, '', array( 'status' => 401 ) );
      }

      return $err;
    }

    /**
     * Handle mail-server request credential from browser.
     *
     * @param WP_REST_Request $req
     *
     * @return WP_REST_Response
     */
    public function test_credentials( WP_REST_Request $req ) {
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-pop-email.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/util.php';
      $creds = (array) json_decode( $req->get_body() );

      $popper = new Pop_Email();
      $creds  = sanitize_credentials( $creds );
      if ( 'never' !== $creds['timing'] ) {
        $result = $popper->login( $creds );
        /* Don't localize 'OK' -- Javascript depends on it. */
        $result =
          true === $result ? 'OK ' . esc_html__( 'Succeeded. Publish or Update to connect.', 'post-from-email' ) : $result;
      } else {
        /* Don't localize 'OK' -- Javascript depends on it. */
        $result = 'OK ' . esc_html__( 'Mailbox disabled. Enable it or use a webhook.', 'post-from-email' );
      }

      return new WP_REST_Response( $result );
    }

    /**
     * Handle url post request from server.
     *
     * @param WP_REST_Request $req
     *
     * @return WP_REST_Response|WP_Error
     */
    public function urlpost( WP_REST_Request $req ) {
      require_once ABSPATH . 'wp-admin/includes/admin.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-pop-email.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-make-post.php';
      require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/util.php';
      $params = (array) json_decode( $req->get_body() );

      $url           = empty( $params['url'] ) ? '' : $params['url'];
      $url           = is_string( $url ) ? $url : '';
      $sanitized_url = sanitize_url( $url, array( 'https', 'http' ) );
      if ( 0 === strlen( $sanitized_url ) ) {
        $mesg = esc_html__( 'Invalid URL:', 'post-from-email' ) . ' ' . esc_html( $url );

        return new WP_REST_Response( $mesg );
      }
      /* Did we get an optional profile_id in the request? */
      $profile_id = empty( $params['profile_id'] ) ? null : (int) $params['profile_id'];

      foreach ( $this->get_active_mailboxes( $profile_id ) as $profile => $credentials ) {
        /** @var WP_POST $profile */
        if ( null !== $profile_id && $profile_id !== $profile->ID ) {
          continue;
        }

        $response = cached_safe_remote_get( $url );
        $mesg     = null;
        $code     = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
          if ( is_wp_error( $response ) ) {
            $mesg = $response->get_error_code() . ': ' . $response->get_error_message();
          } else {
            $mesg = $code . ': ' . wp_remote_retrieve_response_message( $response );
          }
        }
        if ( $mesg ) {
          return new WP_REST_Response( $mesg );
        }
        $fetched = $response['body'];

        $upload                 = array( 'html' => $fetched );
        $post                   = new Make_Post( $upload, $profile, $credentials );
        $post->log_item->source = 'urlpost';
        $response               = $post->process();
        if ( is_wp_error( $response ) ) {
          $post->log_item->valid = 0;
          $post->log_item->store();

          return new WP_REST_Response( $response->get_error_message() );
        }

        return new WP_Rest_Response ( 'OK ' . esc_html__( 'Post created.', 'post-from-email' ) );
      }

      $err = new WP_Error();
      if ( WP_DEBUG ) {
        /* Careful: error messages here can leak back to unauthorized senders */
        $err->add( 400, 'no webhook-enabled profiles', array( 'status' => 400 ) );
      } else {
        $err->add( 401, '', array( 'status' => 401 ) );
      }

      return $err;
    }

    public function user_can_create( WP_REST_Request $req ) {
      return current_user_can( 'create_posts' ) || current_user_can( 'publish_posts' );
    }

    /**
     * __construct helper function.
     *
     * @return void
     */
    private function add_hooks() {

      add_action( 'rest_api_init', function () {

        /* POST the content of a message, from CloudMailin or other similar services, to any profile.
         * POST https://example.com/wp-json/post-from-email/v1/upload/12345      */
        $route = '/' . $this->upload_endpoint_name . '/(?P<profile_id>\d+)';
        register_rest_route( "$this->namespace/v$this->version", $route, array(
          array(
            'callback'            => array( $this, 'post' ),
            'methods'             => 'POST',
            'permission_callback' => array( $this, 'user_can_create' ),
            'args'                => array(
              'profile_id' => array(
                'validate_callback' => function ( $profile_id, $request, $key ) {
                  if ( ! is_numeric( $profile_id ) ) {
                    return false;
                  }
                  $profile_id = (int) $profile_id;
                  $found      = false;
                  foreach ( $this->get_active_mailboxes( $profile_id ) as $profile => $credentials ) {
                    /** @var WP_POST $profile */
                    if ( $profile->ID === $profile_id ) {
                      $found = true;
                    }
                  }

                  return $found;
                },
              ),
            ),
          ),
        ) );

        /* POST the content of a message, from CloudMailin or other similar services, without specifying the profile.
         * POST https://example.com/wp-json/post-from-email/v1/upload      */
        $route = '/' . $this->upload_endpoint_name;
        register_rest_route( "$this->namespace/v$this->version", $route, array(
          array(
            'callback'            => array( $this, 'post' ),
            'methods'             => 'POST',
            'permission_callback' => array( $this, 'user_can_create' ),
          ),
        ) );

        /* POST https://example.com/wp-json/post-from-email/v1/test-credentials */
        register_rest_route( "$this->namespace/v$this->version", "/$this->test_credentials_endpoint_name", array(
          array(
            'callback'            => array( $this, 'test_credentials' ),
            'methods'             => 'POST',
            'permission_callback' => array( $this, 'user_can_create' ),
          ),
        ) );

        /* POST https://example.com/wp-json/post-from-email/v1/urlpost */
        register_rest_route( "$this->namespace/v$this->version", "/$this->urlpost_endpoint_name", array(
          array(
            'callback'            => array( $this, 'urlpost' ),
            'methods'             => 'POST',
            'permission_callback' => array( $this, 'user_can_create' ),
          ),
        ) );
      } );
    }

    /**
     * Encapsulate the WP_Query to get a usable profile.
     *
     * @param int|null $profile_id The chosen profile ID.
     *
     * @return Generator
     */
    private function get_active_mailboxes( $profile_id = null ) {
      $args     = array(
        'post_type' => POST_FROM_EMAIL_PROFILE,
        'status'    => array( 'publish', 'private' ),
      );
      $profiles = new WP_Query( $args );
      try {
        if ( $profiles->have_posts() ) {
          while ( $profiles->have_posts() ) {
            $profiles->the_post();

            $profile     = get_post();
            $credentials = get_post_meta( $profile->ID, POST_FROM_EMAIL_SLUG . '_credentials', true );
            if ( ( is_array( $credentials ) && 'allow' === $credentials['webhook'] ) || ( is_numeric( $profile_id ) ) && $profile_id === $profile->ID ) {
              yield $profile => $credentials;
            }
          }
        }
      } finally {
        wp_reset_postdata();
      }
    }

  }
}
