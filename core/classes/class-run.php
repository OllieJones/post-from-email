<?php

namespace Post_From_Email;
// Exit if accessed directly.
use DOMDocument;
use DOMException;
use DOMXPath;
use Exception;
use WP_Query;
use Less_Parser;
use Less_Exception_Parser;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Class Post_From_Email_Run
 *
 * Thats where we bring the plugin to life
 *
 * @package    POST_FROM_EMAIL
 * @subpackage  Classes/Post_From_Email_Run
 * @author    Ollie Jones
 */
class Run {

  static $scrub_list;

  /**
   * @var int The current frame serial number.
   */
  private $frame_serial_number = 0;

  private $timeout = WEEK_IN_SECONDS * 2;

  /**
   * Our Post_From_Email_Run constructor
   * to run the plugin logic.
   *
   */
  function __construct() {
    $this->add_hooks();
    if ( WP_DEBUG ) {
      /* override the two-week default when debugging */
      $this->timeout = MINUTE_IN_SECONDS * 10;
    }

    self::$scrub_list = array(
      "/html/head/meta[@property='og:image']",
      '/html/head/script',
      '/html/body//script',
      "/html/body//div[@id='tracking-image']",
      "/html/body//table[@class='footer-container']",
      "/html/body//img[@width='1'][@height='1']",
    );
  }

  /** Generate
   *
   * @param string $date the already-generated date (ignored)
   *
   * @return string the date string we generate. It must end with a space for correct formatting.
   */
  public function post_date_and_time( string $date ) {

    $isodate  = esc_attr( get_the_date( 'c' ) );
    $textdate = esc_html( get_the_date() . ' ' . get_the_time() );
    $fmt      =
      '<span class="posted-on"><time class="entry-date published" datetime="%1$s" itemprop="datePublished">%2$s</time></span> ';

    return sprintf( $fmt, $isodate, $textdate );
  }

  /**
   * /**
   * The [post-from-email] shortcode.
   *
   * Renders an embedded HTML email
   *
   * @param array       $atts Shortcode attributes. Default empty.
   * @param string|null $content Shortcode content. Default null.
   * @param string      $tag Shortcode tag (name). Default empty.
   *
   * @return string Shortcode output.
   */
  public function embed( array $atts = [], string $content = null, string $tag = '' ) {

    $html = get_post_meta( get_the_ID(), $atts['meta_tag'], true );
    $doc  = $this->get_document_from_html( $html );

    return $this->extract_body( $doc );

    wp_enqueue_style( 'post-from-email',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/css/post-from-email.css',
      [],
      POST_FROM_EMAIL_VERSION );
    wp_enqueue_script( 'iframe-resizer',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/js/iframeResizer.min.js',
      [],
      POST_FROM_EMAIL_VERSION,
      false );
    wp_enqueue_script( 'post-from-email',
      POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/js/post-from-email.js',
      [],
      POST_FROM_EMAIL_VERSION,
      false );

    $file_url = esc_url( $this->get_file_url( $atts ) );

    $frameid = 'frame' . $this->frame_serial_number ++;

    return "<iframe id='" . $frameid . "' class='post-from-email' style='overflow: hidden; height: 100%;
        width: 100%;' src='$file_url' ></iframe>";
  }

  /**
   * Registers all WordPress and plugin related hooks
   *
   * @access  private
   * @return  void
   */
  private function add_hooks() {

    add_shortcode( POST_FROM_EMAIL_SLUG, [ $this, 'embed' ] );

    add_filter( 'generate_post_date_output', [ $this, 'post_date_and_time' ], 15, 2 );
  }

  /**
   * Convert a url, or a filename of the form f-conta-cc-AbC123.html, to a file leaf name of that form.
   *
   * @param string $url
   *
   * @return string
   */
  private function get_filename( $url ) {
    if ( str_starts_with( $url, 'f-' ) && str_ends_with( $url, '.html' ) ) {
      return $url;
    }
    $parsed = wp_parse_url( $url );
    $file   = 'f-' . sanitize_key( $parsed['PHP_URL_HOST'] . '-' . $parsed['PHP_URL_PATH'] ) . '.html';

    return $file;
  }

  private function get_file_url( $atts ): string {
    $name = empty( $atts['src'] ) ? POST_FROM_EMAIL_SLUG . get_the_ID() . '.html' : $this->get_filename( $atts['src'] );
    $path = get_transient( POST_FROM_EMAIL_SLUG . '-file-' . $name );
    if ( $path ) {
      return $path;
    }
    $path = empty( $atts['src'] )
      ? $this->load_meta_to_iframeable_file( $atts, $name )
      : $this->load_url_to_iframeable_file( $atts['src'], $name );
    set_transient( POST_FROM_EMAIL_SLUG . '-file-' . $name, $path, $this->timeout );

    return $path;
  }

  /**
   * Creates an iframeable file from html.
   *
   * @param string $html HTML to process.
   * @param string $name Filename to use.
   *
   * @return string URL of local cached file.
   * @throws DOMException
   */
  private function load_html_to_iframeable_file( &$html, $name ): string {
    $doc = $this->get_document_from_html( $html );
    /* Put on the iframeResizer js resizing. */
    $this->put_iframeresizer_into_doc( $doc );

    return $this->write_sanitized_doc( $doc, $name );
  }

  /**
   * Load a page from a URL and put it into a local cached file.
   *
   * @param string $url URL to load.
   * @param string $filename Filename to use in local file system.
   *
   * @return string URL of local cached file.
   * @throws DOMException
   */
  private function load_url_to_iframeable_file( $url, $filename ): string {

    $response = cached_safe_remote_get( $url );
    $code     = wp_remote_retrieve_response_code( $response );
    $mesg     = null;
    if ( 200 !== $code ) {
      if ( is_wp_error( $response ) ) {
        $mesg = $response->get_error_code() . ': ' . $response->get_error_message();
      } else {
        $mesg = $code . ': ' . wp_remote_retrieve_response_message( $response );
      }
    }
    if ( $mesg ) {
      throw new Exception( $mesg );
    }

    return $this->load_html_to_iframeable_file( $response['body'], $filename );
  }

  /**
   * @param array  $atts Attributes for a shortcode renderer.
   * @param string $filename Filename to use in local file system.
   *
   * @return string URL of local cached file.
   * @throws DOMException
   */
  private function load_meta_to_iframeable_file( $atts, $filename ): string {
    $html = get_post_meta( get_the_ID(), $atts['meta_tag'], true );

    return $this->load_html_to_iframeable_file( $html, $filename );
  }

  /**
   * Remove unnecessary parts of the document.
   *
   * @param DOMDocument $doc
   *
   * @return void
   */
  private function clean_doc( DOMDocument &$doc, $scrub_list = null ) {
    if ( null == $scrub_list ) {
      $scrub_list = self::$scrub_list;
    }
    $xpath = new DOMXPath( $doc );
    foreach ( $scrub_list as $scrub ) {
      try {
        $els = @$xpath->query( $scrub );

        if ( false === $els ) {
          error_log( 'Error in xpath expression ' . $scrub );
        } else {
          foreach ( $els as $el ) {
            $el->parentNode->removeChild( $el );
          }
        }
      } catch ( Exception $ex ) {
        error_log( 'Error in xpath expression ' . $scrub );
      }
    }
  }

  /**
   * Remove unnecessary parts of the document.
   *
   * @param DOMDocument $doc
   *
   * @return void
   */
  private function remove_tiny_img_tags_from_doc( DOMDocument &$doc ) {
    $xpath = new DOMXPath( $doc );
    $els   = @$xpath->query( '/html/body//img' );
    foreach ( $els as $el ) {
      /* Delete tiny images */
      $w = $el->getAttribute( 'width' );
      $h = $el->getAttribute( 'height' );
      if ( strlen( $w ) > 0 && strlen( $h ) > 0 && ( $w * $h ) <= 16 ) {
        $el->parentNode->removeChild( $el );
      }
    }
  }

  /**
   * Ingest images (and other assets) from an HTML document, and updated the document to use local, ingested, copies.
   *
   * @param DOMDocument $doc HTML document to ingest.
   *
   * @return void
   */
  private function ingest_assets( DOMDocument &$doc ) {
    $srcs  = array();
    $xpath = new DOMXPath( $doc );
    $els   = @$xpath->query( '/html/body//img' );
    if ( false === $els ) {
      error_log( 'Error in xpath looking for images' );
    } else {
      /* Locate the image URLs, and dedup them. */
      foreach ( $els as $el ) {
        $src          = $el->getAttribute( 'src' );
        $srcs[ $src ] = true;
      }
      /* Ingest and cache the assets. */
      foreach ( $srcs as $src => $_ ) {
        $local_url     = $this->ingest_attachment( $src );
        $srcs [ $src ] = $local_url;
      }
      /* Update the html to refer to the local assets. */
      foreach ( $els as $el ) {
        $src = $el->getAttribute( 'src' );
        if ( ! empty ( $srcs [ $src ] ) ) {
          $el->setAttribute( 'src', $srcs[ $src ] );
        }
      }
    }
  }

  /**
   * Fetch an object from a URL and create an attachement post from it.
   *
   * @param string $url_to_ingest URL of the object (image, usually) to ingest.
   *
   * @return string URL of ingested copy on the local server.
   */
  private function ingest_attachment( string $url_to_ingest ): string {
    $local_url   = null;
    $args        = array(
      'post_type'           => 'attachment',
      'meta_key'            => '_source_url',
      'meta_value'          => $url_to_ingest,
      'orderby'             => 'none',
      'nopaging'            => true,
      'ignore_sticky_posts' => true,
      'is_singular'         => true,
      'post_status'         => array( 'inherit', 'publish', 'private' ),
    );
    $query       = new WP_Query( $args );
    $attachments = $query->get_posts();
    foreach ( $attachments as $attachment ) {
      $local_url = $attachment->guid;
    }
    wp_reset_postdata();

    if ( null === $local_url ) {
      /* Go sideload the image */
      require_once( ABSPATH . 'wp-admin/includes/media.php' );
      require_once( ABSPATH . 'wp-admin/includes/file.php' );
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
      global $post;
      $id         =
        media_sideload_image( $url_to_ingest, $post->ID, POST_FROM_EMAIL_SLUG . '_origin:' . $url_to_ingest, 'id' );
      $attachment = get_post( $id );
      $local_url  = $attachment->guid;
    }

    return $local_url;
  }

  /**
   * Adds necessary annotation to received email files to make them work in iframes.
   * An extra script is required to allow iframes to expand to the size of the framed page.
   *
   * @param DOMDocument $doc
   *
   * @return void
   * @throws \DOMException
   */
  private function put_iframeresizer_into_doc( DOMDocument $doc ) {

    /* append the iframeResizer content window tag. */
    $src = POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/js/iframeResizer.contentWindow.min.js';
    $tag = $doc->createElement( 'script' );
    $tag->setAttribute( 'src', $src );
    $body = ( new DOMXpath( $doc ) )->query( '/html/body' );
    if ( count( $body ) > 0 ) {
      $body[0]->appendChild( $tag );
    } else {
      $doc->appendChild( $tag );
    }
  }

  /**
   * Sanitize the document and write it to a file for iframe retrieval, creating the directory as needed.
   *
   * @param DOMDocument $doc The HTML document.
   * @param string      $filename The filename in the local file system.
   *
   * @return string The url of the file written.
   */
  private function write_sanitized_doc( DOMDocument &$doc, string $filename ): string {
    $dirs    = wp_upload_dir();
    $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
    if ( ! @file_exists( $dirname ) ) {
      @mkdir( $dirname );
    }
    $pathname = $dirname . DIRECTORY_SEPARATOR . $filename;
    file_put_contents( $pathname, $doc->saveHTML(), LOCK_EX );

    return $dirs['baseurl'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG . DIRECTORY_SEPARATOR . $filename;
  }

  /**
   * Turn a text string into a DOMDocument and tidy it up.
   *
   * @param string $html HTML document in a text string.
   *
   * @return DOMDocument The created document.
   */
  private function get_document_from_html( string &$html ): DOMDocument {
    $internal_errors = libxml_use_internal_errors( true );

    $doc = new DOMDocument ( 1.0, 'utf-8' );

    $doc->preserveWhiteSpace = false;
    $doc->loadHTML( $html );
    $this->clean_doc( $doc );
    $this->remove_tiny_img_tags_from_doc( $doc );
    $this->ingest_assets( $doc );
    libxml_use_internal_errors( $internal_errors );

    return $doc;
  }

  const less_wrapper = <<<'LESSISMORE'
div#post-from-email {
    all: initial;
    table, td {
	    border-collapse: revert;
	    border-width: 0;
	    margin-bottom: 0;
	    border-spacing: 0;
	    caption-side: revert;
	    color: revert;
	    cursor: revert;
	    direction: revert;
	    empty-cells: revert;
	    padding: 0;
	};
	
    tr, td {
      text-align: initial;
    }	  
LESSISMORE;

  /**
   * Extract the <body> from a doc as a <div> with the embedded style sheets included.
   *
   * @param DOMDocument $doc The document. It is mutated.
   *
   * @return false|string The extracted HTML.
   * @throws DOMException
   * @throws Less_Exception_Parser
   */
  private function extract_body( DOMDocument $doc ) {
    require_once POST_FROM_EMAIL_PLUGIN_DIR . '/vendor/autoload.php';
    $less_parser = new Less_Parser();

    /* Fetch any style sheets, remove them, then scope-limit them with less */
    $search      = new DOMXPath( $doc );
    $els         = $search->query( '//style' );
    $css = '';
    /* Notice that we can't mutate the document while a Generator is in use on it. */
    $stylesheets = array();
    foreach ( $els as $el ) {
      $css .= $el->nodeValue;
      /* Notice that we can't mutate the document while a Generator is in use on it.
       * So we materialize the Generator's results, then mutate. */
      $stylesheets [] = $el;
    }
    foreach ( $stylesheets as $stylesheet ) {
      $stylesheet->parentNode->removeChild( $stylesheet );
    }
    unset ($stylesheets);

    $less = self::less_wrapper . $css . '}';
    $less_parser->parse( $less );
    $css                = $less_parser->getCss();
    $style              = $doc->createElement( 'style' );
    $style->textContent = $css;

    /* Find the <body>. */
    $body = null;
    $els  = $search->query( '/html/body' );
    foreach ( $els as $el ) {
      $body = $el;
      break;
    }
    /* Put in the changed style object. */
    $body->insertBefore( $style, $body->firstChild );

    /* Create a <div> with the same attributes as the <body> */
    $attribute_nodes = array();
    foreach ( $body->attributes as $attribute ) {
      $attribute_nodes [] = $body->getAttributeNode( $attribute->name );
    }
    $div = $doc->createElement( 'div' );
    foreach ( $attribute_nodes as $attribute_node ) {
      $div->setAttributeNode( $attribute_node );
    }
    unset ($attribute_nodes);

    $div->setAttribute( 'id', 'post-from-email' );

    /* Move the body elements to the new <div> */
    $child_nodes = array();
    foreach ( $body->childNodes as $child_node ) {
      $child_nodes [] = $child_node;
    }
    foreach ( $child_nodes as $child_node ) {
      $div->appendChild( $child_node );
    }
    unset ( $child_nodes );

    /* Extract the html from the newly populated <div> */
    return $doc->saveHTML( $div );
  }
}
