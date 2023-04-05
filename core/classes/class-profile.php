<?php

namespace Post_From_Email {

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
   $this->popper = new Pop_Email();
   add_action( 'init', [ $this, 'register_post_type' ] );
   add_action( 'edit_post' . '_' . POST_FROM_EMAIL_PROFILE, [ $this, 'save_profile_credentials' ], 10, 3 );
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
      'insert_into_item'         => __( 'Insert into template', 'post-from-email' ),
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

   add_meta_box(
    'credentials',
    __( 'Your dedicated mailbox\'s settings', 'post-from-email' ),
    array( $this, 'credentials_meta_box' ),
    null,
    'advanced', /* advanced|normal|side */
    'high',
    array()
   );

   add_meta_box(
    'filter',
    __( 'Your incoming message filter settings', 'post-from-email' ),
    array( $this, 'filter_meta_box' ),
    null,
    'advanced', /* advanced|normal|side */
    'default',
    array()
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
    <input type="hidden" name="credentials[type]" value="<?php echo esc_attr( $credentials['type'] ) ?>"/>
    <input type="hidden" name="credentials[folder]" value="<?php echo esc_attr( $credentials['folder'] ) ?>"/>
    <input type="hidden" name="credentials[posted]" value="<?php echo esc_attr( time() ) ?>"/>
    <?php wp_nonce_field( 'wp_rest', 'credentialnonce', false ) ?>
    <table class="credentials">
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
       <select id="port" name="credentials[port]"> <?php echo $options ?></select>
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
        <option value="0" <?php echo 'delete' === $credentials['disposition'] ? 'selected' : '' ?>>
         <?php esc_html_e( 'Remove from POP server after posting', 'post-from-email' ) ?>
        </option>
        <option value="1" <?php echo 'delete' !== $credentials['disposition'] ? 'selected' : '' ?>>
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
        <span id="credential-spinner" class="spinner" style="float:none;"></span>
        <input type="button" id="test" value="<?php esc_attr_e( 'Test', 'post-from-email' ) ?>">
        <div class="clear"></div>
       </div>
      </td>
      <td colspan="2">
       <?php
       if ( isset ( $credentials['status'] ) && true === $credentials['status'] ) {
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
   * HTML for the second metabox, allowlist and DKIM.
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

   $credentials = get_post_meta( $post->ID, POST_FROM_EMAIL_SLUG . '_credentials', true );
   if ( ! is_array( $credentials ) ) {
    $credentials = Pop_Email::$template_credentials;
   }

   $credentials = Pop_Email::sanitize_credentials( $credentials );

   $allowlist  = Pop_Email::sanitize_email_list( $credentials['allowlist'] );
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
   * Fires once a post has been saved.
   *
   * The dynamic portion of the hook name, `$post->post_type`, refers to
   * the post type slug.
   *
   * @param int     $post_ID Post ID.
   * @param WP_Post $post Post object.
   *
   * @since 3.7.0
   *
   */

  public function save_profile_credentials( $post_ID, $post ) {

   if ( isset ( $_POST['action'] ) && 'editpost' !== $_POST['action'] ) {
    return;
   }
   if ( POST_FROM_EMAIL_PROFILE !== $post->post_type ) {
    return;
   }
   if ( ! $post_ID ) {
    return;
   }
   if ( ! isset ( $_POST['credentials'] ) || ! is_array( $_POST['credentials'] ) ) {
    return;
   }

   $credentials = $_POST['credentials'];

   $credentials           = Pop_Email::sanitize_credentials( $credentials );
   $result                = $this->popper->login( $credentials );
   $credentials['status'] = $result;
   update_post_meta( $post_ID, POST_FROM_EMAIL_SLUG . '_credentials', $credentials );
   if ( true === $result ) {
    $this->popper->close();
    Pop_Email::check_mailboxes( 1, $post_ID );
   }
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

   //$settings['textarea_rows'] = 10;
   //$settings['editor_height'] = 150;

   return $settings;
  }

  private function credentials_help_box() {
   ?>
   <div data-target="credentials" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Mailbox Settings', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Get this information from your email service provider.' ) ?></p>
    <hr/>

    <p>
     <?php esc_html_e( 'You can post to your site by sending messages to a dedicated email address.', 'post-from-email' ) ?>
     <?php esc_html_e( 'To do this you need a dedicated mailbox on a convenient email server.', 'post-from-email' ) ?>
     <?php esc_html_e( '(Most hosting services let you create mailboxes.)', 'post-from-email' ) ?>
     <?php esc_html_e( 'When you set up that mailbox, the email provider gives you this information.', 'post-from-email' ) ?>
     <?php esc_html_e( 'Enter it here.', 'post-from-email' ) ?>
     <?php esc_html_e( 'Then, your site will check email regularly, and create posts from the messages it finds.', 'post-from-email' ) ?>

    </p>
    <p>
     <?php esc_html_e( 'Put your dedicated mailbox\'s address on the distribution list for your listserv or email marketing service\'s distribution list.', 'post-from-email' ) ?>
     <?php esc_html_e( '(Constant Contact and Mailchimp are popular marketing services.)', 'post-from-email' ) ?>
    </p>
    <p>
     <?php esc_html_e( 'To retrieve posts from your email messages, enter the maibox\'s  account information here.', 'post-from-email' ) ?>
     <?php esc_html_e( 'Your retrieved posts inherit the categories, tags, and author you set for this template.', 'post-from-email' ) ?>
    </p>
   </div>
   <?php
  }

  private function filter_help_box() {
   ?>
   <div data-target="filter" class="dialog popup help-popup hidden"
        title="<?php esc_html_e( 'Message Filter Settings', 'post-from-email' ) ?>">
    <p><?php esc_html_e( 'Prevent spammers from creating unwanted posts on your site.' ) ?></p>
    <hr/>

    <p>
     <?php esc_html_e( 'To only accept messages from certain senders, enter their email addresses, one per line, in the Allowed Senders box.', 'post-from-email' ) ?>
     <?php esc_html_e( 'If you receive messages from two different senders in one mailbox, you can create two different templates like this one.', 'post-from-email' ) ?>
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

 }
}
