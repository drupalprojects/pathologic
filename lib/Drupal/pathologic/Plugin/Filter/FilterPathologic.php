<?php

/**
 * @file
 * Pathologic!
 */

namespace Drupal\pathologic\Plugin\Filter;

use Drupal\filter\Annotation\Filter;
use Drupal\Core\Annotation\Translation;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Component\Utility\Crypt;

/**
 * Attempts to correct broken paths in content.
 *
 * @Filter(
 *   id = "filter_pathologic",
 *   module = "pathologic",
 *   title = @Translation("Correct URLs with Pathologic"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   settings = {
 *     "local_paths" = "",
 *     "protocol_style" = "full"
 *   },
 *   weight = 50
 * )
 */
class FilterPathologic extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    $form['reminder'] = array(
      '#type' => 'item',
      '#title' => t('In most cases, Pathologic should be the <em>last</em> filter in the &ldquo;Filter processing order&rdquo; list.'),
      '#weight' => 0,
    );
    $form['protocol_style'] = array(
      '#type' => 'radios',
      '#title' => t('Processed URL format'),
      '#default_value' => $this->settings['protocol_style'],
      '#options' => array(
        'full' => t('Full URL (<code>http://example.com/foo/bar</code>)'),
        'proto-rel' => t('Protocol relative URL (<code>//example.com/foo/bar</code>)'),
        'path' => t('Path relative to server root (<code>/foo/bar</code>)'),
      ),
      '#description' => t('The <em>Full URL</em> option is best for stopping broken images and links in syndicated content (such as in RSS feeds), but will likely lead to problems if your site is accessible by both HTTP and HTTPS. Paths output with the <em>Protocol relative URL</em> option will avoid such problems, but feed readers and other software not using up-to-date standards may be confused by the paths. The <em>Path relative to server root</em> option will avoid problems with sites accessible by both HTTP and HTTPS with no compatibility concerns, but will absolutely not fix broken images and links in syndicated content.'),
      '#weight' => 10,
    );
    $form['local_paths'] = array(
      '#type' => 'textarea',
      '#title' =>  t('All base paths for this site'),
      '#default_value' => $this->settings['local_paths'],
        '#description' => t('If this site is or was available at more than one base path or URL, enter them here, separated by line breaks. For example, if this site is live at <code>http://example.com/</code> but has a staging version at <code>http://dev.example.org/staging/</code>, you would enter both those URLs here. If confused, please read <a href="!docs">Pathologic&rsquo;s documentation</a> for more information about this option and what it affects.', array('!docs' => 'http://drupal.org/node/257026')),
      '#weight' => 20,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode, $cache, $cache_id) {
    // @todo Move code from .module file to inside here.
    return _pathologic_filter($text, $this->settings, Crypt::hashBase64(serialize($this->settings)));
  }

}
