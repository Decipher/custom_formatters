<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the preview functionality in FormatterForm.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\custom_formatters\Form\FormatterForm;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\FormatterTypeInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeTypeInterface;

/**
 * Tests the preview section on the formatter entity form.
 *
 * Verifies the preview selects layout (flexbox container, button placement),
 * conditional inclusion of debug settings based on Devel module availability,
 * and the Devel dumper integration for debug output.
 *
 * These tests run as kernel tests to ensure code coverage is collected,
 * since functional tests execute form submissions via HTTP to a separate
 * PHP process where pcov cannot track coverage.
 *
 * @see \Drupal\custom_formatters\Form\FormatterForm::buildPreviewFieldset()
 * @see \Drupal\custom_formatters\Form\FormatterForm::buildPreviewOutput()
 *
 * @group custom_formatters
 */
class FormatterPreviewFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
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
    $this->installConfig(['custom_formatters', 'node']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    // Create an article content type so the preview selects have entity
    // types, bundles, and fields to work with.
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'type' => 'article',
        'name' => 'Article',
      ]);
    assert($node_type instanceof NodeTypeInterface);

    // In Drupal 11, node_add_body_field() expects the body field storage to
    // already exist (provided by the Standard profile). Create it before
    // saving the node type so node_node_type_insert() doesn't fail.
    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_with_summary',
        'settings' => [],
        'cardinality' => 1,
      ])->save();
    }

    $node_type->save();
  }

  /**
   * Tests the selects container uses the preview-selects class.
   *
   * The container must use the custom .preview-selects class for the
   * flexbox layout CSS, and must not carry the Views-specific
   * .views-exposed-form class.
   */
  public function testPreviewSelectsContainerStructure(): void {
    $formatter = $this->createFormatter('test_selects');
    $form = $this->buildForm($formatter);

    $this->assertArrayHasKey('selects', $form['preview']);
    $this->assertContains('preview-selects', $form['preview']['selects']['#attributes']['class']);
    $this->assertNotContains('views-exposed-form', $form['preview']['selects']['#attributes']['class']);
  }

  /**
   * Tests the preview button is placed inside the selects container.
   *
   * The button must be a child of the selects container so that CSS
   * flexbox rules include it in the horizontal layout alongside the
   * selects.
   */
  public function testPreviewButtonInsideSelectsContainer(): void {
    $formatter = $this->createFormatter('test_button');
    $form = $this->buildForm($formatter);

    $this->assertArrayHasKey('button', $form['preview']['selects']);
    $button = $form['preview']['selects']['button'];
    $this->assertEquals('submit', $button['#type']);
    $this->assertEquals('primary', $button['#button_type']);
    $this->assertContains('::previewSubmit', $button['#submit']);
    $this->assertArrayHasKey('#ajax', $button);
    $this->assertEquals('preview-output-wrapper', $button['#ajax']['wrapper']);
  }

  /**
   * Tests the preview button is wrapped in a preview-actions div.
   *
   * The button uses #prefix/#suffix to wrap the bare <input> in a <div>
   * with the preview-actions class, enabling flexbox layout and alignment.
   */
  public function testPreviewButtonWrapperClass(): void {
    $formatter = $this->createFormatter('test_button_class');
    $form = $this->buildForm($formatter);

    $button = $form['preview']['selects']['button'];
    $this->assertStringContainsString('preview-actions', $button['#prefix']);
  }

  /**
   * Tests debug details group is absent when Devel is not installed.
   *
   * Follows the core convention of conditional element inclusion: the
   * entire debug details group should not be rendered when Devel is not
   * available, rather than rendered but disabled.
   */
  public function testDebugDetailsAbsentWithoutDevel(): void {
    $formatter = $this->createFormatter('test_no_debug');
    $form = $this->buildForm($formatter);

    $this->assertArrayNotHasKey('debug', $form['preview']);
  }

  /**
   * Tests the formatter_form library is attached to the form.
   */
  public function testPreviewLibraryAttached(): void {
    $formatter = $this->createFormatter('test_library');
    $form = $this->buildForm($formatter);

    $this->assertContains('custom_formatters/formatter_form', $form['#attached']['library']);
  }

  /**
   * Tests cascading selects have correct AJAX wrappers.
   *
   * The entity type, bundle, and field selects must all trigger an AJAX
   * rebuild of the selects container, ensuring downstream options are
   * recalculated when upstream selections change.
   */
  public function testCascadingSelectsAjax(): void {
    $formatter = $this->createFormatter('test_ajax');
    $form = $this->buildForm($formatter);

    foreach (['entity_type', 'bundle', 'field'] as $select_name) {
      $this->assertArrayHasKey('#ajax', $form['preview']['selects'][$select_name]);
      $this->assertEquals('preview-selects-wrapper', $form['preview']['selects'][$select_name]['#ajax']['wrapper']);
    }

    // Entity select should NOT have AJAX (no downstream depends on it).
    $this->assertArrayNotHasKey('#ajax', $form['preview']['selects']['entity']);
  }

  /**
   * Tests the selects container uses an AJAX wrapper div.
   */
  public function testSelectsContainerAjaxWrapper(): void {
    $formatter = $this->createFormatter('test_wrapper');
    $form = $this->buildForm($formatter);

    $selects = $form['preview']['selects'];
    $this->assertEquals('<div id="preview-selects-wrapper">', $selects['#prefix']);
    $this->assertEquals('</div>', $selects['#suffix']);
  }

  /**
   * Tests preview output section has the correct AJAX wrapper.
   */
  public function testPreviewOutputContainerStructure(): void {
    $formatter = $this->createFormatter('test_output');
    $form = $this->buildForm($formatter);

    $this->assertArrayHasKey('output', $form['preview']);
    $this->assertEquals('preview-output-wrapper', $form['preview']['output']['#attributes']['id']);
  }

  /**
   * Tests the toggle checkbox for full field theming is present.
   */
  public function testToggleCheckboxPresent(): void {
    $formatter = $this->createFormatter('test_toggle');
    $form = $this->buildForm($formatter);

    $this->assertArrayHasKey('toggle', $form['preview']);
    $this->assertEquals('checkbox', $form['preview']['toggle']['#type']);
  }

  /**
   * Tests buildPreviewOutput uses Devel dumper when available.
   *
   * Debug output is wrapped in a collapsible details element with the
   * mock dumper's output nested under the 'dump' key.
   */
  public function testBuildPreviewOutputWithDevelDumper(): void {
    $formatter = $this->createFormatter('test_devel_output');
    $formatter->save();

    $form_object = $this->getFormObject($formatter);

    // Create a mock dumper that returns identifiable output.
    $mock_dumper = new class {

      /**
       * Mock exportAsRenderable returning identifiable test output.
       */
      public function exportAsRenderable(mixed $input, ?string $name = NULL): array {
        return ['#markup' => 'MOCK_DEVEL_OUTPUT'];
      }

    };

    // Inject the mock dumper via reflection.
    $ref = new \ReflectionClass($form_object);
    $prop = $ref->getProperty('develDumper');
    $prop->setAccessible(TRUE);
    $prop->setValue($form_object, $mock_dumper);

    $output = $this->invokeBuildPreviewOutput($form_object, 'php', [
      'debug_variables' => TRUE,
      'debug_html' => TRUE,
    ]);

    // Debug sections should be wrapped in a container with CSS spacing.
    $this->assertArrayHasKey('debug_variables', $output);
    $this->assertArrayHasKey('debug_html', $output);
    $this->assertEquals('container', $output['debug_variables']['#type']);
    $this->assertEquals('container', $output['debug_html']['#type']);
    $this->assertContains('formatter-preview-debug', $output['debug_variables']['#attributes']['class']);
    $this->assertContains('formatter-preview-debug', $output['debug_html']['#attributes']['class']);
    $this->assertEquals('MOCK_DEVEL_OUTPUT', $output['debug_variables']['dump']['#markup']);
    $this->assertEquals('MOCK_DEVEL_OUTPUT', $output['debug_html']['dump']['#markup']);
  }

  /**
   * Tests debug output is not added when debug settings are empty.
   *
   * Since the debug details group is conditionally included only when Devel
   * is installed, the debug settings will always be empty without Devel. This
   * test verifies that buildPreviewOutput produces no debug sections when
   * no settings are active.
   */
  public function testBuildPreviewOutputWithoutDebugSettings(): void {
    $formatter = $this->createFormatter('test_no_debug_settings');
    $formatter->save();

    $form_object = $this->getFormObject($formatter);

    $output = $this->invokeBuildPreviewOutput($form_object, 'html_token', []);

    $this->assertArrayHasKey('preview', $output);
    $this->assertArrayNotHasKey('debug_variables', $output);
    $this->assertArrayNotHasKey('debug_html', $output);
  }

  /**
   * Tests debug_variables works for any engine type.
   *
   * The PHP-only gate was removed so debug_variables now produces output
   * for all formatter types when the checkbox is enabled.
   */
  public function testDebugVariablesForAnyEngineType(): void {
    $formatter = $this->createFormatter('test_any_engine');
    $formatter->save();

    $form_object = $this->getFormObject($formatter);

    // Inject mock dumper so the Devel path is taken.
    $mock_dumper = new class {

      /**
       * Mock exportAsRenderable.
       */
      public function exportAsRenderable(mixed $input, ?string $name = NULL): array {
        return ['#markup' => 'MOCK'];
      }

    };
    $ref = new \ReflectionClass($form_object);
    $prop = $ref->getProperty('develDumper');
    $prop->setAccessible(TRUE);
    $prop->setValue($form_object, $mock_dumper);

    // Verify debug_variables works for non-PHP engines.
    $output = $this->invokeBuildPreviewOutput($form_object, 'html_token', [
      'debug_variables' => TRUE,
      'debug_html' => TRUE,
    ]);

    $this->assertArrayHasKey('debug_variables', $output);
    $this->assertArrayHasKey('debug_html', $output);
    $this->assertEquals('container', $output['debug_variables']['#type']);
    $this->assertEquals('container', $output['debug_html']['#type']);
    $this->assertContains('formatter-preview-debug', $output['debug_variables']['#attributes']['class']);
    $this->assertContains('formatter-preview-debug', $output['debug_html']['#attributes']['class']);
  }

  /**
   * Creates a formatter entity with basic properties.
   *
   * @param string $id
   *   The formatter machine name.
   *
   * @return \Drupal\custom_formatters\FormatterInterface
   *   The unsaved formatter entity.
   */
  private function createFormatter(string $id): FormatterInterface {
    $formatter = $this->container->get('entity_type.manager')
      ->getStorage('formatter')
      ->create([
        'id' => $id,
        'label' => 'Test ' . $id,
        'type' => 'html_token',
        'field_types' => ['text'],
        'data' => '[node:title]',
      ]);
    assert($formatter instanceof FormatterInterface);
    return $formatter;
  }

  /**
   * Builds the formatter edit form for a given formatter entity.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $formatter
   *   The formatter entity.
   *
   * @return array
   *   The rendered form array.
   */
  private function buildForm(FormatterInterface $formatter): array {
    $form_object = $this->getFormObject($formatter);
    return $this->container->get('form_builder')->getForm($form_object);
  }

  /**
   * Gets the FormatterForm object for a given formatter entity.
   *
   * @param \Drupal\custom_formatters\FormatterInterface $formatter
   *   The formatter entity.
   *
   * @return \Drupal\custom_formatters\Form\FormatterForm
   *   The form object with the entity set.
   */
  private function getFormObject(FormatterInterface $formatter): FormatterForm {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('formatter', 'edit');
    assert($form_object instanceof FormatterForm);
    $form_object->setEntity($formatter);
    return $form_object;
  }

  /**
   * Invokes the protected buildPreviewOutput method via reflection.
   *
   * @param \Drupal\custom_formatters\Form\FormatterForm $form_object
   *   The form object.
   * @param string $plugin_id
   *   The formatter type plugin ID (e.g. 'php', 'html_token').
   * @param array $settings
   *   The preview debug settings.
   *
   * @return array
   *   The preview output render array.
   */
  private function invokeBuildPreviewOutput(FormatterForm $form_object, string $plugin_id, array $settings): array {
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getValue')->willReturn([['value' => 'test']]);
    $items->method('getLangcode')->willReturn('en');

    $entity = $this->createMock(FieldableEntityInterface::class);

    $formatter_type = $this->createMock(FormatterTypeInterface::class);
    $formatter_type->method('getPluginId')->willReturn($plugin_id);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('buildPreviewOutput');
    $method->setAccessible(TRUE);

    return $method->invoke(
      $form_object,
      [['#markup' => '<p>Test output</p>']],
      $items,
      'node',
      'article',
      'body',
      $entity,
      $settings,
      FALSE,
      $formatter_type
    );
  }

}
