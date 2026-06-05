<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests Field UI integration for formatter settings.
 *
 * @group custom_formatters
 */
class FormatterSettingUiTest extends CustomFormattersTestBase {

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
      'administer node display',
    ]);
    $this->assertNotFalse($admin);
    $this->drupalLogin($admin);
  }

  /**
   * Test that Field UI operations appear on the formatter list page.
   */
  public function testFieldUiOperationsOnFormatterList() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);

    $this->drupalGet('admin/structure/formatters');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->linkByHrefExists('admin/structure/formatters/manage/' . $formatter->id() . '/fields');
    $this->assertSession()->linkExists('Manage fields');
  }

  /**
   * Test that the Manage fields page is accessible for a formatter.
   */
  public function testManageFieldsPageAccess() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);

    $this->drupalGet('admin/structure/formatters/manage/' . $formatter->id() . '/fields');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Manage fields');
  }

  /**
   * Test adding a field to a formatter via Field UI.
   */
  public function testAddFieldToFormatter() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);
    $formatter_id = $formatter->id();
    $bundle_path = 'admin/structure/formatters/manage/' . $formatter_id;

    $this->fieldUIAddNewField($bundle_path, 'test_setting', 'Test setting', 'text');

    $field_storage = FieldStorageConfig::loadByName('formatter_setting', 'field_test_setting');
    $this->assertNotNull($field_storage, 'Field storage was created.');

    $field_config = FieldConfig::loadByName('formatter_setting', (string) $formatter_id, 'field_test_setting');
    $this->assertNotNull($field_config, 'Field config was created for the formatter bundle.');

    $this->drupalGet($bundle_path . '/fields');
    $this->assertSession()->pageTextContains('field_test_setting');
  }

  /**
   * Test that field definitions are available in the formatter settings form.
   */
  public function testSettingsFormShowsFieldUiFields() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);
    $formatter_id = $formatter->id();

    FieldStorageConfig::create([
      'field_name' => 'field_custom_text',
      'type' => 'text',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_custom_text',
      'entity_type' => 'formatter_setting',
      'bundle' => $formatter_id,
      'label' => 'Custom text setting',
    ])->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('body', [
        'type' => 'custom_formatters:' . $formatter_id,
        'label' => 'hidden',
        'settings' => [
          'field_custom_text' => 'Hello world',
        ],
      ])
      ->save();

    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->responseContains('custom_formatters:' . $formatter_id);
  }

  /**
   * Test Field UI tabs appear on the formatter edit page.
   */
  public function testFieldUiTabsOnEditPage() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);

    $this->drupalGet('admin/structure/formatters/manage/' . $formatter->id());
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->linkExists('Manage fields');
    $this->assertSession()->linkByHrefExists('admin/structure/formatters/manage/' . $formatter->id() . '/fields');
  }

  /**
   * Tests that formatter setting field values render correctly in node output.
   *
   * Creates a Twig formatter with a settings field, assigns a saved
   * FormatterSetting entity to the field display, then verifies the rendered
   * node page contains the expected setting value.
   */
  public function testSettingsFieldsRenderInOutput(): void {
    $formatter = $this->createCustomFormatter([
      'type' => 'twig',
      'data' => '{% for item in items %}<span class="{{ settings.field_css_class|clean_class }}">{{ item.value }}</span>{% endfor %}',
      'field_types' => ['text_with_summary'],
    ]);
    $formatter_id = (string) $formatter->id();

    if (!FieldStorageConfig::loadByName('formatter_setting', 'field_css_class')) {
      FieldStorageConfig::create([
        'field_name' => 'field_css_class',
        'type' => 'string',
        'entity_type' => 'formatter_setting',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'field_css_class',
      'entity_type' => 'formatter_setting',
      'bundle' => $formatter_id,
      'label' => 'CSS class',
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'formatter_setting',
      'bundle' => $formatter_id,
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'field_css_class' => [
          'type' => 'string',
          'label' => 'hidden',
          'settings' => ['link_to_entity' => FALSE],
          'third_party_settings' => [],
          'weight' => 0,
          'region' => 'content',
        ],
      ],
    ])->save();

    $setting = FormatterSetting::create([
      'formatter' => $formatter_id,
      'label' => 'Test setting',
      'field_css_class' => 'my-custom-class',
    ]);
    $setting->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('body', [
        'type' => 'custom_formatters:' . $formatter_id,
        'label' => 'hidden',
        'settings' => [
          'formatter_setting_uuid' => $setting->uuid(),
        ],
      ])
      ->save();

    \Drupal::service('plugin.manager.field.formatter')->clearCachedDefinitions();

    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('my-custom-class');
  }

}
