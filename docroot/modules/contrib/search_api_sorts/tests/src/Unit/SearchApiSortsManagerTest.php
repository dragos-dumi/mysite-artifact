<?php

namespace Drupal\Tests\search_api_sorts\Unit;

use Drupal\search_api_sorts\Entity\SearchApiSortsField;
use Drupal\search_api_sorts\SearchApiSortsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the sorts manager.
 *
 * @group search_api_sorts
 */
class SearchApiSortsManagerTest extends \PHPUnit_Framework_TestCase {

  /**
   * Tests getActiveSort.
   *
   * @dataProvider provideSortOrders
   */
  public function testGetActiveSort($order_argument, $expected) {
    $request_stack = new RequestStack();
    $request = new Request(['sort' => 'sort_field', 'order' => $order_argument]);
    $request_stack->push($request);

    $display = $this->getMockBuilder('\Drupal\search_api\Display\DisplayInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $module_handler = $this->getMockBuilder('Drupal\Core\Extension\ModuleHandlerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $manager = $this->getMockBuilder('\Drupal\Core\Entity\EntityTypeManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $sut = new SearchApiSortsManager($request_stack, $manager, $module_handler);
    $sorts = $sut->getActiveSort($display);
    $this->assertEquals('sort_field', $sorts->getFieldName());
    $this->assertEquals($expected, $sorts->getOrder());
  }

  /**
   * Tests getEnabledSorts.
   */
  public function testGetEnabledSorts() {
    $sorts_field = new SearchApiSortsField(['id' => 'test'], 'search_api_sorts_field');

    $request_stack = new RequestStack();
    $request = new Request();
    $request_stack->push($request);

    $index = $this->getMockBuilder('\Drupal\search_api\Display\DisplayInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $module_handler = $this->getMockBuilder('Drupal\Core\Extension\ModuleHandlerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $storage = $this->getMockBuilder('\Drupal\Core\Entity\EntityStorageInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $storage->expects($this->once())
      ->method('loadByProperties')
      ->willReturn($sorts_field);
    $manager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $manager->expects($this->once())
      ->method('getStorage')
      ->with('search_api_sorts_field')
      ->willReturn($storage);

    $sut = new SearchApiSortsManager($request_stack, $manager, $module_handler);
    $enabled_sorts = $sut->getEnabledSorts($index);

    $this->assertEquals($sorts_field, $enabled_sorts);
  }

  /**
   * Provides mock data and expected results for ::testActiveSortOrder.
   *
   * @return array
   *   An array of mockable data.
   */
  public function provideSortOrders() {
    return [
      ['asc', 'asc'],
      ['desc', 'desc'],
      ['aaa', 'asc'],
      [NULL, 'asc'],
    ];
  }

}
