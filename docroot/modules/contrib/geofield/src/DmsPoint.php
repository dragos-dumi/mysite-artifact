<?php

namespace Drupal\geofield;

/**
 * Helper class to map DMS Point structure.
 */
class DmsPoint {

  /**
   * @var array
   *   The longitude component.
   */
  protected $lon;

  /**
   * @var array
   *   The latitude component.
   */
  protected $lat;

  /**
   * DmsPoint constructor.
   * @param array $lon
   *   The longitude components.
   * @param array $lat
   *   The latitde components.
   */
  public function __construct(array $lon, array $lat) {
    $this->lat = $lat;
    $this->lon = $lon;
  }

  /**
   * Retrieves an object property.
   *
   * @param $property
   *   The property to get
   *
   * @return array|null
   *   The property if exists, otherwise NULL.
   */
  public function get($property) {
    return isset($this->{$property}) ? $this->{$property} : NULL;
  }

  /**
   * @return array
   */
  public function getLon() {
    return $this->lon;
  }

  /**
   * @param array $lon
   */
  public function setLon($lon) {
    $this->lon = $lon;
  }

  /**
   * @return array
   */
  public function getLat() {
    return $this->lat;
  }

  /**
   * @param array $lat
   */
  public function setLat($lat) {
    $this->lat = $lat;
  }

}