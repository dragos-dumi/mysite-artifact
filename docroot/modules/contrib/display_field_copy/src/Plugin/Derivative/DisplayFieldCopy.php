<?php

namespace Drupal\display_field_copy\Plugin\Derivative;

use Drupal\display_field_copy\Form\DisplayFieldCopyForm;
use Drupal\ds\Plugin\Derivative\DynamicField;

/**
 * Retrieves dynamic ds field plugin definitions.
 */
class DisplayFieldCopy extends DynamicField {

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return DisplayFieldCopyForm::TYPE;
  }

}
