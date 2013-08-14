<?php

/**
 * @file
 * Definition of Drupal\pathologic\Tests\PathologicTest.
 */

namespace Drupal\pathologic\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\pathologic\Plugin\Filter\FilterPathologic;
use Drupal\Core\Language\Language;

class PathologicTest extends WebTestBase {

  public static $modules = array('filter', 'pathologic');
  
  public static function getInfo() {
    return array(
      'name' => 'Pathologic',
      'description' => 'Tests Pathologic functionality.',
      'group' => 'Filter',
    );
  }

  function testPathologic() {
    global $script_path;

    // Start by testing our function to build protocol-relative URLs
    $this->assertEqual(
      _pathologic_url_to_protocol_relative('http://example.com/foo/bar'),
      '//example.com/foo/bar',
      t('Protocol-relative URL creation with http:// URL')
    );
    $this->assertEqual(
      _pathologic_url_to_protocol_relative('https://example.org/baz'),
      '//example.org/baz',
      t('Protocol-relative URL creation with https:// URL')
    );

    // Build a phony filter
    $filter = new FilterPathologic(array('settings' => array('protocol_style' => 'full', 'local_paths' => '')), 'foo', array('module' => 'pathologic', 'cache' => FALSE));

    // Build some paths to check against
    $test_paths = array(
      'foo' => array(
        'path' => 'foo',
        'opts' => array()
      ),
      'foo/bar' => array(
        'path' => 'foo/bar',
        'opts' => array()
      ),
      'foo/bar?baz' => array(
        'path' => 'foo/bar',
        'opts' => array('query' => array('baz' => NULL))
      ),
      'foo/bar?baz=qux' => array(
        'path' => 'foo/bar',
        'opts' => array('query' => array('baz' => 'qux'))
      ),
      'foo/bar#baz' => array(
        'path' => 'foo/bar',
        'opts' => array('fragment' => 'baz'),
      ),
      'foo/bar?baz=qux&amp;quux=quuux#quuuux' => array(
        'path' => 'foo/bar',
        'opts' => array(
          'query' => array('baz' => 'qux', 'quux' => 'quuux'),
          'fragment' => 'quuuux',
        ),
      ),
      'foo%20bar?baz=qux%26quux' => array(
        'path' => 'foo bar',
        'opts' => array(
          'query' => array('baz' => 'qux&quux'),
        ),
      ),
      '/' => array(
        'path' => '<front>',
        'opts' => array(),
      ),
    );

    // Run tests with clean URLs both enabled and disabled
    foreach (array('', 'index.php/') as $script_path_option) {
      $script_path = $script_path_option;
      // Run tests with absoulte filtering enabled and disabled
      foreach (array('full', 'proto-rel', 'path') as $protocol_style) {
        $filter->settings['protocol_style'] = $protocol_style;
        $paths = array();
        foreach ($test_paths as $path => $args) {
          $args['opts']['absolute'] = $protocol_style !== 'path';
          $paths[$path] = _pathologic_content_url($args['path'], $args['opts']);
          if ($protocol_style === 'proto-rel') {
            $paths[$path] = _pathologic_url_to_protocol_relative($paths[$path]);
          }
        }
        $t10ns = array(
          '!clean' => empty($script_path) ? t('Yes') : t('No'),
          '!ps' => $protocol_style,
        );

        $this->assertEqual(
          $filter->process('<a href="foo"><img src="foo/bar" /></a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="' . $paths['foo'] . '"><img src="' . $paths['foo/bar'] . '" /></a>',
          t('Simple paths. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<form action="foo/bar?baz"><IMG LONGDESC="foo/bar?baz=qux" /></a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<form action="' . $paths['foo/bar?baz'] . '"><IMG LONGDESC="' . $paths['foo/bar?baz=qux'] . '" /></a>',
          t('Paths with query string. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<a href="foo/bar#baz">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="' . $paths['foo/bar#baz'] . '">',
          t('Path with fragment. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<a href="#foo">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="#foo">',
          t('Fragment-only links. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<a href="foo/bar?baz=qux&amp;quux=quuux#quuuux">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="' . $paths['foo/bar?baz=qux&amp;quux=quuux#quuuux'] . '">',
          t('Path with query string and fragment. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<a href="foo%20bar?baz=qux%26quux">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="' . $paths['foo%20bar?baz=qux%26quux'] . '">',
          t('Path with URL encoded parts. Clean URLs: !clean; protocol style: !ps.', $t10ns)
        );
        $this->assertEqual(
          $filter->process('<a href="/"></a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
          '<a href="' . $paths['/'] . '"></a>',
          t('Path with just slash. Clean URLs: !clean; protocol style: !ps', $t10ns)
        );
      }
    }

    global $base_path;
    $this->assertEqual(
      $filter->process('<a href="' . $base_path . 'foo">bar</a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => FALSE)) .'">bar</a>',
      t('Paths beginning with $base_path (like WYSIWYG editors like to make)')
    );
    global $base_url;
    $this->assertEqual(
      $filter->process('<a href="' . $base_url . '/foo">bar</a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => FALSE)) .'">bar</a>',
      t('Paths beginning with $base_url')
    );

    // @see http://drupal.org/node/1617944
    $this->assertEqual(
      $filter->process('<a href="//example.com/foo">bar</a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="//example.com/foo">bar</a>',
      t('Off-site schemeless URLs (//example.com/foo) ignored')
    );

    // Test internal: and all base paths
    $filter->settings = array(
      'protocol_style' => 'full',
      'local_paths' => "http://example.com/qux\nhttp://example.org\n/bananas",
    );

    // @see https://drupal.org/node/2030789
    $this->assertEqual(
      $filter->process('<a href="//example.org/foo">bar</a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => TRUE)) . '">bar</a>',
      t('On-site schemeless URLs processed')
    );
    $this->assertEqual(
      $filter->process('<a href="internal:foo">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => TRUE)) . '">',
      t('Path Filter compatibility (internal:)')
    );
    $this->assertEqual(
      $filter->process('<a href="files:image.jpeg">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url(file_create_url('public://image.jpeg'), array('absolute' => TRUE, 'is_file' => TRUE)) . '">',
      t('Path Filter compatibility (files:)')
    );
    $this->assertEqual(
      $filter->process('<a href="http://example.com/qux/foo"><img src="http://example.org/bar.jpeg" longdesc="/bananas/baz" /></a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => TRUE)) . '"><img src="' . _pathologic_content_url('bar.jpeg', array('absolute' => TRUE)) . '" longdesc="' . _pathologic_content_url('baz', array('absolute' => TRUE)) . '" /></a>',
      t('"All base paths for this site" functionality')
    );
    $this->assertEqual(
      $filter->process('<a href="webcal:foo">bar</a>', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="webcal:foo">bar</a>',
      t('URLs with likely protocols are ignored')
    );
    // Test hook_pathologic_alter() implementation.
    $this->assertEqual(
      $filter->process('<a href="foo?test=add_foo_qpart">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="' . _pathologic_content_url('foo', array('absolute' => TRUE, 'query' => array('test' => 'add_foo_qpart', 'foo' => 'bar'))) . '">',
      t('hook_pathologic_alter(): Alter $url_params')
    );
    $this->assertEqual(
      $filter->process('<a href="bar?test=use_original">', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL),
      '<a href="bar?test=use_original">',
      t('hook_pathologic_alter(): Passthrough with use_original option')
    );

    // Test paths to existing files when clean URLs are disabled.
    // @see http://drupal.org/node/1672430
    $script_path = '';
    $filtered_tag = $filter->process('<img src="misc/druplicon.png" />', Language::LANGCODE_NOT_SPECIFIED, NULL, NULL);
    $this->assertTrue(
      strpos($filtered_tag, 'q=') === FALSE,
      t('Paths to files don\'t have ?q= when clean URLs are off')
    );
  }
}

/**
 * Wrapper around url() which does HTML entity decoding and encoding.
 *
 * Since Pathologic works with paths in content, it needs to decode paths which
 * have been HTML-encoded, and re-encode them when done. This is a wrapper
 * around url() which does the same thing so that we can expect the results
 * from it and from Pathologic to still match in our tests.
 *
 * @see url()
 * @see http://drupal.org/node/1672932
 * @see http://www.w3.org/TR/xhtml1/guidelines.html#C_12
 */
function _pathologic_content_url($path, $options) {
  // If we should pretend this is a path to a file, make url() behave like clean
  // URLs are enabled.
  // @see _pathologic_replace()
  // @see http://drupal.org/node/1672430
  if (!empty($options['is_file'])) {
    $options['script_path'] = '';
  }

  return check_plain(url(htmlspecialchars_decode($path), $options));
}

/**
 * Implements hook_pathologic_alter(), for testing that functionality.
 */
function pathologic_pathologic_alter(&$url_params, $parts, $settings) {
  if (is_array($parts['qparts']) && isset($parts['qparts']['test'])) {
    if ($parts['qparts']['test'] === 'add_foo_qpart') {
      // Add a "foo" query part
      if (empty($url_params['options']['query'])) {
        $url_params['options']['query'] = array();
      }
      $url_params['options']['query']['foo'] = 'bar';
    }
    elseif ($parts['qparts']['test'] === 'use_original') {
      $url_params['options']['use_original'] = TRUE;
    }
  }
}
