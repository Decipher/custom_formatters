<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the FormatterSetting entity form save and redirect behavior.
 *
 * @group custom_formatters
 */
class FormatterSettingFormTest extends CustomFormattersTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer custom formatters',
      'administer formatter_setting fields',
      'administer formatter_setting form display',
      'administer formatter_setting display',
    ]);
    $this->assertNotFalse($admin);
    $this->drupalLogin($admin);
  }

  /**
   * Tests updating an existing FormatterSetting entity.
   */
  public function testFormatterSettingUpdateForm(): void {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);
    $formatter_id = $formatter->id();

    FieldStorageConfig::create([
      'field_name' => 'field_edit_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_edit_text',
      'entity_type' => 'formatter_setting',
      'bundle' => $formatter_id,
      'label' => 'Edit text',
    ])->save();

    EntityFormDisplay::create([
      'targetEntityType' => 'formatter_setting',
      'bundle' => $formatter_id,
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'label' => [
          'type' => 'string_textfield',
          'weight' => 0,
          'region' => 'content',
          'settings' => ['size' => 60, 'placeholder' => ''],
          'third_party_settings' => [],
        ],
        'field_edit_text' => [
          'type' => 'string_textfield',
          'weight' => 1,
          'region' => 'content',
          'settings' => ['size' => 60, 'placeholder' => ''],
          'third_party_settings' => [],
        ],
      ],
    ])->save();

    $setting = FormatterSetting::create([
      'formatter' => $formatter_id,
      'label' => 'Original label',
      'field_edit_text' => 'Original value',
    ]);
    $setting->save();

    $this->drupalGet($setting->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Save');

    $this->submitForm([
      'label[0][value]' => 'Updated label',
      'field_edit_text[0][value]' => 'Updated value',
    ], 'Save');

    $this->assertSession()->pageTextContains('Updated Updated label.');
    $this->assertSession()->addressEquals('admin/structure/formatters/manage/' . $formatter_id);

    $setting = \Drupal::entityTypeManager()
      ->getStorage('formatter_setting')
      ->loadUnchanged($setting->id());
    $this->assertInstanceOf(FormatterSetting::class, $setting);
    $this->assertEquals('Updated label', $setting->label());
    $this->assertEquals('Updated value', $setting->get('field_edit_text')->value);
  }

}
