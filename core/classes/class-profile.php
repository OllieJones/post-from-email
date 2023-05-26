<?php

namespace Post_From_Email;

use WP_Post;

/**
 * Profile (post_type 'post_from_email_prof') is a custom post type for email source profiles.
 */
class Profile {

  /**
   * @var Pop_Email An email popper instance
   */
  private $popper;

  public function __construct() {
    require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/util.php';

    $this->popper = new Pop_Email();
    add_action( 'init', [ $this, 'register_post_type' ] );
    add_action( 'edit_post' . '_' . POST_FROM_EMAIL_PROFILE, [ $this, 'save_profile_credentials' ], 10, 3 );
    add_action( 'admin_menu', [ $this, 'remove_taxonomy_menus' ] );
    add_action( 'admin_init', function () {
      add_action( 'edit_form_after_title', [ $this, 'explanatory_header' ] );
      add_filter( 'wp_editor_settings', [ $this, 'editor_settings' ], 10, 2 );
    } );
  }

  /**
   * Register our custom post type. post_from_email_prof .
   *
   * The user interface sometimes calls this a "template" and sometimes a "profile"
   *
   * @return void
   *
   */
  public function register_post_type() {
    /** @noinspection SqlNoDataSourceInspection */
    register_post_type(
      POST_FROM_EMAIL_PROFILE,
      array(
        'name'                 => __( 'Template for retrieving posts from email', 'post-from-email' ),
        'description'          => __( 'Templates for retrieving posts from email', 'post-from-email' ),
        'labels'               => array(
          'menu_name'                => _x( 'Posts from email', 'post type menu name', 'post-from-email' ),
          'name'                     => _x( 'Templates for retrieving posts from email', 'post type general name', 'post-from-email' ),
          'singular_name'            => _x( 'Template for retrieving posts from email', 'post type singular name', 'post-from-email' ),
          'add_new'                  => _x( 'Add New', 'post from email', 'post-from-email' ),
          'add_new_item'             => __( 'Add new template for posts from email', 'post-from-email' ),
          'new_item'                 => __( 'New template for posts from email', 'post-from-email' ),
          'edit_item'                => __( 'Edit template for posts from email', 'post-from-email' ),
          'view_item'                => __( 'View template for posts from email', 'post-from-email' ),
          'all_items'                => __( 'All templates', 'post-from-email' ),
          'search_items'             => __( 'Search templates', 'post-from-email' ),
          'parent_item_colon'        => __( ':', 'post-from-email' ),
          'not_found'                => __( 'No templates found. Create one.', 'post-from-email' ),
          'not_found_in_trash'       => __( 'No templaces found in Trash.', 'post-from-email' ),
          'archives'                 => __( 'Archive of templates for posts from email', 'post-from-email' ),
          'insert_into_item'         => __( 'Insert into template', 'post-from-email' ),
          'uploaded_to_this_item'    => __( 'Uploaded to this template', 'post-from-email' ),
          'filter_items_list'        => __( 'Filter template list', 'post-from-email' ),
          'items_list_navigation'    => __( 'Template list navigation', 'post-from-email' ),
          'items_list'               => __( 'Template list', 'post-from-email' ),
          'item_published'           => __( 'Template activated', 'post-from-email' ),
          'item_published_privately' => __( 'Private template activated', 'post-from-email' ),
          'item_reverted_to_draft'   => __( 'Template deactivated', 'post-from-email' ),
          'item_scheduled'           => __( 'Template scheduled for activation', 'post-from-email' ),
          'item_update'              => __( 'Templace updated', 'post-from-email' ),

        ),
        'hierarchical'         => false,
        'public'               => true, //TODO not sure how these visibility parameters interact.
        'exclude_from_search'  => true,
        'publicly_queryable'   => false,
        'show_ui'              => true,
        'show_in_menu'         => true, //TODO change this to put ui in submenu
        'show_in_nav_menus'    => false,
        'show_in_admin_bar'    => false,
        'show_in_rest'         => false, /* No block editor support */
        'menu_position'        => 90,
        'menu_icon'            => 'dashicons-email-alt2',
        'map_meta_cap'         => true,
        'supports'             => array(
          'title',
          'editor',
          'revisions',
          'author',
          'custom_fields',
        ),
        'taxonomies'           => array( 'category', 'post_tag' ),
        'register_meta_box_cb' => [ $this, 'make_meta_boxes' ],
        'has_archive'          => true,
        'rewrite'              => array( 'slug' => POST_FROM_EMAIL_PROFILE ),
        'query_var'            => POST_FROM_EMAIL_PROFILE,
        'can_export'           => true,
        'delete_with_user'     => false,
        'template'             => array(),

      )
    );
  }

  /**
   * Handle the username/password and filters meta boxes.
   *
   * @param $post
   *
   * @return void
   */
  public function make_meta_boxes( $post ) {

    $credentials = $this->get_credentials( $post );

    add_meta_box(
      'credentials',
      __( 'Your dedicated mailbox\'s settings', 'post-from-email' ),
      array( $this, 'credentials_meta_box' ),
      null,
      'advanced', /* advanced|normal|side */
      'high',
      $credentials
    );

    add_meta_box(
      'urlbox',
      __( 'Create a post from a web page', 'post-from-email' ),
      array( $this, 'url_meta_box' ),
      null,
      'advanced', /* advanced|normal|side */
      'default',
      $credentials
    );

    add_meta_box(
      'webhook',
      __( 'Your incoming webhook settings', 'post-from-email' ),
      array( $this, 'webhook_meta_box' ),
      null,
      'advanced', /* advanced|normal|side */
      'default',
      $credentials
    );

    add_meta_box(
      'filter',
      __( 'Your incoming message filter settings', 'post-from-email' ),
      array( $this, 'filter_meta_box' ),
      null,
      'advanced', /* advanced|normal|side */
      'default',
      $credentials
    );

    add_meta_box(
      'postlog',
      __( 'Activity', 'post-from-email' ),
      array( $this, 'postlog_meta_box' ),
      null,
      'advanced', /* advanced|normal|side */
      'default',
      $credentials
    );

    remove_meta_box( 'generate_layout_options_meta_box', null, 'side' );
  }

  /**
   * HTML for the first metabox, usernam, password, all that.
   *
   * @param WP_Post  $post current post.
   * @param          $callback_args
   *
   * @return void
   */
  public function credentials_meta_box( $post, $callback_args ) {

    $credentials = $callback_args['args'];

    wp_enqueue_style( 'jquery-ui-theme',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/jquery-ui.min.css',
      [],
      POST_FROM_EMAIL_VERSION );

    wp_enqueue_script( 'jquery-ui-dialog' );

    wp_enqueue_style( 'profile-editor',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/profile-editor.css',
      [],
      POST_FROM_EMAIL_VERSION );

    $this->credentials_help_box();
    $this->submitdiv_help_box();
    $this->categorydiv_help_box();
    $this->tagsdiv_help_box();
    $this->authordiv_help_box();

    if ( 'pop' === $credentials ['type'] ) {

      /* Timing choice dropdown */
      $timings_options    = array();
      $timings_options [] = '<option value="">' . esc_attr__( '--Choose--', 'post-from-email' ) . '</option>';
      $selected           = $credentials['timing'] === 'never' ? 'selected' : '';
      $timings_options [] =
        '<option value="never"' . $selected . ' >' . esc_attr__( 'Never (Disabled)', 'post-from-email' ) . '</option>';
      $schedules          = wp_get_schedules();
      uasort( $schedules, function ( $a, $b ) {
        return intval( $a['interval'] ) - intval( $b['interval'] );
      } );
      foreach ( $schedules as $key => $schedule ) {
        $selected           = $credentials['timing'] === $key ? 'selected' : '';
        $timings_options [] =
          '<option value="' . esc_attr( $key ) . '"  ' . $selected . ' >' . esc_html( $schedule['display'] ) . '</option>';
      }

      /* Port choice dropdown */
      $possible_ports  = get_possible_ports( $credentials );
      $port_options    = array();
      $port_options [] = '<option value="">' . esc_attr__( '--Choose--', 'post-from-email' ) . '</option>';
      foreach ( $possible_ports as $possible_port ) {
        $possible_port   = intval( $possible_port );
        $selected        = intval( $credentials['port'] ) === $possible_port ? 'selected' : '';
        $port_options [] = "
     <option value='$possible_port' $selected>" . $possible_port . '</option>';
      }
      $port_options = implode( PHP_EOL, $port_options );
      ?>
     <input type="hidden" name="credentials[type]" value="<?php echo esc_attr( $credentials['type'] ) ?>"/>
     <input type="hidden" name="credentials[folder]" value="<?php echo esc_attr( $credentials['folder'] ) ?>"/>
     <input type="hidden" name="credentials[posted]" value="<?php echo esc_attr( time() ) ?>"/>
      <?php wp_nonce_field( 'wp_rest', 'credentialnonce', false ) ?>
     <table class="credentials">
      <tr>
       <td>
        <label for="timing"><?php esc_html_e( 'Check email', 'post-from-email' ) ?>:</label>
       </td>
       <td colspan="2" class="cred">
        <select id="timing" name="credentials[timing]"> <?php echo implode( PHP_EOL, $timings_options ) ?></select>
       </td>
      </tr>
      <tr>
       <td>
        <label for="address"><?php esc_html_e( 'Email address', 'post-from-email' ) ?>:</label>
       </td>
       <td colspan="2" class="cred">
        <input type="email" id="address" name="credentials[address]"
               value="<?php echo esc_attr( $credentials['address'] ); ?>"
        >
       </td>
      </tr>
      <tr>
       <td>
        <label for="user"><?php esc_html_e( 'Username', 'post-from-email' ) ?>:</label>
       </td>
       <td colspan="2" class="cred">
        <input type="text" id="user" name="credentials[user]"
               value="<?php echo esc_attr( $credentials['user'] ); ?>"
        >
       </td>
      </tr>
      <tr>
       <td>
        <label for="pass"><?php esc_html_e( 'Password', 'post-from-email' ) ?>:</label>
       </td>
       <td colspan="2" class="cred">
        <input type="password" id="pass" name="credentials[pass]"
               placeholder="<?php esc_html_e( 'Password', 'post-from-email' ) ?>"
               value="<?php echo esc_attr( $credentials['pass'] ); ?>"
        >
       </td>
      </tr>
      <tr>
       <td>
        <label for="host"><?php esc_html_e( 'POP Server', 'post-from-email' ) ?>:</label>
       </td>
       <td>
        <input type="text" id="host" name="credentials[host]"
               placeholder="<?php esc_html_e( 'POP3 server host name', 'post-from-email' ) ?>"
               value="<?php echo esc_attr( $credentials['host'] ); ?>"
        >
       </td>
       <td>
        <label for="port"><?php esc_html_e( 'Port', 'post-from-email' ) ?></label>
        <select id="port" name="credentials[port]"> <?php echo $port_options ?></select>
       </td>
      </tr>

      <tr>
       <td>
        <label for="ssl-checked"><?php esc_html_e( 'Connection', 'post-from-email' ) ?>:</label>
       </td>
       <td>
        <input type="checkbox" id="ssl-checked"
               name="credentials[ssl_checked]" <?php echo $credentials['ssl'] ? 'checked' : '' ?>>
        <label
         for="ssl-checked"><?php esc_html_e( 'Use a secure connection (SSL)', 'post-from-email' ) ?></label>
       </td>
      </tr>

      <tr>
       <td>
        <label for="leave_emails"><?php esc_html_e( 'Messages', 'post-from-email' ) ?>:</label>
       </td>
       <td colspan="2">
        <select id="leave_emails" name="credentials[disposition]">
         <option value="delete" <?php echo 'delete' === $credentials['disposition'] ? 'selected' : '' ?>>
           <?php esc_html_e( 'Remove from POP server after posting', 'post-from-email' ) ?>
         </option>
         <option value="keep" <?php echo 'delete' !== $credentials['disposition'] ? 'selected' : '' ?>>
           <?php esc_html_e( 'Leave on server (troubleshooting only)', 'post-from-email' ) ?>
         </option>
        </select>
       </td>
      </tr>

      <tr>
       <td colspan="3">
        <hr/>
       </td>
      </tr>
      <tr>
       <td class="test_button">
        <div>
         <span id="credential_spinner" class="spinner" style="float:none;"></span>
         <input type="button" id="test"
                data-endpoint="<?php echo site_url( '/wp-json/post-from-email/v1/test-credentials' );?>"
                value="<?php esc_attr_e( 'Test', 'post-from-email' ) ?>">
         <div class="clear"></div>
        </div>
       </td>
       <td colspan="2">
         <?php
         if ( 'never' === $credentials['timing'] ) {
           echo '<div id="status_message" class="success">' . esc_html__( 'Mailbox disabled. Enable it or use a webhook.', 'post-from-email' ) . '</div>';
         } elseif ( isset ( $credentials['status'] ) && true === $credentials['status'] ) {
           echo '<div id="status_message" class="success">' . esc_html__( 'Connected', 'post-from-email' ) . '</div>';
         } elseif ( isset ( $credentials['status'] ) && is_string( $credentials['status'] ) ) {
           echo '<div id="status_message" class="failure">';
           $msg   = [];
           $lines = explode( "\n", $credentials['status'] );
           foreach ( $lines as $line ) {
             $msg [] = esc_html( $line );
           }
           echo implode( '<br/>', $msg );
           echo '</div>';
         } else {
           echo '<div id="status_message" class="unknown"></div>';
         }
         ?>
       </td>
      </tr>
     </table>
      <?php
    } else {
      esc_html_e( 'This mailbox access protocol is not supported', 'post-from-email' );
      echo ':' . esc_html__( $credentials ['type'] );
    }
  }

  /**
   * HTML for the third metabox, allowlist and DKIM.
   *
   * @param WP_Post $post current post.
   * @param array   $callback_args Args given to make_meta_box()
   *
   * @return void
   */
  public function filter_meta_box( $post, $callback_args ) {

    wp_enqueue_style( 'profile-editor',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/profile-editor.css',
      [],
      POST_FROM_EMAIL_VERSION );

    $this->filter_help_box();

    $credentials = $callback_args['args'];

    $allowlist  = sanitize_email_list( $credentials['allowlist'] );
    $allowcount = min( 10, max( 4, count( explode( "\n", $allowlist ) ) + 1 ) );

    ?>
   <table class="credentials">

    <tr>
     <td>
      <label for="allowlist"><?php esc_html_e( 'Allowed senders', 'post-from-email' ) ?>:</label>
     </td>
     <td>
      <textarea id="allowlist"
                rows="<?php echo $allowcount ?>"
                name="credentials[allowlist]"
                placeholder="<?php esc_attr_e( 'Posts allowed from any sender', 'post-from-email' ); ?>"
      ><?php echo $credentials['allowlist'] ?></textarea>
     </td>
    </tr>
    <tr>
     <td></td>
     <td>
       <?php esc_html_e( 'Addresses of trusted email senders, one per line.' ); ?>
     </td>
    </tr>
    <tr>
     <td>
      <input type="checkbox" id="dkim-checked"
             name="credentials[dkim_checked]" <?php echo $credentials['dkim'] ? 'checked' : '' ?>>
     </td>
     <td colspan="2">
      <label
       for="dkim-checked"><?php esc_html_e( 'Ignore unsigned messages', 'post-from-email' ) ?></label>
     </td>
    </tr>
   </table>
    <?php
  }

  /**
   * HTML for the fourth metabox, activity log.
   *
   * @param WP_Post $post current post.
   * @param array   $callback_args Args given to make_meta_box()
   *
   * @return void
   */
  public function postlog_meta_box( $post, $callback_args ) {
    require_once POST_FROM_EMAIL_PLUGIN_DIR . '/core/classes/class-log-post.php';

    wp_enqueue_style( 'profile-editor',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/profile-editor.css',
      [],
      POST_FROM_EMAIL_VERSION );

    $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
    $this->postlog_help_box();

    ?>
   <table class="postlog">
    <tbody>

    <?php
    foreach ( Log_Post::get( $post->ID ) as $item ) {
      /** @var Log_Post $item */
      $datestamp = get_date_from_gmt( date( 'Y-m-d H:i:s', $item->time ), $date_format );

      $message = is_array( $item->errors ) && count( $item->errors ) >= 1 ? $item->errors[0]['message'] : '';
      switch ( $item->valid ) {
        case 1:
          $class   = 'good';
          $subject = $item->subject;
          $from    = $item->from;
          $icon    =
            $item->signed == 1 ? '<span class="dashicons dashicons-privacy"></span>' : '<span class="dashicons dashicons-yes-alt"></span>';
          break;
        case 0:
          $class   = 'bad';
          $subject = null;
          $from    = $message;
          $icon    = '<span class="dashicons dashicons-warning"></span>';
          break;
        case - 1:
          $class   = 'nothing';
          $subject = null;
          $from    = $message;
          $icon    = '<span class="dashicons dashicons-marker"></span>';
          break;
        default:
          $class   = '';
          $subject = null;
          $from    = '';
          $icon    = '<span class="dashicons dashicons-flag"></span>';
          break;
      }

      ?>
     <tr class="<?php echo esc_attr( $class ) ?>">
      <td>
        <?php echo wp_kses( $icon, 'post' ) ?>
      </td>
      <td>
        <?php echo esc_html( $datestamp ); ?>
      </td>
      <td>
        <?php echo esc_html( $item->source ); ?>
      </td>
       <?php
       if ( $subject ) {
         ?>
        <td> <?php echo esc_html( $from ); ?> </td>
        <td>
          <?php
          if ( $item->post_id > 0 ) {
            $link = get_permalink( $item->post_id );
            if ( is_string( $link ) && strlen( $link ) > 0 ) {
              echo '<a href="' . esc_attr( $link ) . '" style="text-decoration: none;"><span class="dashicons dashicons-admin-post"></span></a>';
            }
          }
          ?>
        </td>
        <td> <?php echo esc_html( $subject ); ?> </td>
         <?php
       } else {
         ?>
        <td colspan="3"> <?php echo esc_html( $from ); ?> </td>
         <?php
       }
       ?>

     </tr>
      <?php
    } ?>
    </tbody>
   </table>
    <?php
  }

  /**
   * HTML for the webhook meta box.
   *
   * @param WP_Post $post current post.
   * @param array   $callback_args Args given to make_meta_box()
   *
   * @return void
   */
  public function webhook_meta_box( $post, $callback_args ) {

    $this->webhook_help_box( $post );

    $credentials = $callback_args['args'];

    ?>
   <table class="webhook">

    <tr>
     <td>
      <label for="webhookallowed"><?php esc_html_e( 'Posting from webhook', 'post-from-email' ) ?>:</label>
     </td>
     <td colspan="2">
      <select id="webhookallowed" name="credentials[webhook]">
       <option value="deny" <?php echo 'deny' !== $credentials['webhook'] ? 'selected' : '' ?>>
         <?php esc_html_e( 'Disabled', 'post-from-email' ) ?>
       </option>
       <option value="allow" <?php echo 'allow' === $credentials['webhook'] ? 'selected' : '' ?>>
         <?php esc_html_e( 'Enabled', 'post-from-email' ) ?>
       </option>
      </select>
     </td>
    </tr>
   </table>
    <?php
  }

  /**
   * HTML for the post-from-URL metabox.
   *
   * @param WP_Post $post current post.
   * @param array   $callback_args Args given to make_meta_box()
   *
   * @return void
   */
  public function url_meta_box( $post, $callback_args ) {

    $this->url_help_box();

    ?>
   <table class="urlbox">
    <tbody>
    <?php
    if ( is_numeric( $post->ID ) ) {
      ?>
     <tr>
      <td>
       <label for="urlpost_url"><?php esc_html_e( 'Post from URL', 'post-from-email' ) ?>:</label>
      </td>
      <td class="url">
       <input type="url" id="urlpost_url" value="" size="40" placeholder="https://">
      </td>
     </tr>

     <tr>
      <td class="urlpost_button">
       <div>
        <span id="urlpost_spinner" class="spinner" style="float:none;"></span>
        <input type="button"
               id="urlpost_button"
               data-endpoint="<?php echo site_url('/wp-json/post-from-email/v1/urlpost')?>"
               data-profile_id="<?php echo (int) $post->ID ?>"
               value="<?php esc_attr_e( 'Create Post', 'post-from-email' ) ?>">
        <div class="clear"></div>
       </div>
      </td>
     <tr>
      <td colspan="2">
       <div id="url_message" class="unknown"></div>
      </td>
     </tr>
      <?php
    } else {
      ?>
     <tr>
      <td>
        <?php esc_html_e( 'Post from URL', 'post-from-email' ) ?>:
      </td>
      <td class="url">
       <span> <?php esc_html_e( 'Publish before posting from a URL.', 'post-from-email' ) ?> </span>
      </td>
     </tr>
      <?php
    }
    ?>
    </tbody>

   </table>
    <?php
  }

  /**
   * Fires once a post has been saved.
   *
   * The dynamic portion of the hook name, `$post->post_type`, refers to
   * the post type slug.
   *
   * @param int     $profile_id Post ID.
   * @param WP_Post $profile Post object.
   *
   * @since 3.7.0
   *
   */

  public function save_profile_credentials( $profile_id, $profile ) {

    if ( isset ( $_POST['action'] ) && 'editpost' !== $_POST['action'] ) {
      return;
    }
    if ( POST_FROM_EMAIL_PROFILE !== $profile->post_type ) {
      return;
    }
    if ( ! $profile_id ) {
      return;
    }
    if ( ! isset ( $_POST['credentials'] ) || ! is_array( $_POST['credentials'] ) ) {
      return;
    }

    $credentials     = sanitize_credentials( $_POST['credentials'] );
    $old_credentials =
      sanitize_credentials( get_post_meta( $profile_id, POST_FROM_EMAIL_SLUG . '_credentials', true ) );

    if ( $old_credentials['timing'] !== $credentials['timing'] ) {
      Main::unschedule_mailbox_check( $profile_id );
    }
    /* Check email once on post unless timing is set to 'never' */
    if ( 'never' !== $credentials['timing'] ) {
      $result                = $this->popper->login( $credentials );
      $credentials['status'] = $result;
      $this->put_credentials( $profile_id, $credentials );
      if ( true === $result ) {
        $this->popper->close();
        if ( 'publish' === $profile->post_status || 'private' === $profile->post_status ) {
          Pop_Email::check_mailboxes( 10, $profile_id );
        }
      }
    } else {
      $this->put_credentials( $profile_id, $credentials );
    }
  }

  /**
   * Display explanatory text at the top of the profile editing form.
   *
   * @return void
   */
  public function explanatory_header() {
    global $post;

    if ( empty ( $post ) || POST_FROM_EMAIL_PROFILE !== get_post_type( $post ) ) {
      return;
    }
    ?>
   <h3><?php esc_html_e( 'This template retrieves posts from email.', 'post-from-email' ) ?> </h3>
   <p>
     <?php esc_html_e( 'Create a template for each dedicated mailbox you use for posting.', 'post-from-email' ) ?>
     <?php esc_html_e( 'Posts created from email messages are assigned the template\'s attributes (visibility, categories, tags, and author).', 'post-from-email' ) ?>
     <?php esc_html_e( 'Learn more by clicking each attributes\'s', 'post-from-email' ) ?>
    <span class="dashicons dashicons-editor-help"></span>
     <?php esc_html_e( 'icon.', 'post-from-email' ) ?>
   </p>
    <?php
  }

  /**
   * Filters the wp_editor() settings.
   *
   * @param array  $settings Array of editor arguments.
   * @param string $editor_id Unique editor identifier, e.g. 'content'. Accepts 'classic-block'
   *                          when called from block editor's Classic block.
   *
   * @since 4.0.0
   *
   * @see _WP_Editors::parse_settings()
   *
   */
  public function editor_settings( $settings, $editor_id ) {
    global $post;

    if ( 'content' !== $editor_id || empty ( $post ) || POST_FROM_EMAIL_PROFILE !== get_post_type( $post ) ) {
      return $settings;
    }

    /* This is a botch. It looks like tiny mce ignores its window-size settings, so we'll just horse 'em with Javascript */
    wp_enqueue_script( 'profile-editor',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/js/profile-editor.js',
      [],
      POST_FROM_EMAIL_VERSION );

    return $settings;
  }

  /**
   * The custom post type menu comes with taxonomy-editing items. Remove them.
   *
   * Our profile (template) custom post type uses the same categories and tags as ordinary posts,
   * so we don't need the menus repeated.
   *
   * @return void
   */

  public function remove_taxonomy_menus() {
    global $submenu;
    $edit_page = 'edit.php?post_type=' . POST_FROM_EMAIL_PROFILE;
    $slugs     = [ 'category', 'post_tag' ];
    if ( is_array( $submenu[ $edit_page ] ) ) {
      foreach ( $submenu[ $edit_page ] as $k => $sub ) {
        $params = array();
        parse_str( wp_parse_url( $sub[2], PHP_URL_QUERY ), $params );
        if ( ! empty ( $params['taxonomy'] ) ) {
          if ( in_array( $params['taxonomy'], $slugs ) ) {
            unset( $submenu[ $edit_page ][ $k ] );
          }
        }
      }
    }
  }

  /**
   * Retrieve complete URL from parsed URL.
   *
   * @param array $parsed_url
   *
   * @return string
   */
  private function get_url( $parsed_url ) {
    $scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
    $host     = $parsed_url['host'] ?? '';
    $port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
    $user     = $parsed_url['user'] ?? '';
    $pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
    $pass     = ( $user || $pass ) ? "$pass@" : '';
    $path     = $parsed_url['path'] ?? '';
    $query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
    $fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

    return "$scheme$user$pass$host$port$path$query$fragment";
  }

  /**
   * Render the help box for credentials.
   *
   * @return void
   */
  private function credentials_help_box() {
    ?>
   <div data-target="credentials" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Mailbox Settings', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Get these settings from your email service provider.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'You can post to your site by sending messages to a dedicated email address.', 'post-from-email' ) ?>
      <?php esc_html_e( 'To do this you need a dedicated mailbox on a convenient email server.', 'post-from-email' ) ?>
      <?php esc_html_e( '(Most hosting services let you create mailboxes.)', 'post-from-email' ) ?>
      <?php esc_html_e( 'When you create that mailbox, the email provider gives you these settings.', 'post-from-email' ) ?>
      <?php esc_html_e( 'Enter them here.', 'post-from-email' ) ?>
      <?php esc_html_e( 'Then, your site will check email regularly, and create posts from the messages it finds.', 'post-from-email' ) ?>

    </p>
    <p>
      <?php esc_html_e( 'Put your dedicated mailbox\'s address on the distribution list for your listserv or email marketing service\'s distribution list.', 'post-from-email' ) ?>
      <?php esc_html_e( '(Constant Contact and Mailchimp are popular marketing services.)', 'post-from-email' ) ?>
    </p>
    <p>
      <?php esc_html_e( 'To retrieve posts from your email messages, enter the maibox\'s account settings here.', 'post-from-email' ) ?>
      <?php esc_html_e( 'Your retrieved posts inherit the categories, tags, and author you set for this template.', 'post-from-email' ) ?>
    </p>
    <p>
      <?php esc_html_e( 'If you use an email-to-webhook service, you don\'t need to provide mailbox account settings.', 'post-from-email' ) ?>
      <?php echo esc_htmL__( 'Just set', 'post-from-email' )
                 . ' <em>' . esc_html__( 'Check email', 'post-from-email' ) . '</em> '
                 . esc_html__( 'to', 'post-from-email' )
                 . ' <em>' . esc_html__( 'Never (Disabled)', 'post-from-email' ) . '</em>.' ?>
    </p>
     <?php
     if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
       ?>
      <p>
        <?php esc_html_e( 'On your site WP-Cron is disabled:  DISABLE_WP_CRON is set to true in your wp_config.php file.', 'post-from-email' ) ?>
        <?php esc_html_e( 'Be sure to schedule a System Cron Job to check your email.', 'post-from-email' ) ?>

      </p>
       <?php
     }
     ?>
   </div>
    <?php
  }

  /**
   * Render the help box for the filter panel.
   *
   * @return void
   */
  private function filter_help_box() {
    ?>
   <div data-target="filter" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Message Filter Settings', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Prevent spammers from creating unwanted posts on your site.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'To only accept messages from certain senders, enter their email addresses, one per line, in the Allowed Senders box.', 'post-from-email' ) ?>
      <?php esc_html_e( 'If you receive messages from two different senders in one mailbox, and you want their posts to have different categories, tags, or authors, you can create two different templates like this one.', 'post-from-email' ) ?>
      <?php esc_html_e( 'Put just one sender\'s address in each template.', 'post-from-email' ) ?>
      <?php esc_html_e( 'If you do this you can apply a diferent template, with different categories and tags, to each sender.', 'post-from-email' ) ?>
    </p>
    <p>
      <?php esc_html_e( 'Many email services authenticate their messages by signing them.', 'post-from-email' ) ?>
      <?php esc_html_e( 'They use a standard known as DKIM to prove the messages came from them, and not some spammer.', 'post-from-email' ) ?>
      <?php esc_html_e( 'You can check Ignore messages to reject unsigned messages.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  /**
   * Render the help box for the webhook panel
   *
   * @param WP_POST $post The pust for the current profile.
   *
   * @return void
   */
  private function webhook_help_box( $post ) {
    ?>
   <div data-target="webhook" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Webhook settings', 'post-from-email' ) ?>">
    <p autofocus><?php esc_html_e( 'Allow posting via an incoming webhook.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'You can use an email-to-webhook service to deliver messages to your server.', 'post-from-email' ) ?>
     <a href="https://CloudMailin.com/" target="_blank">CloudMailin.com</a>
      <?php esc_html_e( 'provides this service.' ) ?>
      <?php esc_html_e( 'This is a good choice if your email provider blocks incoming messages from email marketing services like Constant Contact or Mailchimp.', 'post-from-email' ) ?>

    </p>
    <p>
      <?php esc_html_e( 'Configure your email-to-webhook service to POST incoming emails to this site using the webhook URL', 'post-from-email' ) ?>
     :
    </p>
    <p style="margin-left: 1em;">
     <code><?php
       $user           = wp_get_current_user();
       $url            = get_rest_url( null, POST_FROM_EMAIL_SLUG . '/v1/upload/' . $post->ID );
       $parsed         = parse_url( $url );
       $parsed['pass'] = esc_attr__( 'PASSWORD', 'post-from-email' );
       $parsed['user'] = $user->user_login;
       echo $this->get_url( $parsed );

       ?></code>
    </p>
    <p>
      <?php esc_html_e( 'Give your username and password in the URL as shown.', 'post-from-email' ) ?>
      <?php
      /* translators: 1 WordPress login name */
      $prompt = __( 'Use your username (%s) and', 'post-from-email' );
      echo esc_html( sprintf( $prompt, $user->user_login ) );
      ?>
     <a href="<?php echo admin_url( 'profile.php#application-passwords-section' ) ?>"
        target="_blank"><?php esc_html_e( 'an application password.' ) ?></a>
      <?php esc_html_e( 'Remove the spaces from your application password before using it.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function url_help_box() {
    ?>
   <div data-target="urlbox" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Posting from a URL', 'post-from-email' ) ?>">
    <p autofocus><?php esc_html_e( 'Create a post from a URL.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'Many email marketing services offer a link in their messages saying "View as Webpage.', 'post-from-email' ) ?>
      <?php esc_html_e( 'You can create a post by pasting the URL from such a link here.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function submitdiv_help_box() {
    ?>
   <div data-target="submitdiv" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Publishing and Visibility Settings', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Control whether this template is active, and whether it creates public or private posts.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'To make this template active, and make it check for incoming email messages, Publish it.', 'post-from-email' ) ?>
      <?php esc_html_e( 'Draft and Pending Review templates are not active and do not check for messages.', 'post-from-email' ) ?>
    </p>
    <p>
      <?php esc_html_e( 'The template\'s Visibility affects the created posts. If the template is Public, the posts will be Public. If the template is Private, the posts will also be Private.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function categorydiv_help_box() {
    ?>
   <div data-target="categorydiv" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Categories', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Choose the categories for the created posts.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'The categories you choose here are applied to each created post.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function tagsdiv_help_box() {
    ?>
   <div data-target="tagsdiv-post_tag" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Tags', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Choose the tags for the created posts.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'The tags you choose here are applied to each created post.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function authordiv_help_box() {
    ?>
   <div data-target="authordiv" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Author Setting', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Choose the author for the created posts.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'The author you choose here is assigned to each created post.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  private function postlog_help_box() {
    ?>
   <div data-target="postlog" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Post from Email activity', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'View recent activity.' ) ?></p>
    <hr/>

    <p>
      <?php esc_html_e( 'This shows posts recently created from email messages. It also shows ignored email messages and the reason for ignoring them.', 'post-from-email' ) ?>
    </p>
    <p>
     <span
      class="dashicons dashicons-privacy"></span> <?php esc_html_e( ' means a message was signed by the sender.', 'post-from-email' ) ?>
    </p>
    <p>
     <span
      class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( ' means a message was not signed but still accepted.', 'post-from-email' ) ?>
    </p>
   </div>
    <?php
  }

  /**
   * Retrieve the email service creds associated with this current profile.
   *
   * @param WP_Post $post
   *
   * @return array Credentials array.
   */
  private function get_credentials( WP_Post $post ): array {
    $credentials = get_post_meta( $post->ID, POST_FROM_EMAIL_SLUG . '_credentials', true );
    if ( ! is_array( $credentials ) ) {
      $credentials = get_template_credentials();
    }

    return sanitize_credentials( $credentials );
  }

  /**
   * @param WP_Post|int $post
   * @param array       $credentials
   *
   * @return void
   */
  private function put_credentials(
    $post, array $credentials
  ) {
    $post_id = $post instanceof WP_Post ? $post->ID : $post;
    update_post_meta( $post_id, POST_FROM_EMAIL_SLUG . '_credentials', $credentials );
  }
}
