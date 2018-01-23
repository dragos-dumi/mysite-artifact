<?php

namespace Drupal\geofield\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the GeoType constraint.
 */
class GeoConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (isset($value)) {
      $valid_geometry = TRUE;

      try {
        if (!\Drupal::service('geofield.geophp')->load($value, 'wkt')) {
          $valid_geometry = FALSE;
        }
      }
      catch (\Exception $e) {
        $valid_geometry = FALSE;
      }

      if (!$valid_geometry) {
        $this->context->addViolation($constraint->message, ['@value' => $value]);
      }
    }
  }

}
