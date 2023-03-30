<?php

namespace Post_From_Email {

 /**
  * Profile (post_type 'post_from_email_prof') is a custom post type for email source profiles.
  */
 class Profile {

  /**
   * @var Pop_Email An email popper instance
   */
  private $popper;

  public function __construct() {
   $this->popper = new Pop_Email();
   add_action( 'init', [ $this, 'register_post_type' ] );
   add_action( 'edit_post' . '_' . POST_FROM_EMAIL_PROFILE, [ $this, 'save_profile_credentials' ], 10, 3 );
   add_action( 'admin_init', function () {
    add_action( 'edit_form_after_title', [ $this, 'explanatory_header' ] );
    add_filter( 'wp_editor_settings', [ $this, 'editor_settings' ], 10, 2 );
   } );
  }

  public function register_post_type() {
   register_post_type(
    POST_FROM_EMAIL_PROFILE,
    array(
     'name'                 => __( 'Template for retrieving posts from eamil', 'post-from-email' ),
     'description'          => __( 'Templates for retrieving posts from email', 'post-from-email' ),
     'labels'               => array(
      'name'                     => _x( 'Posts from email', 'post type general name', 'post-from-email' ),
      'singular_name'            => _x( 'Template for retrieving posts from email', 'post type singular name', 'post-from-email' ),
      'add_new'                  => _x( 'Add New', 'post from email', 'post-from-email' ),
      'add_new_item'             => __( 'Add new template for posts from email', 'post-from-email' ),
      'new_item'                 => __( 'New template for posts from email', 'post-from-email' ),
      'edit_item'                => __( 'Edit template for posts from email', 'post-from-email' ),
      'view_item'                => __( 'View template for posts from email', 'post-from-email' ),
      'all_items'                => __( 'All templates', 'post-from-email' ),
      'search_items'             => __( 'Search templates for posts from email', 'post-from-email' ),
      'parent_item_colon'        => __( ':', 'post-from-email' ),
      'not_found'                => __( 'No templates found. Create one.', 'post-from-email' ),
      'not_found_in_trash'       => __( 'No templaces found in Trash.', 'post-from-email' ),
      'archives'                 => __( 'Archive of templates for posts from email', 'post-from-email' ),
      'insert_into_item'         => __( 'Insert into Profile', 'post-from-email' ),
      'uploaded_to_this_item'    => __( 'Uploaded to this template', 'post-from-email' ),
      'filter_items_list'        => __( 'Filter template list', 'post-from-email' ),
      'items_list_navigation'    => __( 'Template list navigation', 'post-from-email' ),
      'items_list'               => __( 'Template list', 'post-from-email' ),
      'item_published'           => __( 'Templace activated', 'post-from-email' ),
      'item_published_privately' => __( 'Private template activated', 'post-from-email' ),
      'item_reverted_to_draft'   => __( 'Template deactivated', 'post-from-email' ),
      'item_scheduled'           => __( 'Template scheduled for activation', 'post-from-email' ),
      'item_update'              => __( 'Templace updated', 'post-from-email' ),

     ),
     'public'               => true, //TODO
     'hierarchical'         => false,
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

  public function make_meta_boxes( $post ) {

   add_meta_box(
    'credentials',
    __( 'Access settings for your dedicated mailbox', 'post-from-email' ),
    array( $this, 'credentials_meta_box' ),
    null,
    'advanced', /* advanced|normal|side */
    'high',
    array()
   );

   add_meta_box(
    'filter',
    __( 'Filter incoming messages', 'post-from-email' ),
    array( $this, 'filter_meta_box' ),
    null,
    'advanced', /* advanced|normal|side */
    'default',
    array()
   );

   remove_meta_box( 'generate_layout_options_meta_box', null, 'side' );
  }

  public function credentials_meta_box( $post, $args ) {

   wp_enqueue_style( 'profile-editor',
    POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/profile-editor.css',
    [],
    POST_FROM_EMAIL_VERSION );

   $credentials = get_post_meta( $post->ID, POST_FROM_EMAIL_SLUG . '_credentials', true );
   if ( ! $credentials ) {
    $credentials = Pop_Email::$template_credentials;
   }

   $credentials = Pop_Email::sanitize_credentials( $credentials );

   if ( 'pop' === $credentials ['type'] ) {
    $possible_ports = Pop_Email::get_possible_ports( $credentials );
    $options        = array();
    $options []     = '
     <option value="">' . esc_html__( '--Choose--', 'post-from-email' ) . '</option>';
    foreach ( $possible_ports as $possible_port ) {
     $selected   = $credentials['port'] === $possible_port ? 'selected' : '';
     $options [] = "
     <option value='$possible_port' $selected='true'>" . esc_html( $possible_port ) . '</option>';
    }
    $options = implode( PHP_EOL, $options );
    ?>
    <p><?php esc_html_e( 'When you set up your mailbox, your email hosting provider gives you this information.' ) ?></p>
    <hr/>
    <table class="credentials">
     <tr>
      <td>
       <label for="email"><?php esc_html_e( 'Email address', 'post-from-email' ) ?>:</label>
      </td>
      <td colspan="2" class="cred">
       <input type="email" id="user" name="credentials[email]"
              value="<?php esc_attr_e( $credentials['address'] ); ?>"
       >
      </td>
     </tr>
     <tr>
      <td>
       <label for="user"><?php esc_html_e( 'Username', 'post-from-email' ) ?>:</label>
      </td>
      <td colspan="2" class="cred">
       <input type="text" id="user" name="credentials[user]"
              value="<?php esc_attr_e( $credentials['user'] ); ?>"
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
       <select id="port" name="credentials[port]"> <?php echo $options ?></select>
      </td>
     </tr>
     <tr>
      <td>
       <input type="checkbox" id="ssl-checked"
              name="credentials[ssl_checked]" <?php echo $credentials['ssl'] ? 'checked' : '' ?>>
      </td>
      <td colspan="2">
       <label
        for="ssl-checked"><?php esc_html_e( 'Always use a secure connection (SSL) when retrieving mail', 'post-from-email' ) ?></label>
      </td>
     </tr>
    </table>
    <?php
   } else {
    esc_html_e( 'This mailbox access protocol is not supported', 'post-from-email' );
    echo ':' . esc_html__( $credentials ['type'] );
   }
  }

  public function filter_meta_box( $post, $args ) {

   wp_enqueue_style( 'profile-editor',
    POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/profile-editor.css',
    [],
    POST_FROM_EMAIL_VERSION );

   $credentials = get_post_meta( $post->ID, POST_FROM_EMAIL_SLUG . '_credentials', true );
   if ( ! $credentials ) {
    $credentials = Pop_Email::$template_credentials;
   }

   $credentials = Pop_Email::sanitize_credentials( $credentials );

   $allowlist  = Pop_Email::sanitize_email_list( $credentials['allowlist'] );
   $allowcount = min( 10, max( 4, count( explode( "\n", $allowlist ) ) + 1 ) );

   ?>
   <p><?php esc_html_e( 'These filters help prevent spammers from abusing your mailbox to generate unwanted posts.' ) ?></p>
   <hr/>
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
      <?php esc_html_e( 'Enter the addresses of email senders you trust to post to your site, one per line.' ); ?>
     </td>
    </tr>
    <tr>
     <td>
      <input type="checkbox" id="dkim-checked"
             name="credentials[dkim_checked]" <?php echo $credentials['dkim'] ? 'checked' : '' ?>>
     </td>
     <td colspan="2">
      <label
       for="dkim-checked"><?php esc_html_e( 'Ignore posts unless signed by the sender to prove they\'re not spam (DKIM validation)', 'post-from-email' ) ?></label>
     </td>
    </tr>
   </table>
   <?php
  }

  /**
   * Fires once a post has been saved.
   *
   * The dynamic portion of the hook name, `$post->post_type`, refers to
   * the post type slug.
   *
   * @param int      $post_ID Post ID.
   * @param \WP_Post $post Post object.
   *
   * @since 3.7.0
   *
   */

  public function save_profile_credentials( $post_ID, $post ) {

   if ( is_array( $_POST['data'] )
        && array_key_exists( 'action', $_POST['data'] )
        && 'heartbeat' === $_POST['data']['action'] ) {
    return;
   }
   $foo = $post_ID;
  }

  /**
   * Filter the post-updated messaes
   *
   * @param array $todo Array of message strings
   *
   * @return array
   */
  public function filter_post_updated_messages( $todo ) {
   //TODO
   return $todo;
  }

  /**
   * Display explanatory information at the top of the profile editing form.
   *
   * @return void
   */
  public function explanatory_header() {
   global $post;

   if ( empty ( $post ) || POST_FROM_EMAIL_PROFILE !== get_post_type( $post ) ) {
    return;
   }
   ?>
   <h3><?php esc_html_e( 'This is a template for retrieving posts from email.', 'post-from-email' ) ?> </h3>
   <p>
    <?php esc_html_e( 'Post to your site by sending email messages to a dedicated mailbox.', 'post-from-email' ) ?>
    <?php esc_html_e( 'To do this you need a dedicated mailbox on a convenient email server.', 'post-from-email' ) ?>
    <?php esc_html_e( '(Many hosting services let you create mailboxes.)', 'post-from-email' ) ?>
    <?php esc_html_e( 'Then, put that mailbox on your listserv or email marketing service\'s distribution list.', 'post-from-email' ) ?>
    <?php esc_html_e( '(Constant Contact and Mailchimp are popular services.)', 'post-from-email' ) ?>
   </p>
   <p>
    <?php esc_html_e( 'To retrieve posts from your email messages, enter the maibox\'s  account information here.', 'post-from-email' ) ?>
    <?php esc_html_e( 'Your retrieved posts inherit the categories, tags, and author you set for this template.', 'post-from-email' ) ?>
   </p>
   <p>
    <?php esc_html_e( 'Create one of these templates for each dedicated mailbox you use for posting.', 'post-from-email' ) ?>
   </p>
   <p><?php
    echo esc_html(
     sprintf(
     /* translators: 1: the name of the shortcode */
      __( 'Don\'t forget to use the [%1$s] shortcode.', 'post-from-email' ),
      POST_FROM_EMAIL_SLUG
     ) ) ?> </p>
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

   //$settings['textarea_rows'] = 10;
   //$settings['editor_height'] = 150;

   return $settings;
  }

 }
}
