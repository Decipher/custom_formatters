<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the formatter_setting schema is installed on upgrade.
 *
 * @group custom_formatters
 */
class FormatterSettingUpdatePathTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Include the install file so update/install functions are available.
    require_once \Drupal::service('extension.path.resolver')
      ->getPath('module', 'custom_formatters') . '/custom_formatters.install';
  }

  /**
   * Tests that update_8401 installs the missing formatter_setting schema.
   */
  public function testUpdate8401InstallsFormatterSettingSchema(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    $this->assertNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should not be installed before running update_8401.'
    );

    \custom_formatters_update_8401();

    $this->assertNotNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should be installed after running update_8401.'
    );
  }

  /**
   * Tests that update_8402 installs the missing formatter_setting schema.
   *
   * Simulates a site that already ran the broken update_8401 (which did
   * nothing), leaving the schema uninstalled. update_8402 retroactively
   * installs it.
   */
  public function testUpdate8402InstallsFormatterSettingSchema(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    // Simulate a pre-formatter_setting site: the entity type is not installed.
    $this->assertNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should not be installed before running update_8402.'
    );

    // Run the update hook.
    \custom_formatters_update_8402();

    // The entity type should now be installed.
    $this->assertNotNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should be installed after running update_8402.'
    );

    // The base table should exist.
    $connection = \Drupal::database();
    $this->assertTrue(
      $connection->schema()->tableExists('formatter_setting'),
      'formatter_setting base table should exist after update_8402.'
    );
  }

  /**
   * Tests that update_8402 is idempotent.
   */
  public function testUpdate8402IsIdempotent(): void {
    // Run update_8402 once — installs the schema.
    \custom_formatters_update_8402();

    // Run it again — should not throw or error.
    \custom_formatters_update_8402();

    $update_manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertNotNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should still be installed after a second update_8402.'
    );
  }

  /**
   * Tests that custom_formatters_install installs the schema.
   */
  public function testInstallHookInstallsFormatterSettingSchema(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    $this->assertNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should not be installed before hook_install.'
    );

    // Call the install hook directly.
    \custom_formatters_install();

    $this->assertNotNull(
      $update_manager->getEntityType('formatter_setting'),
      'formatter_setting should be installed after hook_install.'
    );

    $this->assertTrue(
      \Drupal::database()->schema()->tableExists('formatter_setting'),
      'formatter_setting base table should exist after hook_install.'
    );
  }

}
