<?php

namespace Drupal\Tests\raven\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;

/**
 * Tests Raven module.
 *
 * @group raven
 */
class RavenTest extends JavascriptTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['raven'];

  /**
   * Tests Raven.js configuration UI.
   */
  function testRavenJavascriptConfig() {
    $admin_user = $this->drupalCreateUser(['administer site configuration', 'send javascript errors to sentry']);
    $this->drupalLogin($admin_user);
    $this->drupalPostForm('admin/config/development/raven', ['javascript_error_handler' => TRUE], t('Save configuration'));
  }

}
