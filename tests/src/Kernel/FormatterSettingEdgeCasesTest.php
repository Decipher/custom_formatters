<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\custom_formatters\Form\FormatterForm;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\FormatterTypeBase;
use Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType\FormatterPreset;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests edge cases in formatter setting infrastructure.
 *
 * Covers buildSettingsReference(), urlRouteParameters(),
 * processSettingsFieldset(), and previewDebugData().
 *
 * @group custom_formatters
 */
class FormatterSettingEdgeCasesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
    'field_ui',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * The formatter entity.
   */
  private FormatterInterface $formatter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('formatter_setting');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['custom_formatters', 'filter']);

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'edge_test',
        'label' => 'Edge Test',
        'type' => 'html_token',
        'field_types' => ['text_with_summary', 'text_long'],
        'data' => '[formatter_setting:field_edge]',
      ]);
    \assert($formatter instanceof FormatterInterface);
    $formatter->save();
    $this->formatter = $formatter;

    NodeType::create(['type' => 'article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();
  }

  /**
   * Tests urlRouteParameters() includes the formatter bundle parameter.
   */
  public function testUrlRouteParameters(): void {
    $setting = FormatterSetting::create([
      'formatter' => 'edge_test',
      'label' => 'Route test',
    ]);
    $setting->save();

    $url = $setting->toUrl('edit-form');
    $path = $url->toString(TRUE)->getGeneratedUrl();
    $this->assertStringContainsString('/edge_test/', $path,
      'URL must contain the formatter bundle ID.'
    );
  }

  /**
   * Tests buildSettingsReference() returns empty when entity has no ID.
   */
  public function testBuildSettingsReferenceNoId(): void {
    $no_id_entity = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'no_id_test',
        'label' => 'No ID',
        'type' => 'html_token',
      ]);
    \assert($no_id_entity instanceof FormatterInterface);
    $type = $no_id_entity->getFormatterType();
    \assert($type instanceof FormatterTypeBase);

    $ref = new \ReflectionMethod(FormatterTypeBase::class, 'buildSettingsReference');
    $ref->setAccessible(TRUE);
    $result = $ref->invoke($type);
    $this->assertSame([], $result, 'buildSettingsReference should return empty when entity has no ID.');
  }

  /**
   * Tests buildSettingsReference() headers and content with fields.
   */
  public function testBuildSettingsReferenceWithFields(): void {
    $this->addSettingField();

    // Create a new plugin instance to reflect the new fields.
    $formatter_type = $this->formatter->getFormatterType();
    \assert($formatter_type instanceof FormatterTypeBase);

    $ref = new \ReflectionMethod(FormatterTypeBase::class, 'buildSettingsReference');
    $ref->setAccessible(TRUE);
    $result = $ref->invoke($formatter_type);

    $this->assertArrayHasKey('table', $result, 'Result must include a table.');
    $this->assertArrayHasKey('#rows', $result['table']);
    $this->assertCount(1, $result['table']['#rows']);
    $this->assertSame('field_edge_value', $result['table']['#rows'][0][0]);
  }

  /**
   * Tests processSettingsFieldset() guard clause with non-FormatterForm object.
   */
  public function testProcessSettingsFieldsetGuardClause(): void {
    $form_state = new FormState();
    $form_state->setBuildInfo(['callback_object' => NULL, 'args' => [], 'files' => []]);
    $element = ['#type' => 'fieldset'];

    $result = FormatterForm::processSettingsFieldset($element, $form_state);
    $this->assertSame($element, $result,
      'processSettingsFieldset must return element unchanged when form object is not FormatterForm.'
    );
  }

  /**
   * Tests FormatterPreset::previewDebugData() returns items and settings.
   */
  public function testFormatterPresetPreviewDebugData(): void {
    $preset_formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'preset_debug_test',
        'label' => 'Preset debug',
        'type' => 'formatter_preset',
        'field_types' => ['text_with_summary'],
        'data' => [
          'formatter' => 'text_default',
          'settings' => [],
        ],
      ]);
    \assert($preset_formatter instanceof FormatterInterface);
    $preset_formatter->save();

    $type = $preset_formatter->getFormatterType();
    $this->assertInstanceOf(FormatterPreset::class, $type);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Debug test',
      'body' => ['value' => 'Debug content', 'format' => 'plain_text'],
    ]);
    $node->save();

    $result = $type->previewDebugData($node->get('body'), $node);
    $this->assertIsArray($result);
    $this->assertArrayHasKey('items', $result);
    $this->assertArrayHasKey('settings', $result);
  }

  /**
   * Adds a setting field to the edge_test bundle.
   */
  private function addSettingField(): void {
    if (!FieldStorageConfig::loadByName('formatter_setting', 'field_edge_value')) {
      FieldStorageConfig::create([
        'field_name' => 'field_edge_value',
        'type' => 'string',
        'entity_type' => 'formatter_setting',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'field_edge_value',
      'entity_type' => 'formatter_setting',
      'bundle' => 'edge_test',
      'label' => 'Edge value',
    ])->save();
  }

}
