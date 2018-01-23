<?php

/**
 * @file
 * Contains \Drupal\inline_responsive_images\Plugin\Filter\FilterImageStyle.
 */

namespace Drupal\inline_responsive_images\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a filter to render inline images as image styles.
 *
 * @Filter(
 *   id = "filter_imagestyle",
 *   module = "inline_responsive_images",
 *   title = @Translation("Display image styles"),
 *   description = @Translation("Uses the data-image-style attribute on &lt;img&gt; tags to display image styles."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterImageStyle extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $image_styles = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
    $form['image_styles'] = array(
      '#type' => 'markup',
      '#markup' => 'Select the image styles that are available in the editor',
    );
    foreach ($image_styles as $image_style) {
      $form['image_style_' . $image_style->id()] = array(
        '#type' => 'checkbox',
        '#title' => $image_style->label(),
        '#default_value' => isset($this->settings['image_style_' . $image_style->id()]) ? $this->settings['image_style_' . $image_style->id()] : 0,
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (stristr($text, 'data-image-style') !== FALSE && stristr($text, 'data-responsive-image-style') == FALSE) {
      $image_styles = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();

      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//*[@data-entity-type="file" and @data-entity-uuid and @data-image-style]') as $node) {
        $file_uuid = $node->getAttribute('data-entity-uuid');
        $image_style_id = $node->getAttribute('data-image-style');

        // If the image style is not a valid one, then don't transform the HTML.
        if (empty($file_uuid) || !isset($image_styles[$image_style_id])) {
          continue;
        }

        // Retrieved matching file in array for the specified uuid.
        $matching_files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uuid' => $file_uuid]);
        $file = reset($matching_files);

        // Stop further element processing, if it's not a valid file.
        if (!$file) {
          continue;
        }

        $image = \Drupal::service('image.factory')->get($file->getFileUri());

        // Stop further element processing, if it's not a valid image.
        if (!$image->isValid()) {
          continue;
        }

        $width = $image->getWidth();
        $height = $image->getHeight();

        $node->removeAttribute('width');
        $node->removeAttribute('height');
        $node->removeAttribute('src');

        // Make sure all non-regenerated attributes are retained.
        $attributes = array();
        for ($i = 0; $i < $node->attributes->length; $i++) {
          $attr = $node->attributes->item($i);
          $attributes[$attr->name] = $attr->value;
        }

        // Set up image render array.
        $image = array(
          '#theme' => 'image_style',
          '#uri' => $file->getFileUri(),
          '#width' => $width,
          '#height' => $height,
          '#attributes' => $attributes,
          '#style_name' => $image_style_id,
        );

        $altered_html = \Drupal::service('renderer')->render($image);

        // Load the altered HTML into a new DOMDocument and retrieve the elements.
        $alt_nodes = Html::load(trim($altered_html))->getElementsByTagName('body')
          ->item(0)
          ->childNodes;

        foreach ($alt_nodes as $alt_node) {
          // Import the updated node from the new DOMDocument into the original
          // one, importing also the child nodes of the updated node.
          $new_node = $dom->importNode($alt_node, TRUE);
          // Add the image node(s)!
          // The order of the children is reversed later on, so insert them in reversed order now.
          $node->parentNode->insertBefore($new_node, $node);
        }
        // Finally, remove the original image node.
        $node->parentNode->removeChild($node);
      }

      return new FilterProcessResult(Html::serialize($dom));
    }

    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      $image_styles = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
      $list = '<code>' . implode('</code>, <code>', array_keys($image_styles)) . '</code>';
      return t('
        <p>You can display images using a site-wide style by adding a <code>data-image-style</code> attribute, whose value is one of the image style machine names: !image-style-machine-name-list.</p>', array('!image-style-machine-name-list' => $list));
    }
    else {
      return t('You can display images using site-wide styles by adding a data-image-style attribute.');
    }
  }
}
