<?php

namespace Drupal\geofield;

interface DmsConverterInterface {
  /**
   * Transforms a DMS point to a decimal one
   *
   * @param \Drupal\geofield\DmsPoint $point
   *   The DMS Point to transform.
   *
   * @return array
   *   The equivalent Decimal Point array.
   */
  public static function dmsToDecimal(DmsPoint $point);

  /**
   * @param float $lon
   *   The Decimal Point to transform longitude.
   * @param float $lat
   *   The Decimal Point to transform latitude.
   *
   * @return \Drupal\geofield\DmsPoint
   *   The equivalent DMS Point object.
   */
  public static function decimalToDms($lon, $lat);

}
