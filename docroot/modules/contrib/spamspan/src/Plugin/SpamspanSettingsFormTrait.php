<?php

namespace Drupal\spamspan\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides common methods for Spamspan plugins.
 */
trait SpamspanSettingsFormTrait {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Spamspan '@' replacement.
    $form['spamspan_at'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Replacement for "@"'),
      '#default_value' => $this->getSetting('spamspan_at'),
      '#required' => TRUE,
      '#description' => $this->t('Replace "@" with this text when javascript is disabled.'),
    );
    $form['spamspan_use_graphic'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use a graphical replacement for "@"'),
      '#default_value' => $this->getSetting('spamspan_use_graphic'),
      '#description' => $this->t('Replace "@" with a graphical representation when javascript is disabled (and ignore the setting "Replacement for @" above).'),
    );
    $form['spamspan_dot_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Replace dots in email with text'),
      '#default_value' => $this->getSetting('spamspan_dot_enable'),
      '#description' => $this->t('Switch on dot replacement.'),
    );
    $form['spamspan_dot'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Replacement for "."'),
      '#default_value' => $this->getSetting('spamspan_dot'),
      '#required' => TRUE,
      '#description' => $this->t('Replace "." with this text.'),
    );

    // No trees, see https://www.drupal.org/node/2378437.
    // We fix this in our custom validate handler.
    $form['use_form'] = array(
      '#type' => 'details',
      '#title' => $this->t('Use a form instead of a link'),
      '#open' => TRUE,
    );
    $form['use_form']['spamspan_use_form'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use a form instead of a link'),
      '#default_value' => $this->getSetting('spamspan_use_form'),
      '#description' => $this->t('Link to a contact form instead of an email address. The following settings are used only if you select this option.'),
    );
    $form['use_form']['spamspan_form_pattern'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Replacement string for the email address'),
      '#default_value' => $this->getSetting('spamspan_form_pattern'),
      '#required' => TRUE,
      '#description' => $this->t('Replace the email link with this string and substitute the following <br />%url = the url where the form resides,<br />%email = the email address (base64 and urlencoded),<br />%displaytext = text to display instead of the email address.'),
    );
    // Required checkbox? what is the point?
    // If needed, then make an annotation entry as well *     "spamspan_email_encode" = TRUE,
    /*$form['use_form']['spamspan_email_encode'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Encode the email address'),
      '#default_value' => $this->settings['spamspan_email_encode'],
      '#required' => TRUE,
      '#description' => $this->t('Encode the email address using base64 to protect from spammers. Must be enabled for forms because the email address ends up in a URL.'),
    );*/
    $form['use_form']['spamspan_form_default_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default url'),
      '#default_value' => $this->getSetting('spamspan_form_default_url'),
      '#required' => TRUE,
      '#description' => $this->t('Default url to form to use if none specified (e.g. me@example.com[custom_url_to_form])'),
    );
    $form['use_form']['spamspan_form_default_displaytext'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Default displaytext'),
      '#default_value' => $this->getSetting('spamspan_form_default_displaytext'),
      '#required' => TRUE,
      '#description' => $this->t('Default displaytext to use if none specified (e.g. me@example.com[custom_url_to_form|custom_displaytext])'),
    );

    // We need this to insert our own validate/submit handlers.
    // We use our own validate handler to extract use_form settings
    $form['#process'] = array(
      array($this, 'processSettingsForm'),
    );
    return $form;
  }

  /**
   * Returns the value of a setting, or its default value if absent.
   *
   * We need to define this method because EmailSpamspanFormatter and
   * FilterSpamspan have different interfaces and FilterSpamspan is missing
   * getSetting() definition.
   * Also for what ever reason because this is a Trait method overloading does
   * not work.
   *
   * @param string $key
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   *
   * @see PluginSettingsBase::getSetting().
   */
  public function getSetting($key) {
    // Merge defaults if we have no value for the key.
    if (method_exists($this, 'mergeDefaults') && !$this->defaultSettingsMerged && !array_key_exists($key, $this->settings)) {
      $this->mergeDefaults();
    }
    return isset($this->settings[$key]) ? $this->settings[$key] : NULL;
  }

  /**
   * Attach our validation.
   */
  public function processSettingsForm(&$element, FormStateInterface $form_state, &$complete_form) {
    $complete_form['#validate'][] = array($this, 'validateSettingsForm');
    return $element;
  }

  /**
   * Validate settings form.
   */
  public function validateSettingsForm(array &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue(['filters', 'filter_spamspan', 'settings']);
    $use_form = $settings['use_form'];

    // No trees, see https://www.drupal.org/node/2378437.
    unset($settings['use_form']);
    $settings += $use_form;
    $form_state->setValue(['filters', 'filter_spamspan', 'settings'], $settings);
  }

}
