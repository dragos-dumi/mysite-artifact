<?php


namespace Drupal\spamspan\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements an example form.
 */
class SpamspanTestForm extends FormBase {

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'spamspan_test_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //get defaults
    $plugin_manager = \Drupal::service('plugin.manager.filter');
    $configuration = $plugin_manager->getDefinition('filter_spamspan');
    $defaults = $configuration['settings'];
    $filter = $plugin_manager->createInstance('filter_spamspan', $configuration);


    $test_text = 'My work email is me@example.com and my home email is me@example.org.';

    $storage = $form_state->getStorage();

    if (isset($storage['test_text'])) {
      $test_text = $storage['test_text'];
    }

    $default_list = array();
    foreach ($defaults as $name => $value) {
      $default_list[] = $name . ': <strong>' . htmlentities($value) . '</strong>';
    }

    $form['configure'] = array('#markup' => $this->t(
      '<p>The @dn module obfuscates email addresses to help prevent spambots from collecting them.'
      . ' It will produce clickable links if JavaScript is enabled,'
      . ' and will show the email address as <code>example [at] example [dot] com</code> if the browser does not support JavaScript.</p>'
      . '<p>To configure the module:<ol>'
      . '<li>Read the list of text formats at <a href="/admin/config/content/formats">Text formats</a>.</li>'
      . '<li>Select <strong>configure</strong> for the format requiring email addresses.</li>'
      . '<li>In <strong>Enable filters</strong>, select <em>@dn email address encoding filter</em>.</li>'
      . '<li>In <strong>Filter processing order </strong>, move @dn below <em>Convert line breaks into HTML</em> and above <em>Convert URLs into links</em>.</li>'
      . '<li>If you use the <strong>Limit allowed HTML tags</strong> filter, make sure that &lt;span&gt; is one of the allowed tags.</li>'
      . '<li>Select <strong>@dn email address encoding filter</strong> to configure @dn for the text format.</li>'
      . '<li>Select <strong>Save configuration</strong> to save your changes.</li>'
      . '</ol></p>'
      . '<h2>Defaults</h2>'
      . '<p>The following defaults are used for new filters and for spamspan() when there is no filter specified.</p>'
      . '<ul><li>' . implode('</li><li>', $default_list) . '</li></ul>'
      . '<h2>Test spamspan()</h2>'
      . '<p>Test the @dn <code>spamspan()</code> function using the following <strong>Test text</strong> field.'
      . ' Enter text containing an email address then hit the Test button. We set up a default example to get you started.</p>',
      array('@dn' => 'Spamspan')
    ));

    $form['test_text'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Test text'),
      '#size' => 80,
      '#maxlength' => 200,
      '#default_value' => $test_text,
    );

    $settings_form = $filter->settingsForm([], $form_state);
    foreach ($defaults as $field => $value) {
      if (isset($settings_form['use_form'][$field])) {
        $form[$field] = $settings_form['use_form'][$field];
      }
      else {
        $form[$field] = $settings_form[$field];
      }
      if (isset($storage[$field])) {
        $form[$field]['#default_value'] = $defaults[$field] = $storage[$field];
      }
    }

    $test_result = spamspan($test_text, $defaults);
    $form['test_js'] = array('#markup' => '<p>The result passed through spamspan() and processed by Javasript:</p><div style="background-color: #ccffcc;">' . $test_result . '</div>');
    $form['test_result'] = array('#markup' => '<p>The result passed through spamspan() but not processed by Javascript:</p><div style="background-color: #ccccff;">' . str_replace('class="spamspan"', '', $test_result) . '</div>');
    $form['test_as_html'] = array('#markup' => '<p>The HTML in the result:</p><div style="background-color: #ffcccc;">' . nl2br(htmlentities($test_result)) . '</div>');
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Test'));

    $form['#attached']['library'][] = 'spamspan/obfuscate';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setStorage($form_state->getValues());
    $form_state->setRebuild();
  }

}