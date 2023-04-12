<?php

namespace Post_From_Email {
// Exit if accessed directly.
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
    public function embed( array $atts = [], $content = null, $tag = '' ) {

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

      return "<iframe id='frame0' class='post-from-email' style='overflow: hidden; height: 100%;
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
      $parsed = wp_parse_url ( $url );
      $file = 'f-' . sanitize_key( $parsed['PHP_URL_HOST'] . '-' . $parsed['PHP_URL_PATH'] ) . '.html';

      return $file;
    }

    private function get_file_url( $atts ): string {
      global $post;
      if ( array_key_exists( 'src', $atts ) ) {
        $name = $this->get_filename( $atts['src'] );
      } else {
        $name = POST_FROM_EMAIL_SLUG . $post->ID . '.html';
      }
      $path = get_transient( POST_FROM_EMAIL_SLUG . '-file-' . $name );
      if ( $path ) {
        return $path;
      }
      if ( array_key_exists( 'src', $atts ) ) {
        $path = $this->load_doc_to_file( $atts['src'], $name );
      } elseif ( array_key_exists( 'tag', $atts ) ) {
        $path = $this->load_meta_to_file( $atts, $name );
      }
      set_transient( POST_FROM_EMAIL_SLUG . '-file-' . $name, $path, $this->timeout );

      return $path;
    }

    /**
     * @throws \DOMException
     */
    private function load_doc_to_file( $url, $name ): string {
      $doc = new \DOMDocument ( 1.0, 'utf-8' );

      $doc->preserveWhiteSpace = false;
      @$doc->loadHTMLFile( $url );

      return $this->write_sanitized_doc( $doc, $name );
    }

    /**
     * @throws \DOMException
     */
    private function load_meta_to_file( $atts, $name ): string {
      $doc = new \DOMDocument ( 1.0, 'utf-8)' );

      $doc->preserveWhiteSpace = false;
      @$doc->loadHTML( get_post_meta( get_the_ID(), $atts['meta_tag'], true ) );

      return $this->write_sanitized_doc( $doc, $name );
    }

    /**
     * Remove unnecessary parts of the document.
     *
     * @param \DOMDocument $doc
     *
     * @return void
     */
    private function clean_doc( \DOMDocument &$doc, $scrub_list = null ) {
      if ( null == $scrub_list ) {
        $scrub_list = self::$scrub_list;
      }
      $xpath = new \DOMXPath( $doc );
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
        } catch ( \Exception $ex ) {
          error_log( 'Error in xpath expression ' . $scrub );
        }
      }
    }

    /**
     * Adds necessary annotation to received email files to make them work in iframes.
     * An extra script is required to allow iframes to expand to the size of the framed page.
     *
     * @param \DOMDocument $doc
     *
     * @return void
     * @throws \DOMException
     */
    private function annotate_doc( \DOMDocument &$doc ) {

      /* append the iframeResizer content window tag. */
      $src = POST_FROM_EMAIL_PLUGIN_URL . 'core/assets/js/iframeResizer.contentWindow.min.js';
      $tag = $doc->createElement( 'script' );
      $tag->setAttribute( 'src', $src );
      $body = ( new \DOMXpath( $doc ) )->query( '/html/body' );
      if ( count( $body ) > 0 ) {
        $body[0]->appendChild( $tag );
      } else {
        $doc->appendChild( $tag );
      }
    }

    /**
     * Sanitize the document and write it to a file for iframe retrieval, creating the directory as needed.
     *
     * @param \DOMDocument $doc The HTML document.
     * @param string       $name The filename.
     *
     * @return string The url of the file written.
     * @throws \DOMException
     */
    private function write_sanitized_doc( \DOMDocument &$doc, $name ) {
      $this->clean_doc( $doc );
      $this->annotate_doc( $doc );
      $dirs    = wp_upload_dir();
      $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
      if ( ! @file_exists( $dirname ) ) {
        @mkdir( $dirname );
      }
      $pathname = $dirname . DIRECTORY_SEPARATOR . $name;
      file_put_contents( $pathname, $doc->saveHTML(), LOCK_EX );

      return $dirs['baseurl'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG . DIRECTORY_SEPARATOR . $name;
    }
  }
}
