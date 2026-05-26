<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests that formatter presets render entity reference-based fields.
 *
 * Regression test: FormatterPreset::viewElements() must call prepareView()
 * before delegating to the core formatter. Without it, entity reference
 * formatters (image, file, etc.) produce empty output because
 * EntityReferenceFormatterBase::getEntitiesToView() skips items where
 * _loaded is not set.
 *
 * @coversDefaultClass \Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType\FormatterPreset
 * @group custom_formatters
 */
class FormatterPresetEntityReferenceTest extends KernelTestBase {
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
    'file',
    'filter',
    'image',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['custom_formatters', 'filter', 'image']);
  }

  /**
   * Tests that image fields render via formatter preset.
   *
   * ImageFormatter extends EntityReferenceFormatterBase which requires
   * prepareView() to set _loaded on field items before getEntitiesToView()
   * will return them.
   *
   * @covers ::viewElements
   */
  public function testImageFieldRendersViaFormatterPreset(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Image',
    ])->save();

    $images = $this->getTestFiles('image');
    $image = reset($images);
    $this->assertNotFalse($image);
    $file = File::create([
      'uri' => $image->uri ?? '',
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
      'uid' => 1,
      'field_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test alt',
      ],
    ]);
    $node->save();

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'image_preset_test',
        'label' => 'Image Preset Test',
        'type' => 'formatter_preset',
        'field_types' => ['image'],
        'data' => [
          'formatter' => 'image',
          'settings' => [
            'image_style' => 'thumbnail',
            'image_link' => '',
          ],
        ],
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $items = $node->get('field_image');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result, 'Formatter preset produced output for image field.');
    $rendered = '';
    foreach ($result as $element) {
      $rendered .= (string) \Drupal::service('renderer')->renderInIsolation($element);
    }
    $this->assertStringContainsString('<img', $rendered);
    $this->assertStringContainsString('thumbnail', $rendered);
  }

  /**
   * Tests that text fields render via formatter preset (non-entity-reference).
   *
   * Ensures the prepareView() addition does not break simple field types.
   *
   * @covers ::viewElements
   */
  public function testTextFieldRendersViaFormatterPreset(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
      'uid' => 1,
      'body' => [
        'value' => '<p>Hello world</p>',
        'format' => 'plain_text',
      ],
    ]);
    $node->save();

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'text_preset_test',
        'label' => 'Text Preset Test',
        'type' => 'formatter_preset',
        'field_types' => ['text_with_summary'],
        'data' => [
          'formatter' => 'text_default',
          'settings' => [],
        ],
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $items = $node->get('body');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result, 'Formatter preset produced output for text field.');
    $rendered = '';
    foreach ($result as $element) {
      $rendered .= (string) \Drupal::service('renderer')->renderInIsolation($element);
    }
    $this->assertStringContainsString('Hello world', $rendered);
  }

  /**
   * Tests that formatter preset renders with unsaved entity (Devel Generate).
   *
   * Simulates the Devel Generate preview scenario where the entity exists only
   * in memory. The file is saved (by generateSampleItems) but the parent node
   * is not.
   *
   * @covers ::viewElements
   */
  public function testImageFieldRendersWithUnsavedEntity(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Image',
    ])->save();

    $entity = Node::create([
      'type' => 'page',
      'title' => 'Unsaved',
      'uid' => 1,
    ]);
    foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
      if ($definition instanceof FieldConfigInterface && $entity->hasField($field_name)) {
        $field = $entity->get($field_name);
        if ($field->isEmpty()) {
          try {
            $field->generateSampleItems();
          }
          catch (\Exception) {
          }
        }
      }
    }

    $this->assertFalse($entity->get('field_image')->isEmpty(), 'Sample image was generated.');

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'unsaved_image_test',
        'label' => 'Unsaved Image Test',
        'type' => 'formatter_preset',
        'field_types' => ['image'],
        'data' => [
          'formatter' => 'image',
          'settings' => [
            'image_style' => '',
            'image_link' => '',
          ],
        ],
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $items = $entity->get('field_image');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result, 'Formatter preset produced output for unsaved entity.');
    $rendered = '';
    foreach ($result as $element) {
      $rendered .= (string) \Drupal::service('renderer')->renderInIsolation($element);
    }
    $this->assertStringContainsString('<img', $rendered);
  }

}
