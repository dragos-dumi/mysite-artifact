<?php
/**
 * @file
 * Contains \Drupal\inline_responsive_images\Form\ResponsiveEditorImageDialog.
 */

namespace Drupal\inline_responsive_images\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\editor\Form\EditorImageDialog;

/**
 * Provides an image dialog for text editors.
 */
class ResponsiveEditorImageDialog extends EditorImageDialog{

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    
    // Convert any uploaded files from the FID values to data-entity-uuid
    // attributes and set data-entity-type to 'file'.
    $fid = $form_state->getValue(array('fid', 0));
    if (!empty($fid)) {
      $file = $this->fileStorage->load($fid);
      $file_url = file_create_url($file->getFileUri());
      // Transform absolute image URLs to relative image URLs: prevent problems
      // on multisite set-ups and prevent mixed content errors.
      $file_url = file_url_transform_relative($file_url);
      $form_state->setValue(array('attributes', 'src'), $file_url);
      $form_state->setValue(array('attributes', 'data-entity-uuid'), $file->uuid());
      $form_state->setValue(array('attributes', 'data-entity-type'), 'file');
    }

    // When the alt attribute is set to two double quotes, transform it to the
    // empty string: two double quotes signify "empty alt attribute". See above.
    if (trim($form_state->getValue(array('attributes', 'alt'))) === '""') {
      $form_state->setValue(array('attributes', 'alt'), '');
    }

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#editor-image-dialog-form', $form));
    }
    else {
      $response->addCommand(new EditorDialogSave($form_state->getValues()));
      $response->addCommand(new CloseModalDialogCommand());
    }

    return $response;
  }  
  
}