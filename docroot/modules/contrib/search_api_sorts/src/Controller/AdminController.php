<?php

namespace Drupal\search_api_sorts\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_sorts\ConfigIdEscapeTrait;

/**
 * Class AdminController.
 *
 * @package Drupal\search_api_sorts\Controller
 */
class AdminController extends ControllerBase {
  use ConfigIdEscapeTrait;

  /**
   * Overview of search api displays to choose to manage sort fields for.
   */
  public function displayListing(IndexInterface $search_api_index) {

    $rows = [];
    /** @var \Drupal\search_api\Display\DisplayPluginManager $sapi_display_manager */
    $sapi_display_manager = \Drupal::service('plugin.manager.search_api.display');
    foreach ($sapi_display_manager->getInstances() as $display) {
      if ($search_api_index == $display->getIndex()) {

        $row = [];
        $row['display'] = $display->label();
        $row['description'] = $display->getDescription();
        $search_api_display = $display->getPluginId();
        $escaped_search_api_display = $this->getEscapedConfigId($search_api_display);

        $links['configure'] = [
          'title' => $this->t('Manage sort fields'),
          'url' => Url::fromRoute('search_api_sorts.search_api_display.sorts', [
            'search_api_index' => $search_api_index->id(),
            'search_api_display' => $escaped_search_api_display,
          ]),
        ];

        $row['operations']['data'] = [
          '#type' => 'operations',
          '#links' => $links,
        ];
        $rows[] = $row;
      }
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Display'), $this->t('Description'), $this->t('Operations')],
      '#title' => $this->t('Sorts configuration.'),
      '#rows' => $rows,
      '#empty' => $this->t('You have no search displays defined yet. An example of a display is a views page using this index, or a search api pages page.'),
    ];

    return $build;
  }

}
