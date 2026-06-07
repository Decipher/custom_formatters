<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FormatterInterface as FieldFormatterInterface;
use Drupal\custom_formatters\Entity\FormatterSetting;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\FormatterTypeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that formatter settings values are rendered via view display formatter.
 *
 * Verifies that settings passed to engine plugins are processed strings
 * (not raw field item data), and that the view display configuration
 * controls the output format.
 *
 * @group custom_formatters
 */
class FormatterSettingsRenderTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('formatter_setting');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['custom_formatters', 'filter']);
  }

  /**
   * Tests that a string settings field value is passed as a rendered string.
   *
   * Verifies the full round-trip: field value stored on FormatterSetting entity
   * → rendered via string formatter → received by engine plugin as plain text.
   */
  public function testStringSettingRendersAsString(): void {
    $formatter = $this->createFormatter('twig_str_test', 'twig', '{{ settings.field_test_text }}');

    FieldStorageConfig::create([
      'field_name' => 'field_test_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'formatter_setting',
      'bundle' => 'twig_str_test',
      'label' => 'Test text',
    ])->save();

    $this->createViewDisplay('formatter_setting', 'twig_str_test', [
      'field_test_text' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
    ]);

    $setting = FormatterSetting::create([
      'formatter' => 'twig_str_test',
      'label' => 'Test',
      'field_test_text' => 'hello-world',
    ]);
    $setting->save();

    $rendered = $this->renderField($setting, 'field_test_text');

    $node = $this->createNodeWithBody('Test body');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_test_text' => $rendered]);

    $this->assertNotEmpty($result);
    $markup = (string) $result['#markup'];
    $this->assertStringContainsString('hello-world', $markup);
    $this->assertStringNotContainsString('[{', $markup);
    $this->assertStringNotContainsString("'value'", $markup);
  }

  /**
   * Tests that a boolean settings field with value TRUE renders as "Yes".
   */
  public function testBooleanSettingRendersAsYesNo(): void {
    $formatter = $this->createFormatter('twig_bool_test', 'twig', 'Result:{{ settings.field_test_bool }}');

    FieldStorageConfig::create([
      'field_name' => 'field_test_bool',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_bool',
      'entity_type' => 'formatter_setting',
      'bundle' => 'twig_bool_test',
      'label' => 'Test bool',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $this->createViewDisplay('formatter_setting', 'twig_bool_test', [
      'field_test_bool' => ['type' => 'boolean', 'settings' => ['format' => 'yes-no']],
    ]);

    $setting = FormatterSetting::create([
      'formatter' => 'twig_bool_test',
      'label' => 'Test',
      'field_test_bool' => 1,
    ]);
    $setting->save();

    $rendered = $this->renderField($setting, 'field_test_bool');

    $node = $this->createNodeWithBody('Test body');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_test_bool' => $rendered]);

    $this->assertNotEmpty($result);
    $markup = (string) $result['#markup'];
    $this->assertStringContainsString('Result:', $markup);
    $this->assertStringContainsString('Yes', $markup);
  }

  /**
   * Tests that a boolean settings field with value FALSE renders as "No".
   */
  public function testBooleanFalseRendersAsNo(): void {
    $formatter = $this->createFormatter('twig_bool_false', 'twig', 'Val:{{ settings.field_flag }}');

    FieldStorageConfig::create([
      'field_name' => 'field_flag',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_flag',
      'entity_type' => 'formatter_setting',
      'bundle' => 'twig_bool_false',
      'label' => 'Flag',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $this->createViewDisplay('formatter_setting', 'twig_bool_false', [
      'field_flag' => ['type' => 'boolean', 'settings' => ['format' => 'yes-no']],
    ]);

    $setting = FormatterSetting::create([
      'formatter' => 'twig_bool_false',
      'label' => 'Test',
      'field_flag' => 0,
    ]);
    $setting->save();

    $rendered = $this->renderField($setting, 'field_flag');

    $node = $this->createNodeWithBody('Test body');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_flag' => $rendered]);

    $this->assertNotEmpty($result);
    $this->assertStringContainsString('No', (string) $result['#markup']);
  }

  /**
   * Tests that settings values are substituted into HTML+Token output.
   */
  public function testHtmlTokenSettingsRendered(): void {
    $formatter = $this->createFormatter('html_token_test', 'html_token', '[formatter_setting:field_label_text]');

    FieldStorageConfig::create([
      'field_name' => 'field_label_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_label_text',
      'entity_type' => 'formatter_setting',
      'bundle' => 'html_token_test',
      'label' => 'Label text',
    ])->save();

    $node = $this->createNodeWithBody('Node body text');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_label_text' => 'my-rendered-label']);

    $this->assertNotEmpty($result);
    $this->assertStringContainsString('my-rendered-label', (string) $result[0]['#markup']);
  }

  /**
   * Tests that [formatter_setting:field:raw] returns the value from _raw.
   */
  public function testHtmlTokenRawModifierReturnsRawValue(): void {
    $formatter = $this->createFormatter('html_token_raw', 'html_token', '[formatter_setting:field_raw_text:raw]');

    FieldStorageConfig::create([
      'field_name' => 'field_raw_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_raw_text',
      'entity_type' => 'formatter_setting',
      'bundle' => 'html_token_raw',
      'label' => 'Raw text',
    ])->save();

    $node = $this->createNodeWithBody('Node body');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', [
      'field_raw_text' => 'rendered-value',
      '_raw' => ['field_raw_text' => 'raw-value'],
    ]);

    $this->assertNotEmpty($result);
    $this->assertStringContainsString('raw-value', (string) $result[0]['#markup']);
    $this->assertStringNotContainsString('rendered-value', (string) $result[0]['#markup']);
  }

  /**
   * Tests that [formatter_setting:field:raw] falls back when _raw is absent.
   *
   * When _raw is not an array, the token is not replaced and is cleared by the
   * token service (clear => TRUE), resulting in an empty output.
   */
  public function testHtmlTokenRawModifierFallsBackWhenRawAbsent(): void {
    $formatter = $this->createFormatter('html_token_no_raw', 'html_token', 'before:[formatter_setting:field_no_raw:raw]:after');

    FieldStorageConfig::create([
      'field_name' => 'field_no_raw',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_no_raw',
      'entity_type' => 'formatter_setting',
      'bundle' => 'html_token_no_raw',
      'label' => 'No raw',
    ])->save();

    $node = $this->createNodeWithBody('Body');
    $formatter_type = $this->formatterType($formatter);

    // Pass _raw as a non-array scalar — the is_array guard treats it as [].
    $result = $formatter_type->viewElements($node->get('body'), 'en', [
      'field_no_raw' => 'rendered',
      '_raw' => 'not-an-array',
    ]);

    $this->assertNotEmpty($result);
    // Token is not replaced (falls back to the literal), then cleared by the
    // token service, so the substitution slot is removed from the output.
    $this->assertStringNotContainsString('rendered', (string) $result[0]['#markup']);
  }

  /**
   * Tests that settings values are accessible in the PHP formatter's $settings.
   */
  public function testPhpSettingsRendered(): void {
    $formatter = $this->createFormatter('php_test', 'php', 'return ["#markup" => $settings["field_tag"]];');

    FieldStorageConfig::create([
      'field_name' => 'field_tag',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tag',
      'entity_type' => 'formatter_setting',
      'bundle' => 'php_test',
      'label' => 'Tag',
    ])->save();

    $node = $this->createNodeWithBody('Test');
    $formatter_type = $this->formatterType($formatter);
    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_tag' => 'featured']);

    $this->assertNotEmpty($result);
    $this->assertStringContainsString('featured', (string) $result['#markup']);
  }

  /**
   * Tests that the EntityViewDisplay formatter setting controls boolean output.
   *
   * Verifies that switching the boolean formatter's format (yes-no, on-off,
   * true-false) on the view display changes the rendered string passed to the
   * engine plugin.
   */
  public function testViewDisplayControlsBooleanFormat(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_toggle',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('bool_format_test', 'twig', '{{ settings.field_toggle }}');

    FieldConfig::create([
      'field_name' => 'field_toggle',
      'entity_type' => 'formatter_setting',
      'bundle' => 'bool_format_test',
      'label' => 'Toggle',
      'settings' => ['on_label' => 'On', 'off_label' => 'Off'],
    ])->save();

    $setting = FormatterSetting::create([
      'formatter' => 'bool_format_test',
      'label' => 'Test',
      'field_toggle' => 1,
    ]);
    $setting->save();

    $node = $this->createNodeWithBody('Body');

    $formats = ['yes-no' => 'Yes', 'on-off' => 'On', 'true-false' => 'True'];
    foreach ($formats as $format => $expected) {
      $existing = EntityViewDisplay::load("formatter_setting.bool_format_test.default");
      if ($existing) {
        $existing->delete();
      }
      $this->createViewDisplay('formatter_setting', 'bool_format_test', [
        'field_toggle' => ['type' => 'boolean', 'settings' => ['format' => $format]],
      ]);

      $rendered = $this->renderField($setting, 'field_toggle');

      $formatter_type = $this->formatterType($formatter);
      $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_toggle' => $rendered]);

      $this->assertStringContainsString($expected, (string) $result['#markup'], "Boolean format '$format' should render as '$expected'.");
    }
  }

  /**
   * Creates and saves a formatter entity.
   *
   * @param string $id
   *   The formatter machine name.
   * @param string $type
   *   The formatter engine plugin ID (e.g. 'twig', 'html_token', 'php').
   * @param string $data
   *   The formatter engine code or template.
   *
   * @return \Drupal\custom_formatters\FormatterInterface
   *   The saved formatter entity.
   */
  private function createFormatter(string $id, string $type, string $data): FormatterInterface {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => $id,
        'label' => 'Test ' . $id,
        'type' => $type,
        'field_types' => ['text_with_summary'],
        'data' => $data,
      ]);
    assert($formatter instanceof FormatterInterface);
    $formatter->save();
    return $formatter;
  }

  /**
   * Returns the formatter type plugin for a formatter, asserting it is valid.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $formatter
   *   The formatter entity.
   *
   * @return \Drupal\custom_formatters\FormatterTypeInterface
   *   The formatter type plugin.
   */
  private function formatterType(FormatterInterface $formatter): FormatterTypeInterface {
    $type = $formatter->getFormatterType();
    assert($type !== FALSE, 'Formatter type plugin must be available.');
    return $type;
  }

  /**
   * Creates a saved article node with the given body value.
   *
   * Also creates the article node type and body field if they do not exist.
   *
   * @param string $body_value
   *   The body text value.
   *
   * @return \Drupal\node\Entity\Node
   *   The saved node.
   */
  private function createNodeWithBody(string $body_value): Node {
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

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article',
      'body' => ['value' => $body_value, 'format' => 'plain_text'],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Creates and saves an EntityViewDisplay with the given field components.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   * @param array $fields
   *   Keyed by field name; each value is ['type' => ..., 'settings' => [...]].
   *
   * @return \Drupal\Core\Entity\Entity\EntityViewDisplay
   *   The saved view display.
   */
  private function createViewDisplay(string $entity_type, string $bundle, array $fields): EntityViewDisplay {
    $content = [];
    $weight = 0;
    foreach ($fields as $field_name => $config) {
      $content[$field_name] = [
        'type' => $config['type'],
        'label' => 'hidden',
        'settings' => $config['settings'] ?? [],
        'third_party_settings' => [],
        'weight' => $weight++,
        'region' => 'content',
      ];
    }
    $display = EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
      'content' => $content,
    ]);
    $display->save();
    return $display;
  }

  /**
   * Renders a single field on a FormatterSetting entity to a plain HTML string.
   *
   * @param \Drupal\custom_formatters\Entity\FormatterSetting $entity
   *   The formatter setting entity.
   * @param string $field_name
   *   The field machine name to render.
   *
   * @return string
   *   The concatenated rendered markup for all field items.
   */
  private function renderField(FormatterSetting $entity, string $field_name): string {
    $view_display = EntityViewDisplay::load("formatter_setting.{$entity->bundle()}.default");
    $this->assertNotNull($view_display, "View display for bundle {$entity->bundle()} must exist.");
    $renderer = $view_display->getRenderer($field_name);
    $this->assertInstanceOf(FieldFormatterInterface::class, $renderer, "Renderer for field $field_name must exist.");

    $rendered = '';
    foreach ($renderer->viewElements($entity->get($field_name), 'en') as $element) {
      $rendered .= (string) \Drupal::service('renderer')->renderInIsolation($element);
    }
    return $rendered;
  }

}
