<?php

/**
 * @file
 * Contains \Drupal\inline_responsive_images\Plugin\CKEditorPlugin\DrupalResponsiveImageStyle.
 */

namespace Drupal\inline_responsive_images\Plugin\CKEditorPlugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;

/**
 * Defines the "drupalresponsiveimagestyle" plugin.
 *
 * @CKEditorPlugin(
 *   id = "drupalresponsiveimagestyle",
 *   label = @Translation("Drupal responsive image style"),
 *   module = "inline_responsive_images"
 * )
 */
class DrupalResponsiveImageStyle extends PluginBase implements CKEditorPluginInterface, CKEditorPluginContextualInterface {

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'inline_responsive_images') . '/js/plugins/drupalresponsiveimagestyle/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    if (!$editor->hasAssociatedFilterFormat()) {
      return FALSE;
    }

    // Automatically enable this plugin if the text format associated with this
    // text editor uses the filter_responsive_image_style filter and the
    // DrupalImage button is enabled.
    $format = $editor->getFilterFormat();
    if ($format->filters('filter_responsive_image_style')->status) {
      $enabled = FALSE;
      $settings = $editor->getSettings();
      foreach ($settings['toolbar']['rows'] as $row) {
        foreach ($row as $group) {
          foreach ($group['items'] as $button) {
            if ($button === 'DrupalImage') {
              $enabled = TRUE;
            }
          }
        }
      }
      return $enabled;
    }

    return FALSE;
  }

}