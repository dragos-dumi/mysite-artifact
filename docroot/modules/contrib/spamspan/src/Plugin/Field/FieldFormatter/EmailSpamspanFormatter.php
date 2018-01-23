<?php

/**
 * @file
 * Contains \Drupal\Core\Field\Plugin\Field\FieldFormatter\EmailSpamspanFormatter.
 */

namespace Drupal\spamspan\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\spamspan\Plugin\SpamspanSettingsFormTrait;

/**
 * Plugin implementation of the 'email_mailto' formatter.
 *
 * @FieldFormatter(
 *   id = "email_spamspan",
 *   label = @Translation("Email SpamSpan"),
 *   field_types = {
 *     "email"
 *   }
 * )
 *
 * @ingroup field_formatter
 */
class EmailSpamspanFormatter extends FormatterBase {

  use SpamspanSettingsFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $plugin_manager = \Drupal::service('plugin.manager.filter');
    $configuration = $plugin_manager->getDefinition('filter_spamspan');

    return $configuration['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('spamspan_use_form')) {
      $summary[] = $this->t('Link to a contact form instead of an email address');
    }
    else {
      $summary[] = $this->t('Replacement for "@" is %1', array('%1' => $this->getSetting('spamspan_at')));
      if ($this->getSetting('spamspan_use_graphic')) {
        $summary[] = $this->t('Use a graphical replacement for "@"');
      }
      if ($this->getSetting('spamspan_dot_enable')) {
        $summary[] = $this->t('Replacement for "." is %1', array('%1' => $this->getSetting('spamspan_dot')));
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSettingsForm(array &$form, FormStateInterface $form_state) {
    $field_name = $form_state->get('plugin_settings_edit');
    $settings = $form_state->getValue(['fields', $field_name, 'settings_edit_form', 'settings']);
    $use_form = $settings['use_form'];

    // No trees, see https://www.drupal.org/node/2378437.
    unset($settings['use_form']);
    $settings += $use_form;
    $form_state->setValue(['fields', $field_name, 'settings_edit_form', 'settings'], $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#markup' => spamspan($item->value, $this->getSettings()),
        '#attached' => ['library' => ['spamspan/obfuscate']],
      ];
    }

    return $elements;
  }

}
