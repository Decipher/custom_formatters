<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests access control and edge cases for formatter setting entities.
 *
 * @group custom_formatters
 */
class FormatterSettingAccessControlTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * A user with admin permission.
   */
  private AccountInterface $adminUser;

  /**
   * A user without admin permission.
   */
  private AccountInterface $regularUser;

  /**
   * A test formatter setting entity.
   */
  private FormatterSetting $setting;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('formatter_setting');
    $this->installEntitySchema('user');
    $this->installConfig(['custom_formatters', 'filter']);

    $adminUser = $this->createUser(['administer custom formatters']);
    \assert($adminUser instanceof AccountInterface);
    $this->adminUser = $adminUser;
    $regularUser = $this->createUser([]);
    \assert($regularUser instanceof AccountInterface);
    $this->regularUser = $regularUser;

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'access_test',
        'label' => 'Access Test',
        'type' => 'html_token',
        'data' => '[node:title]',
      ]);
    \assert($formatter instanceof FormatterInterface);
    $formatter->save();

    $this->setting = FormatterSetting::create([
      'formatter' => 'access_test',
      'label' => 'Test setting',
    ]);
    $this->setting->save();
  }

  /**
   * Tests that admin users can view a formatter setting entity.
   */
  public function testAdminCanView(): void {
    $access = $this->setting->access('view', $this->adminUser, TRUE);
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that non-admin users cannot view a formatter setting entity.
   */
  public function testNonAdminCannotView(): void {
    $access = $this->setting->access('view', $this->regularUser, TRUE);
    $this->assertTrue($access->isNeutral(), 'Non-admin should get neutral access.');
  }

  /**
   * Tests that admin users can update a formatter setting entity.
   */
  public function testAdminCanUpdate(): void {
    $access = $this->setting->access('update', $this->adminUser, TRUE);
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that admin users can delete a formatter setting entity.
   */
  public function testAdminCanDelete(): void {
    $access = $this->setting->access('delete', $this->adminUser, TRUE);
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that admin users can create formatter setting entities.
   */
  public function testAdminCanCreate(): void {
    $access = $this->setting->access('create', $this->adminUser, TRUE);
    $this->assertTrue($access->isAllowed());
  }

  /**
   * Tests that non-admin users cannot create formatter setting entities.
   */
  public function testNonAdminCannotCreate(): void {
    $access = $this->setting->access('create', $this->regularUser, TRUE);
    $this->assertTrue($access->isNeutral(), 'Non-admin should get neutral access.');
  }

}
