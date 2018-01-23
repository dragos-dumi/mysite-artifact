<?php

namespace Drupal\Tests\search_api_autocomplete\FunctionalJavascript;

use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Element\NodeElement;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Tests\TestsHelper;
use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;

/**
 * Tests the functionality of the whole module from a user's perspective.
 *
 * @group search_api_autocomplete
 */
class IntegrationTest extends IntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_test',
    'search_api_autocomplete_test',
  ];

  /**
   * The ID of the search index used in this test.
   *
   * @var string
   */
  protected $indexId = 'autocomplete_search_index';

  /**
   * The ID of the search entity created for this test.
   *
   * @var string
   */
  protected $searchId = 'search_api_autocomplete_test_view';

  /**
   * An admin user used for the tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A normal (non-admin) user used for the tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $normalUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'administer search_api',
      'administer search_api_autocomplete',
      'administer permissions',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);

    $this->normalUser = $this->drupalCreateUser();
  }

  /**
   * Tests the complete functionality of the module via the UI.
   */
  public function testModule() {
    $this->drupalLogin($this->adminUser);

    $this->enableSearch();
    $this->configureSearch();
    $this->checkEntityDependencies();
    $this->checkSearchAutocomplete();
    $this->checkSearchAutocomplete(TRUE);
    $this->checkCustomAutocompleteScript();
    $this->checkHooks();
    $this->checkPluginCacheClear();
    $this->checkAutocompleteAccess();
    $this->checkAdminAccess();
  }

  /**
   * Goes to the index's "Autocomplete" tab and creates/enables the test search.
   */
  protected function enableSearch() {
    $assert_session = $this->assertSession();

    // Make test suggester incompatible with all searches to test the "No
    // suggesters available" message.
    $callback = [TestsHelper::class, 'returnFalse'];
    $this->setMethodOverride('suggester', 'supportsSearch', $callback);

    $this->drupalGet($this->getAdminPath());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('There are currently no suggester plugins installed that support this index.');

    // Make it compatible with all searches again and refresh page.
    $this->setMethodOverride('suggester', 'supportsSearch', NULL);

    $this->getSession()->reload();
    $this->logPageChange();
    $assert_session->statusCodeEquals(200);

    // Check whether all expected groups and searches are present.
    $assert_session->pageTextContains('Search views');
    $assert_session->pageTextContains('Searches provided by Views');
    $assert_session->pageTextContains('Search API Autocomplete Test view');
    $assert_session->pageTextContains('Test search');
    $assert_session->pageTextContains('Autocomplete test module search');

    // Enable all Views searches (just one).
    $assert_session->checkboxNotChecked("searches[{$this->searchId}]");
    $this->click('table[data-drupal-selector="edit-search-views-searches"] > thead > tr > th.select-all input.form-checkbox');
    $assert_session->checkboxChecked("searches[{$this->searchId}]");

    $this->click('[data-drupal-selector="edit-submit"]');
    $this->logPageChange(NULL, 'POST');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The settings have been saved. Please remember to set the permissions for the newly enabled searches.');
  }

  /**
   * Configures the test search via the UI.
   */
  protected function configureSearch() {
    $assert_session = $this->assertSession();

    $this->click('.dropbutton-action a[href$="/edit"]');
    $this->logPageChange();
    $assert_session->statusCodeEquals(200);
    $assert_session->addressEquals($this->getAdminPath('edit'));

    // The "Server" suggester shouldn't be available at that point.
    $assert_session->elementExists('css', 'input[name="suggesters[enabled][search_api_autocomplete_test]"]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][server]"]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][custom_script]"]');

    // Make the test backend support autocomplete so that the "Server" suggester
    // becomes available.
    $callback = [TestsHelper::class, 'getSupportedFeatures'];
    $this->setMethodOverride('backend', 'getSupportedFeatures', $callback);
    $callback = [TestsHelper::class, 'getAutocompleteSuggestions'];
    $this->setMethodOverride('backend', 'getAutocompleteSuggestions', $callback);

    // After refreshing, the "Server" suggester should now be available. But by
    // default, only our test suggester should be enabled (since it was the only
    // option before).
    $this->getSession()->reload();
    $this->logPageChange();
    $assert_session->checkboxChecked('suggesters[enabled][search_api_autocomplete_test]');
    $assert_session->checkboxNotChecked('suggesters[enabled][server]');
    $assert_session->elementNotExists('css', 'input[name="suggesters[enabled][custom_script]"]');

    // The "Server" suggester's config form is hidden by default, but displayed
    // once we check its "Enabled" checkbox.
    $this->assertNotVisible('css', 'details[data-drupal-selector="edit-suggesters-settings-server"]');
    $this->click('input[name="suggesters[enabled][server]"]');
    $this->assertVisible('css', 'details[data-drupal-selector="edit-suggesters-settings-server"]');

    // Submit the form with some values for all fields.
    $edit = [
      'suggesters[weights][search_api_autocomplete_test][limit]' => '3',
      'suggesters[weights][server][limit]' => '3',
      'suggesters[weights][search_api_autocomplete_test][weight]' => '0',
      'suggesters[weights][server][weight]' => '10',
      'suggesters[settings][server][fields][name]' => FALSE,
      'suggesters[settings][server][fields][body]' => TRUE,
      'search_settings[displays][selected][default]' => FALSE,
      'options[limit]' => '5',
      'options[min_length]' => '2',
      'options[show_count]' => TRUE,
      'options[delay]' => '1000',
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Verifies that the search entity's dependencies were calculated correctly.
   */
  protected function checkEntityDependencies() {
    /** @var \Drupal\search_api_autocomplete\SearchInterface $search */
    $search = Search::load($this->searchId);
    $expected = [
      'config' => [
        'search_api.index.autocomplete_search_index',
        "views.view.{$this->searchId}",
      ],
      'module' => [
        'search_api',
        'search_api_autocomplete_test',
      ],
    ];
    $this->assertEquals($expected, $search->getDependencies());
  }

  /**
   * Tests autocompletion in the search form.
   *
   * @param bool $click_url_suggestion
   *   (optional) TRUE to click the URL-based suggestion, FALSE to click one of
   *   the "normal" search keys suggestions.
   */
  protected function checkSearchAutocomplete($click_url_suggestion = FALSE) {
    $assert_session = $this->assertSession();

    $this->drupalGet('search-api-autocomplete-test');
    $assert_session->statusCodeEquals(200);

    $assert_session->elementAttributeContains('css', 'input[data-drupal-selector="edit-keys"]', 'data-search-api-autocomplete-search', $this->searchId);

    $elements = $this->getAutocompleteSuggestions();
    $suggestions = [];
    $suggestion_elements = [];
    foreach ($elements as $element) {
      $label = $this->getElementText($element, '.autocomplete-suggestion-label');
      $user_input = $this->getElementText($element, '.autocomplete-suggestion-user-input');
      $suffix = $this->getElementText($element, '.autocomplete-suggestion-suggestion-suffix');
      $count = $this->getElementText($element, '.autocomplete-suggestion-results-count');
      $keys = $label . $user_input . $suffix;
      $suggestions[] = [
        'keys' => $keys,
        'count' => $count,
      ];
      $suggestion_elements[$keys] = $element;
    }
    $expected = [
      [
        'keys' => 'test-suggester-1',
        'count' => 1,
      ],
      [
        'keys' => 'test-suggester-2',
        'count' => 2,
      ],
      [
        'keys' => 'test-suggester-url',
        'count' => NULL,
      ],
      [
        'keys' => 'test-backend-1',
        'count' => 1,
      ],
      [
        'keys' => 'test-backend-2',
        'count' => 2,
      ],
    ];
    $this->assertEquals($expected, $suggestions);

    /** @var \Drupal\search_api\Query\QueryInterface $query */
    list($query) = $this->getMethodArguments('backend', 'getAutocompleteSuggestions');
    $this->assertEquals(['body'], $query->getFulltextFields());

    if ($click_url_suggestion) {
      // Click the URL suggestion and verify it correctly redirects the browser
      // to that URL.
      $suggestion_elements['test-suggester-url']->click();
      $this->logPageChange();
      $assert_session->addressEquals("/user/{$this->adminUser->id()}");
      return;
    }

    // Click one of the search key suggestions. The form should now auto-submit.
    $suggestion_elements['test-suggester-1']->click();
    $this->logPageChange();
    $assert_session->addressEquals('/search-api-autocomplete-test');
    $this->assertRegExp('#[?&]keys=test-suggester-1#', $this->getUrl());

    // Check that autocomplete in the "Name" filter works, too, and that it sets
    // the correct fields on the query.
    $this->getAutocompleteSuggestions('edit-name-value');
    list($query) = $this->getMethodArguments('suggester', 'getAutocompleteSuggestions');
    $this->assertEquals(['name'], $query->getFulltextFields());
  }

  /**
   * Retrieves autocomplete suggestions from a field on the current page.
   *
   * @param string $field_html_id
   *   (optional) The HTML ID of the field.
   * @param string $input
   *   (optional) The input to write into the field.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The suggestion elements from the page.
   */
  protected function getAutocompleteSuggestions($field_html_id = 'edit-keys', $input = 'test') {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $field = $assert_session->elementExists('css', "input[data-drupal-selector=\"$field_html_id\"]");
    $field->setValue(substr($input, 0, -1));
    $this->getSession()->getDriver()->keyDown($field->getXpath(), substr($input, -1));

    $element = $assert_session->waitOnAutocomplete();
    $this->assertTrue($element && $element->isVisible());
    $this->logPageChange();

    // Contrary to documentation, this can also return NULL. Therefore, we need
    // to make sure to return an array even in this case.
    return $page->findAll('css', '.ui-autocomplete .ui-menu-item') ?: [];
  }

  /**
   * Retrieves the text contents of a descendant of the given element.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element.
   * @param string $css_selector
   *   The CSS selector defining the descendant to look for.
   *
   * @return string|null
   *   The text contents of the descendant, or NULL if it couldn't be found.
   */
  protected function getElementText(NodeElement $element, $css_selector) {
    $element = $element->find('css', $css_selector);
    return $element ? $element->getText() : NULL;
  }

  /**
   * Tests whether using a custom autocomplete script is properly supported.
   *
   * @see \Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\CustomScript
   */
  protected function checkCustomAutocompleteScript() {
    $assert_session = $this->assertSession();

    \Drupal::configFactory()
      ->getEditable('search_api_autocomplete.settings')
      ->set('enable_custom_scripts', TRUE)
      ->save();

    $this->drupalGet($this->getAdminPath('edit'));
    $path = '/' . drupal_get_path('module', 'search_api_autocomplete_test');
    $path .= '/core/custom_autocomplete_script.php';
    $edit = [
      'suggesters[enabled][custom_script]' => TRUE,
      'suggesters[settings][custom_script][path]' => $path,
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('search-api-autocomplete-test');
    $assert_session->statusCodeEquals(200);

    $assert_session->elementAttributeContains('css', 'input[data-drupal-selector="edit-keys"]', 'data-search-api-autocomplete-search', $this->searchId);

    $elements = $this->getAutocompleteSuggestions();
    $this->assertCount(4, $elements);
    $suggestions = [];
    foreach ($elements as $element) {
      $suggestions[] = $element->getText();
    }
    sort($suggestions);
    $expected = [
      'display: page',
      'filter: keys',
      'q: test',
      "search_api_autocomplete_search: {$this->searchId}",
    ];
    $this->assertEquals($expected, $suggestions, 'Unexpected suggestions returned by custom script.');

    $this->drupalGet($this->getAdminPath('edit'));
    $edit = [
      'suggesters[enabled][custom_script]' => FALSE,
      'suggesters[settings][custom_script][path]' => '',
    ];
    $this->submitForm($edit, 'Save');
  }

  /**
   * Checks that the module's hooks work as expected.
   */
  protected function checkHooks() {
    $assert_session = $this->assertSession();

    \Drupal::getContainer()->get('module_installer')->install([
      'search_api_autocomplete_test_hooks',
    ]);

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextContains('The Siren');
    $assert_session->pageTextContains('Planet Hell');
    $assert_session->pageTextNotContains('Search views');
    $assert_session->pageTextNotContains('Searches provided by Views');

    $this->drupalGet($this->getAdminPath('edit'));
    $assert_session->pageTextContains('Wish I Had an Angel');
    $assert_session->pageTextNotContains('Test suggester');

    $this->drupalGet('search-api-autocomplete-test');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains("Creek Mary's Blood");

    $autocomplete_path = "search_api_autocomplete/{$this->searchId}";
    $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
    $assert_session->responseContains('dark chest of wonders');

    \Drupal::getContainer()->get('module_installer')->uninstall([
      'search_api_autocomplete_test_hooks',
    ]);
  }

  /**
   * Verifies that creating or deleting a view clears the search plugin cache.
   */
  protected function checkPluginCacheClear() {
    $assert_session = $this->assertSession();
    $new_view_label = 'Search plugin cache test';

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextNotContains($new_view_label);

    $view = View::load('search_api_autocomplete_test_view')->createDuplicate();
    $view->set('id', 'search_plugin_cache_test');
    $view->set('label', $new_view_label);
    $display = $view->get('display');
    $display['page']['display_options']['path'] = 'some/new/path';
    $view->set('display', $display);
    $view->save();

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextContains($new_view_label);

    $view->delete();

    $this->drupalGet($this->getAdminPath());
    $assert_session->pageTextNotContains($new_view_label);
  }

  /**
   * Verifies that autocomplete is only applied after access checks.
   */
  protected function checkAutocompleteAccess() {
    $assert_session = $this->assertSession();

    // Make sure autocomplete functionality is only available for users with the
    // right permission.
    $users = [
      'non-admin' => $this->normalUser,
      'anonymous' => NULL,
    ];
    $permission = "use search_api_autocomplete for {$this->searchId}";
    $autocomplete_path = "search_api_autocomplete/{$this->searchId}";
    foreach ($users as $user_type => $account) {
      $this->drupalLogout();
      if ($account) {
        $this->drupalLogin($account);
      }

      $this->drupalGet('search-api-autocomplete-test');
      $assert_session->statusCodeEquals(200);
      $element = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-keys"]');
      $this->assertFalse($element->hasAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertFalse($element->hasClass('form-autocomplete'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");

      $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
      $assert_session->statusCodeEquals(403);

      $rid = $account ? 'authenticated' : 'anonymous';
      $role = Role::load($rid);
      $role->grantPermission($permission);
      $role->save();

      $this->drupalGet('search-api-autocomplete-test');
      $assert_session->statusCodeEquals(200);
      $element = $assert_session->elementExists('css', 'input[data-drupal-selector="edit-keys"]');
      $this->assertTrue($element->hasAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertContains($this->searchId, $element->getAttribute('data-search-api-autocomplete-search'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");
      $this->assertTrue($element->hasClass('form-autocomplete'), "Autocomplete should not be enabled for $user_type user without the necessary permission.");

      $this->drupalGet($autocomplete_path, ['query' => ['q' => 'test']]);
      $assert_session->statusCodeEquals(200);
    }
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Verifies that admin pages are properly protected.
   */
  protected function checkAdminAccess() {
    // Make sure anonymous and non-admin users cannot access admin pages.
    $users = [
      'non-admin' => $this->normalUser,
      'anonymous' => NULL,
    ];
    $paths = [
      'index overview' => $this->getAdminPath(),
      'search edit form' => $this->getAdminPath('edit'),
      'search delete form' => $this->getAdminPath('delete'),
    ];
    foreach ($users as $user_type => $account) {
      $this->drupalLogout();
      if ($account) {
        $this->drupalLogin($account);
      }
      foreach ($paths as $label => $path) {
        $this->drupalGet($path);
        $status_code = $this->getSession()->getStatusCode();
        $this->assertEquals(403, $status_code, "The $label is accessible for $user_type users.");
      }
    }
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Returns the path of an admin page.
   *
   * @param string|null $page
   *   (optional) Either "edit" or "delete" to get the path of the respective
   *   search form, or NULL for the index's "Autocomplete" tab.
   * @param string|null $search_id
   *   (optional) The ID of the search to link to, if a page is specified. NULL
   *   to use the default search used by this test.
   *
   * @return string
   *   The internal path to the specified page.
   */
  protected function getAdminPath($page = NULL, $search_id = NULL) {
    $path = 'admin/config/search/search-api/index/autocomplete_search_index/autocomplete';
    if ($page !== NULL) {
      if ($search_id === NULL) {
        $search_id = $this->searchId;
      }
      $path .= "/$search_id/$page";
    }
    return $path;
  }

  /**
   * Logs a page change, if HTML output logging is enabled.
   *
   * The base class only logs requests when the drupalGet() or drupalPost()
   * methods are used, so we need to implement this ourselves for other page
   * changes.
   *
   * To enable HTML output logging, create some file where links to the logged
   * pages should be placed and set the "BROWSERTEST_OUTPUT_FILE" environment
   * variable to that file's path.
   *
   * @param string|null $url
   *   (optional) The URL requested, if not the current URL.
   * @param string $method
   *   (optional) The HTTP method used for the request.
   *
   * @see \Drupal\Tests\BrowserTestBase::drupalGet()
   * @see \Drupal\Tests\BrowserTestBase::setUp()
   */
  protected function logPageChange($url = NULL, $method = 'GET') {
    $session = $this->getSession();
    $driver = $session->getDriver();
    if (!$this->htmlOutputEnabled || $driver instanceof GoutteDriver) {
      return;
    }
    $current_url = $session->getCurrentUrl();
    $url = $url ?: $current_url;
    $html_output = "$method request to: $url<hr />Ending URL: $current_url";
    $html_output .= '<hr />' . $session->getPage()->getContent();;
    $html_output .= $this->getHtmlOutputHeaders();
    $this->htmlOutput($html_output);
  }

  /**
   * Asserts that the specified element exists and is visible.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   *   Thrown if the element doesn't exist.
   */
  protected function assertVisible($selector_type, $selector) {
    $element = $this->assertSession()->elementExists($selector_type, $selector);
    $this->assertTrue($element->isVisible(), "Element should be visible but isn't.");
  }

  /**
   * Asserts that the specified element exists but is not visible.
   *
   * @param string $selector_type
   *   The element selector type (CSS, XPath).
   * @param string|array $selector
   *   The element selector. Note: the first found element is used.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   *   Thrown if the element doesn't exist.
   */
  protected function assertNotVisible($selector_type, $selector) {
    $element = $this->assertSession()->elementExists($selector_type, $selector);
    $this->assertFalse($element->isVisible(), "Element shouldn't be visible but is.");
  }

}
