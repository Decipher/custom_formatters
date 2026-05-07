<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for formatter config entity export integrity.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that formatter config entities export all required properties.
 *
 * Ensures that ConfigEntityBase::toArray() includes all keys declared in the
 * Formatter entity's config_export annotation: id, label, type, description,
 * field_types, and data. This guards against regressions where config export
 * silently drops properties, breaking CMI round-trips.
 *
 * Regression test for issues #3188668 and #3572914.
 *
 * @group custom_formatters
 */
class FormatterConfigExportTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['custom_formatters'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('custom_formatters');
  }

  /**
   * Tests that toArray() includes all config_export keys.
   *
   * Creates a formatter with every property populated, then verifies that
   * toArray() returns all six keys (id, label, type, description,
   * field_types, data) with their original values intact.
   */
  public function testConfigExportIncludesAllProperties(): void {
    $values = [
      'id' => 'test_export',
      'label' => 'Test Export',
      'type' => 'html_token',
      'description' => 'A test formatter',
      'field_types' => ['text', 'text_long'],
      'data' => '<p>[custom_formatters:entity]</p>',
    ];

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create($values);
    $formatter->save();

    $exported = $formatter->toArray();

    foreach (['id', 'label', 'type', 'description', 'field_types', 'data'] as $key) {
      $this->assertArrayHasKey($key, $exported, "Config export is missing '$key' key.");
      $this->assertEquals($values[$key], $exported[$key], "Exported '$key' does not match original value.");
    }
  }

  /**
   * Tests config export/import round-trip preserves all properties.
   *
   * Creates a formatter, exports it to an array, deletes the entity, then
   * recreates it from the exported data. Verifies that all properties
   * (id, label, type, field_types, data) survive the round-trip unchanged.
   */
  public function testConfigExportRoundTrip(): void {
    $values = [
      'id' => 'test_roundtrip',
      'label' => 'Round Trip',
      'type' => 'php',
      'field_types' => ['text_with_summary'],
      'data' => "return 'hello';",
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('formatter');
    $storage->create($values)->save();

    $exported = $storage->load('test_roundtrip')->toArray();

    $storage->load('test_roundtrip')->delete();
    $storage->create($exported)->save();

    $imported = $storage->load('test_roundtrip');

    $this->assertEquals('test_roundtrip', $imported->id());
    $this->assertEquals('Round Trip', $imported->label());
    $this->assertEquals('php', $imported->get('type'));
    $this->assertEquals(['text_with_summary'], $imported->get('field_types'));
    $this->assertEquals("return 'hello';", $imported->get('data'));
  }

  /**
   * Tests that formatter_preset mapping data exports correctly.
   *
   * The formatter_preset engine stores structured data (formatter name +
   * settings) as a mapping in config schema. Verifies that this complex
   * data structure survives config export intact.
   */
  public function testConfigExportFormatterPresetData(): void {
    $values = [
      'id' => 'test_preset',
      'label' => 'Test Preset',
      'type' => 'formatter_preset',
      'field_types' => ['text'],
      'data' => [
        'formatter' => 'text_default',
      ],
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('formatter');
    $storage->create($values)->save();

    $exported = $storage->load('test_preset')->toArray();

    $this->assertEquals('formatter_preset', $exported['type']);
    $this->assertArrayHasKey('data', $exported);
    $this->assertEquals('text_default', $exported['data']['formatter']);
  }

}
