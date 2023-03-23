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
   * @since    1.0.0
   */
  class Run {

    private $timeout = WEEK_IN_SECONDS * 2;

    /**
     * Our Post_From_Email_Run constructor
     * to run the plugin logic.
     *
     * @since 1.0.0
     */
    function __construct() {
      $this->add_hooks();
      if ( WP_DEBUG ) {
        $this->timeout = DAY_IN_SECONDS; // TODO this can be longer.
      }
    }

    /** Generate
     *
     * @param string $date the already-generated date (ignored)
     *
     * @return string the date string we generate. It must end with a space for correct formatting.
     */
    public function post_date_and_time( string $date ): string {

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
    public function embed( array $atts = [], string $content = null, string $tag = '' ): string {

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

      $file_url = $this->get_file_url( $atts );

      return "<iframe id='frame0' class='post-from-email' style='overflow: hidden; height: 100%;
        width: 100%;' src='$file_url' ></iframe>";
    }

    /**
     * Registers all WordPress and plugin related hooks
     *
     * @access  private
     * @return  void
     * @since  1.0.0
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
    private function get_filename( $url ): string {
      if ( str_starts_with( $url, 'f-' ) && str_ends_with( $url, '.html' ) ) {
        return $url;
      }
      $file = 'f-'
              . sanitize_key( parse_url( $url, PHP_URL_HOST )
                              . '-'
                              . parse_url( $url, PHP_URL_PATH ) )
              . '.html';

      return $file;
    }

    private function get_file_url( $atts ): string {
      if ( array_key_exists( 'src', $atts ) ) {
        $name = $this->get_filename( $atts['src'] );
      } elseif ( array_key_exists( 'tag', $atts ) ) {
        $name = $atts ['tag'] . '.html';
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
      $doc = new \DOMDocument (1.0, 'utf=8' );

      $doc->preserveWhiteSpace = false;
      @$doc->loadHTMLFile( $url );
      $this->clean_doc( $doc );
      $this->annotate_doc( $doc );
      $dirs    = wp_upload_dir();
      $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
      @mkdir( $dirname );
      $pathname = $dirname . DIRECTORY_SEPARATOR . $name;
      $doc->saveHTMLFile( $pathname );
      $pathname = $dirs['baseurl'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG . DIRECTORY_SEPARATOR . $name;

      return $pathname;
    }

    /**
     * @throws \DOMException
     */
    private function load_meta_to_file( $atts, $name ): string {
      $doc = new \DOMDocument (1.0, 'utf-8)');

      $doc->preserveWhiteSpace = false;
      @$doc->loadHTML( get_post_meta( get_the_ID(), $atts['meta_tag'], true ) );

      $this->clean_doc( $doc );
      $this->annotate_doc( $doc );
      $dirs    = wp_upload_dir();
      $dirname = $dirs['basedir'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG;
      @mkdir( $dirname );
      $pathname = $dirname . DIRECTORY_SEPARATOR . $name;
      $doc->saveHTMLFile( $pathname );
      $pathname = $dirs['baseurl'] . DIRECTORY_SEPARATOR . POST_FROM_EMAIL_SLUG . DIRECTORY_SEPARATOR . $name;

      return $pathname;
    }

    /**
     * Remove unnecessary parts of the document.
     *
     * @param \DOMDocument $doc
     *
     * @return void
     */
    private function clean_doc( \DOMDocument &$doc ) {
      $xpath = new \DOMXPath( $doc );
      $metas = $xpath->query( '/html/head/meta' );

      /* Get rid of the fb opengraph og:image items. There are many and they are long. */
      foreach ( $metas as $meta ) {
        $property = $meta->getAttribute( 'property' );
        if ( 'og:image' === $property ) {
          $meta->parentNode->removeChild( $meta );
        }
      }

      /* scrub out the scripts in the header, mostly surveillance. */
      $scripts = $xpath->query( '/html/head/script' );
      foreach ( $scripts as $script ) {
        $script->parentNode->removeChild( $script );
      }
      $bodies = $xpath->query( '/html/body' );
      foreach ( $bodies as $body ) {
        /* Get rid of the surveillance pixel. */
        $els = $xpath->query( "div[@id='tracking-image']", $body );
        foreach ( $els as $el ) {
          $el->parentNode->removeChild( $el );
        }

        /* Get rid of the Constant Contact footer; we don't want anybody to
         * unsubscribe from the web page.
         */
        $els = $xpath->query( "//table[@class='footer-container']", $body );
        foreach ( $els as $el ) {
          $el->parentNode->removeChild( $el );
        }
      }

      return;
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
      $body[0]->appendChild( $tag );
    }
  }
}
