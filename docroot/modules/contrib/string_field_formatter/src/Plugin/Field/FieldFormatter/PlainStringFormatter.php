<?php

namespace Drupal\string_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'plain_string_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "plain_string_formatter",
 *   label = @Translation("Plain string formatter"),
 *   field_types = {
 *     "string",
 *   },
 *   edit = {
 *     "editor" = "form"
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class PlainStringFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'wrap_tag' => '_none',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultWrapTagOptions() {
    return array(
      '_none' => t('- None -'),
      'div' => t('DIV'),
      'h1' => t('H1'),
      'h2' => t('H2'),
      'h3' => t('H3'),
      'h4' => t('H4'),
      'h5' => t('H5'),
      'h6' => t('H6'),
      'span' => t('SPAN'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = array();
    $element['wrap_tag'] = array(
      '#title' => t('Wrap text in tag'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('wrap_tag'),
      '#options' => $this->defaultWrapTagOptions(),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();
    $wrap_tag = $this->getSetting('wrap_tag');
    if ('_none' == $wrap_tag) {
      $summary[] = t('No wrap tag defined.');
    }
    else {
      $summary[] = t('Wrap text with tag: @tag', array('@tag' => $wrap_tag));
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    $wrap_tag = $this->getSetting('wrap_tag');
    if ('_none' == $wrap_tag) {
      $wrap_tag = '';
    }

    foreach ($items as $delta => $item) {
      $elements[$delta] = array(
        '#type' => 'html_tag',
        '#tag' => $wrap_tag,
        '#value' => $item->value,
      );
    }

    return $elements;
  }

}
