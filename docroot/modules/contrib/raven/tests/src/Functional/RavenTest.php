<?php

namespace Drupal\Tests\raven\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Raven module.
 *
 * @group raven
 */
class RavenTest extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['raven'];

  /**
   * Tests Raven module configuration UI.
   */
  function testRavenConfig() {
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);
    $this->drupalPostForm('admin/config/development/raven', [], t('Save configuration'));
  }

}
