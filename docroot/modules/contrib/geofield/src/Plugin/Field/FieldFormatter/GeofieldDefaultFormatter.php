<?php

/**
 * @file
 * Contains \Drupal\geofield\Plugin\Field\FieldFormatter\GeofieldDefaultFormatter.
 */

namespace Drupal\geofield\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * Plugin implementation of the 'geofield_default' formatter.
 *
 * @FieldFormatter(
 *   id = "geofield_default",
 *   label = @Translation("Raw Output"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldDefaultFormatter extends FormatterBase {

  /**
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   *   The GeoPHP service.
   */
  protected $geophp;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition,  $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->geophp = \Drupal::service('geofield.geophp');
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'output_format' => 'wkt'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $options = $this->geophp->getAdapterMap();
    unset($options['google_geocode']);

    $elements['output_format'] = [
      '#title' => $this->t('Output Format'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('output_format'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $formatOptions = $this->geophp->getAdapterMap();
    $summary = [];
    $summary[] = $this->t('Geospatial output format: @format', ['@format' => $formatOptions[$this->getSetting('output_format')]]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $geom = $this->geophp->load($item->value);
      $output = $geom ? $geom->out($this->getSetting('output_format')) : '';
      $elements[$delta] = ['#markup' => Html::escape($output)];
    }

    return $elements;
  }

}
