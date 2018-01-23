<?php

namespace Drupal\geofield;

/**
 * Helper class to convert point object from one format to the other.
 */
class DmsConverter implements DmsConverterInterface {

  /**
   * {@inheritdoc}
   */
  public static function dmsToDecimal(DmsPoint $point) {
    $lon_data = $point->getLon();
    $lat_data = $point->getLat();
    $lon = $lon_data['degrees'] + ($lon_data['minutes'] / 60) + ($lon_data['seconds'] / 3600);
    $lat = $lat_data['degrees'] + ($lat_data['minutes'] / 60) + ($lat_data['seconds'] / 3600);

    $lon = ($lon_data['orientation'] == 'W') ? (-1 * $lon) : $lon;
    $lat = ($lat_data['orientation'] == 'S') ? (-1 * $lat) : $lat;

    return [$lon, $lat];
  }

  /**
   * {@inheritdoc}
   */
  public static function decimalToDms($lon, $lat) {
    $latDirection = $lat < 0 ? 'S': 'N';
    $lonDirection = $lon < 0 ? 'W': 'E';

    $latInDegrees = floor(abs($lat));
    $lonInDegrees = floor(abs($lon));

    $latDecimal = (abs($lat) - $latInDegrees) * 60;
    $lonDecimal = (abs($lon) - $lonInDegrees) * 60;

    $latMinutes = floor($latDecimal);
    $lonMinutes = floor($lonDecimal);

    $latDecimal = ($latDecimal - $latMinutes) * 60;
    $lonDecimal = ($lonDecimal - $lonMinutes) * 60;

    $latSeconds = round($latDecimal);
    $lonSeconds = round($lonDecimal);

    return new DmsPoint([
        'orientation' => $lonDirection,
        'degrees' => $lonInDegrees,
        'minutes' => $lonMinutes,
        'seconds' => $lonSeconds,
      ],
      [
        'orientation' => $latDirection,
        'degrees' => $latInDegrees,
        'minutes' => $latMinutes,
        'seconds' => $latSeconds,
      ]
    );
  }

}