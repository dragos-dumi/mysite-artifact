<?php

namespace Drupal\Tests\search_api_sorts\Functional;

use Drupal\Core\Url;

/**
 * Tests the default functionality of Search API sorts.
 *
 * @group search_api_sorts
 */
class IntegrationTest extends SortsFunctionalBase {

  /**
   * Tests sorting.
   */
  public function testFramework() {
    $this->drupalLogin($this->adminUser);

    // Add sorting on ID.
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/sorts');
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/sorts/' . $this->escapedDisplayId);
    $edit = [
      'sorts[id][status]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save settings');

    // Check for non-existence of the block first.
    $this->drupalGet('search-api-sorts-test');
    $this->assertSession()->linkNotExists('ID');

    $block_settings = [
      'region' => 'footer',
      'id' => 'sorts_id',
    ];
    $this->drupalPlaceBlock('search_api_sorts_block:' . $this->displayId, $block_settings);

    // Make sure the block is available and the ID link is shown, check that the
    // sorting applied is in alphabetical order.
    $this->drupalGet('search-api-sorts-test');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('ID');
    $this->assertPositions([
      'default | foo bar baz foobaz föö',
      'default | foo test foobuz',
      'default | foo baz',
      'default | bar baz',
    ]);

    // Click on the link and assert that the url now has changed, also check
    // that the sort order is still the same.
    $this->clickLink('ID');
    $this->assertSession()->statusCodeEquals(200);
    $url = Url::fromUserInput('/search-api-sorts-test', ['query' => ['sort' => 'id', 'order' => 'asc']]);
    $this->assertUrl($url);
    $this->assertPositions([
      'default | foo bar baz foobaz föö',
      'default | foo test foobuz',
      'default | foo baz',
      'default | bar baz',
    ]);

    // Click on the link again and assert that the url is now changed again and
    // that the sort order now also has changed.
    $this->clickLink('ID');
    $this->assertSession()->statusCodeEquals(200);
    $url = Url::fromUserInput('/search-api-sorts-test', ['query' => ['sort' => 'id', 'order' => 'desc']]);
    $this->assertUrl($url);
    $this->assertPositions([
      'default | bar baz',
      'default | foo baz',
      'default | foo test foobuz',
      'default | foo bar baz foobaz föö',
    ]);
  }

}
