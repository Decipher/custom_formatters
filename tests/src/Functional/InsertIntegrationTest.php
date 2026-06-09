<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Functional;

use Drupal\Core\Field\FormatterInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests Insert module integration for Custom Formatters.
 *
 * The Insert stub module (custom_formatters_test_insert) is enabled selectively
 * per-test via $modules so that tests which check the moduleExists() guard run
 * without it and tests that exercise the full code path run with it.
 *
 * @group custom_formatters
 */
class InsertIntegrationTest extends CustomFormattersTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'block',
    'custom_formatters_test',
    'insert',
    'field_ui',
    'file',
    'image',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Load the insert integration file so its functions are available
    // for direct invocation in tests.
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.module')
      ->getPath('custom_formatters') . '/modules/insert.inc';
  }

  /**
   * Test the style registration and field-type filtering logic.
   *
   * Verifies that custom_formatters_insert_styles() correctly:
   * - Exposes image formatters for the 'image' insert type.
   * - Exposes file and entity_reference formatters for the 'file' insert type.
   * - Excludes non-matching field types.
   * - Returns empty for unrecognized insert types.
   */
  public function testInsertStylesFiltering(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('insert'), 'Insert stub module is active.');

    $image_fmt = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['image'],
      'data' => "return 'img';",
    ]);

    $file_fmt = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['file'],
      'data' => "return 'file';",
    ]);

    $er_fmt = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['entity_reference'],
      'data' => "return 'er';",
    ]);

    $text_fmt = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['text'],
      'data' => "return 'txt';",
    ]);

    // 'image' insert type: only image formatters.
    $image_styles = custom_formatters_insert_styles('image');
    $this->assertArrayHasKey('custom_formatters__' . $image_fmt->id(), $image_styles, 'Image formatter appears for image insert type.');
    $this->assertArrayNotHasKey('custom_formatters__' . $file_fmt->id(), $image_styles, 'File formatter excluded from image insert type.');
    $this->assertArrayNotHasKey('custom_formatters__' . $er_fmt->id(), $image_styles, 'Entity reference formatter excluded from image insert type.');
    $this->assertArrayNotHasKey('custom_formatters__' . $text_fmt->id(), $image_styles, 'Text formatter excluded from image insert type.');

    // 'file' insert type: file and entity_reference formatters.
    $file_styles = custom_formatters_insert_styles('file');
    $this->assertArrayNotHasKey('custom_formatters__' . $image_fmt->id(), $file_styles, 'Image formatter excluded from file insert type.');
    $this->assertArrayHasKey('custom_formatters__' . $file_fmt->id(), $file_styles, 'File formatter appears for file insert type.');
    $this->assertArrayHasKey('custom_formatters__' . $er_fmt->id(), $file_styles, 'Entity reference formatter appears for file insert type.');
    $this->assertArrayNotHasKey('custom_formatters__' . $text_fmt->id(), $file_styles, 'Text formatter excluded from file insert type.');

    // Unrecognized insert type returns empty.
    $this->assertEmpty(custom_formatters_insert_styles('video'), 'Unrecognized insert type returns empty.');
  }

  /**
   * Test custom_formatters_insert_render() with an image formatter.
   *
   * Exercises the full code path including InsertFieldItemList.
   */
  public function testInsertRender(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('insert'), 'Insert stub module is active.');

    $formatter = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['image'],
      'data' => "return 'RENDERED:' . \$items->first()->entity->getFilename();",
    ]);

    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri ?? '',
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    $style_name = 'custom_formatters__' . $formatter->id();

    // Verify the formatter appears in Insert styles.
    $styles = custom_formatters_insert_styles('image');
    $this->assertArrayHasKey($style_name, $styles, 'Formatter appears as an Insert style.');

    // Verify the full insert_render hook path, exercising InsertFieldItemList.
    $rendered = custom_formatters_insert_render($style_name, ['file' => $file], []);
    $this->assertNotEmpty($rendered, 'insert_render returned a non-empty string.');
    $this->assertStringContainsString('RENDERED:', $rendered, 'Formatter output prefix is present.');
    $this->assertStringContainsString($file->getFilename(), $rendered, 'Formatter output contains the filename.');

    // Non-matching style name returns empty.
    $this->assertSame('', custom_formatters_insert_render('other_module__style', ['file' => $file], []), 'Non-matching style returns empty string.');

    // Missing file returns empty.
    $this->assertSame('', custom_formatters_insert_render($style_name, [], []), 'Missing file returns empty string.');
  }

  /**
   * Test rendering through a custom formatter using a node-based approach.
   *
   * Creates a node with an image field, attaches a file, and renders the
   * field through a custom formatter — the same rendering path used by
   * custom_formatters_insert_render().
   */
  public function testFormatterRenderingWithFileFieldItemList(): void {
    // Create an image field on the article content type.
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_render_test',
        'type' => 'image',
        'entity_type' => 'node',
      ]);
    $field_storage->save();

    $field_config = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_name' => 'field_render_test',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'Render Test',
      ]);
    $field_config->save();

    // Create the test file.
    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri ?? '',
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    // Create a node with the image field.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Insert Render Test',
      'field_render_test' => [
        'target_id' => $file->id(),
        'alt' => '',
        'title' => $file->getFilename(),
      ],
    ]);
    $node->save();

    // Create the custom formatter for image fields.
    // Uses $items->first()->entity to access the referenced file entity.
    $formatter = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['image'],
      'data' => "return 'RENDERED:' . \$items->first()->entity->getFilename();",
    ]);

    // Get the FieldItemList from the node (properly constructed by the entity
    // system with the correct TypedData parent chain).
    $items = $node->get('field_render_test');

    // Create the formatter plugin instance.
    $plugin_manager = \Drupal::service('plugin.manager.field.formatter');
    $formatter_instance = $plugin_manager->createInstance(
      'custom_formatters:' . $formatter->id(),
      [
        'field_definition' => $field_config,
        'settings' => [],
        'label' => 'hidden',
        'view_mode' => '_custom',
        'third_party_settings' => [],
      ],
    );

    \assert($formatter_instance instanceof FormatterInterface);
    $elements = $formatter_instance->viewElements($items, $items->getLangcode());

    $this->assertNotEmpty($elements, 'Rendering returned elements.');
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($elements);
    $this->assertStringContainsString('RENDERED:', $rendered, 'Formatter output contains expected prefix.');
    $this->assertStringContainsString($file->getFilename(), $rendered, 'Formatter output contains the filename.');
  }

  /**
   * Test rendering via entity_reference through a custom formatter.
   */
  public function testFormatterRenderingWithEntityReferenceFieldItemList(): void {
    // Create an entity_reference field on the article content type.
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_er_test',
        'type' => 'entity_reference',
        'entity_type' => 'node',
        'settings' => ['target_type' => 'file'],
      ]);
    $field_storage->save();

    $field_config = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_name' => 'field_er_test',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'ER Test',
        'settings' => ['handler' => 'default:file'],
      ]);
    $field_config->save();

    // Create the test file.
    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri ?? '',
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    // Create a node with the entity_reference field.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Insert ER Test',
      'field_er_test' => ['target_id' => $file->id()],
    ]);
    $node->save();

    // Create the custom formatter for entity_reference fields.
    $formatter = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['entity_reference'],
      'data' => "return 'ER-RENDERED:' . \$items->first()->entity->getFilename();",
    ]);

    // Get the FieldItemList from the node.
    $items = $node->get('field_er_test');

    // Create the formatter plugin instance.
    $plugin_manager = \Drupal::service('plugin.manager.field.formatter');
    $formatter_instance = $plugin_manager->createInstance(
      'custom_formatters:' . $formatter->id(),
      [
        'field_definition' => $field_config,
        'settings' => [],
        'label' => 'hidden',
        'view_mode' => '_custom',
        'third_party_settings' => [],
      ],
    );

    \assert($formatter_instance instanceof FormatterInterface);
    $elements = $formatter_instance->viewElements($items, $items->getLangcode());

    $this->assertNotEmpty($elements, 'Entity reference rendering returned elements.');
    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($elements);
    $this->assertStringContainsString('ER-RENDERED:', $rendered, 'Entity reference formatter output contains expected prefix.');
    $this->assertStringContainsString($file->getFilename(), $rendered, 'Formatter output contains the filename.');
  }

  /**
   * Test that the manage form display page loads without errors.
   */
  public function testManageDisplayPage(): void {
    // Create an image field on the article content type.
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_display_test',
        'type' => 'image',
        'entity_type' => 'node',
      ]);
    $field_storage->save();

    \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_name' => 'field_display_test',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'Display Test',
      ])
      ->save();

    $this->drupalGet('admin/structure/types/manage/article/form-display');
    $this->assertSession()->statusCodeEquals(200);
  }

}
