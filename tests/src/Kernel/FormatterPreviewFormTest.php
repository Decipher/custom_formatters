<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the preview functionality in FormatterForm.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\custom_formatters\DevelGenerateIntegration;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\custom_formatters\Form\FormatterForm;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\FormatterTypeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

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

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_formatters',
    'field',
    'field_ui',
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
    $this->installEntitySchema('formatter_setting');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    node_access_rebuild();

    // Create an article content type so the preview selects have entity
    // types, bundles, and fields to work with.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'type' => 'article',
        'name' => 'Article',
      ]);

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

    // Ensure the body field config exists on the article bundle.
    // node_node_type_insert() may skip creating it in kernel test env.
    if (!FieldConfig::loadByName('node', 'article', 'body')) {
      FieldConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'Body',
      ])->save();
    }
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
   * Tests getPreviewDefaults returns expected default values.
   */
  public function testGetPreviewDefaults(): void {
    $formatter = $this->createFormatter('test_defaults');
    // Set field_types to include text_with_summary so body field matches.
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewDefaults');
    $method->setAccessible(TRUE);

    $defaults = $method->invoke($form_object, new FormState());

    $this->assertEquals('node', $defaults['entity_type']);
    $this->assertEquals('article', $defaults['bundle']);
    $this->assertEquals('body', $defaults['field']);
  }

  /**
   * Tests getPreviewEntityTypes returns node.
   */
  public function testGetPreviewEntityTypes(): void {
    $formatter = $this->createFormatter('test_entity_types');
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewEntityTypes');
    $method->setAccessible(TRUE);

    $types = $method->invoke($form_object);
    $this->assertArrayHasKey('node', $types);
  }

  /**
   * Tests getPreviewBundles returns article for node.
   */
  public function testGetPreviewBundles(): void {
    $formatter = $this->createFormatter('test_bundles');
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewBundles');
    $method->setAccessible(TRUE);

    $bundles = $method->invoke($form_object, 'node');
    $this->assertArrayHasKey('article', $bundles);
  }

  /**
   * Tests getPreviewFields returns body for article with text types.
   */
  public function testGetPreviewFields(): void {
    $formatter = $this->createFormatter('test_fields');
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewFields');
    $method->setAccessible(TRUE);

    $fields = $method->invoke($form_object, 'node', 'article', ['text', 'text_with_summary']);
    $this->assertArrayHasKey('body', $fields);
  }

  /**
   * Tests buildPreviewOutput with toggle=true hits field theming path.
   */
  public function testBuildPreviewOutputWithToggle(): void {
    $formatter = $this->createFormatter('test_toggle_debug');
    $formatter->save();
    $form_object = $this->getFormObject($formatter);

    $output = $this->invokeBuildPreviewOutput($form_object, 'html_token', [], TRUE);

    $this->assertArrayHasKey('preview', $output);
    $this->assertArrayHasKey('content', $output['preview']);
    $this->assertNotEmpty($output['preview']['content']['#markup']);
  }

  /**
   * Tests Php::previewDebugData() returns $items->getValue().
   */
  public function testPhpPreviewDebugData(): void {
    $formatter = $this->container->get('entity_type.manager')
      ->getStorage('formatter')
      ->create([
        'id' => 'test_php_debug',
        'label' => 'Test PHP Debug',
        'type' => 'php',
        'field_types' => ['text'],
        'data' => 'return "test";',
      ]);
    $formatter->save();
    $plugin = $formatter->getFormatterType();

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getValue')->willReturn([['value' => 'php test']]);

    $entity = $this->createMock(FieldableEntityInterface::class);

    $data = $plugin->previewDebugData($items, $entity);
    $this->assertEquals([['value' => 'php test']], $data['items']);
    $this->assertArrayHasKey('settings', $data);
  }

  /**
   * Tests Twig::previewDebugData() returns bundled data.
   */
  public function testTwigPreviewDebugData(): void {
    $formatter = $this->container->get('entity_type.manager')
      ->getStorage('formatter')
      ->create([
        'id' => 'test_twig_debug',
        'label' => 'Test Twig Debug',
        'type' => 'twig',
        'field_types' => ['text'],
        'data' => '{{ items }}',
      ]);
    $formatter->save();
    $plugin = $formatter->getFormatterType();

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getValue')->willReturn([['value' => 'twig test']]);
    $items->method('getLangcode')->willReturn('en');

    $entity = $this->createMock(FieldableEntityInterface::class);

    $data = $plugin->previewDebugData($items, $entity);
    $this->assertArrayHasKey('items', $data);
    $this->assertArrayHasKey('langcode', $data);
    $this->assertArrayHasKey('entity', $data);
    $this->assertEquals('en', $data['langcode']);
    $this->assertSame($entity, $data['entity']);
  }

  /**
   * Tests HTMLToken::previewDebugData() returns $entity.
   */
  public function testHtmlTokenPreviewDebugData(): void {
    $formatter = $this->container->get('entity_type.manager')
      ->getStorage('formatter')
      ->create([
        'id' => 'test_html_token_debug',
        'label' => 'Test HTMLToken Debug',
        'type' => 'html_token',
        'field_types' => ['text'],
        'data' => '[node:title]',
      ]);
    $formatter->save();
    $plugin = $formatter->getFormatterType();

    $items = $this->createMock(FieldItemListInterface::class);
    $entity = $this->createMock(FieldableEntityInterface::class);

    $data = $plugin->previewDebugData($items, $entity);
    $this->assertSame($entity, $data['entity']);
    $this->assertArrayHasKey('settings', $data);
  }

  /**
   * Tests getPreviewEntities returns nodes with body field data.
   */
  public function testGetPreviewEntities(): void {
    $user = $this->createUser(['bypass node access']);
    assert($user instanceof AccountInterface);
    $this->container->get('current_user')->setAccount($user);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Test preview entity',
        'body' => 'Some body text',
      ]);
    $node->save();

    $formatter = $this->createFormatter('test_get_entities');
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewEntities');
    $method->setAccessible(TRUE);

    $entities = $method->invoke($form_object, 'node', 'article', 'body');
    $this->assertNotEmpty($entities);
    $this->assertArrayHasKey($node->id(), $entities);
  }

  /**
   * Tests previewSelectsAjax returns the selects container.
   */
  public function testPreviewSelectsAjax(): void {
    $formatter = $this->createFormatter('test_ajax_selects');
    $form_object = $this->getFormObject($formatter);
    $form = $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $result = $form_object->previewSelectsAjax($form, $form_state);

    $this->assertArrayHasKey('entity_type', $result);
    $this->assertArrayHasKey('bundle', $result);
    $this->assertArrayHasKey('field', $result);
    $this->assertArrayHasKey('entity', $result);
  }

  /**
   * Tests previewAjaxCallback returns the output container.
   */
  public function testPreviewAjaxCallback(): void {
    $formatter = $this->createFormatter('test_ajax_output');
    $form_object = $this->getFormObject($formatter);
    $form = $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $result = $form_object->previewAjaxCallback($form, $form_state);

    $this->assertEquals('container', $result['#type']);
  }

  /**
   * Tests previewSubmit with incomplete selections sets warning message.
   */
  public function testPreviewSubmitIncompleteSelections(): void {
    $formatter = $this->createFormatter('test_incomplete_submit');
    $form_object = $this->getFormObject($formatter);
    $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $form_state->setValue(['preview', 'selects', 'entity_type'], 'node');
    $form_state->setValue(['preview', 'selects', 'bundle'], '');

    $form_object->previewSubmit([], $form_state);

    $output = $form_state->get('preview_output');
    $this->assertNotNull($output);
    $this->assertEquals('status_messages', $output['#theme']);
  }

  /**
   * Tests "Devel generate" option is absent when devel_generate is disabled.
   */
  public function testDevelGenerateOptionAbsentWithoutModule(): void {
    $formatter = $this->createFormatter('test_dg_absent');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $form = $this->buildForm($formatter);

    $entity_select = $form['preview']['selects']['entity'];
    $this->assertArrayNotHasKey('devel_generate', $entity_select['#options']);
  }

  /**
   * Tests "Devel generate" option appears when devel_generate is enabled.
   */
  public function testDevelGenerateOptionAppearsWhenAvailable(): void {
    $this->enableModules(['devel_generate']);

    $formatter = $this->createFormatter('test_dg_present');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $form = $this->buildForm($formatter);

    $entity_select = $form['preview']['selects']['entity'];
    $this->assertArrayHasKey('devel_generate', $entity_select['#options']);
    $this->assertEquals('Devel generate', $entity_select['#options']['devel_generate']);

    $options = (array) $entity_select['#options'];
    $non_empty_keys = array_filter(array_keys($options), fn($k) => $k !== '');
    $this->assertEquals('devel_generate', reset($non_empty_keys));
  }

  /**
   * Tests "Devel generate" is the default selection when available.
   */
  public function testDevelGenerateIsDefaultSelection(): void {
    $this->enableModules(['devel_generate']);

    $formatter = $this->createFormatter('test_dg_default');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $form = $this->buildForm($formatter);

    $entity_select = $form['preview']['selects']['entity'];
    $this->assertEquals('devel_generate', $entity_select['#default_value']);
  }

  /**
   * Tests getPreviewDefaults returns 'devel_generate' when available.
   */
  public function testGetPreviewDefaultsWithDevelGenerate(): void {
    $this->enableModules(['devel_generate']);

    $formatter = $this->createFormatter('test_dg_defaults');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewDefaults');
    $method->setAccessible(TRUE);

    $defaults = $method->invoke($form_object, new FormState());
    $this->assertEquals('node', $defaults['entity_type']);
    $this->assertEquals('article', $defaults['bundle']);
    $this->assertEquals('body', $defaults['field']);
    $this->assertEquals('devel_generate', $defaults['entity']);
  }

  /**
   * Tests previewSubmit with devel_generate entity produces output.
   */
  public function testPreviewSubmitWithDevelGenerate(): void {
    $this->enableModules(['filter', 'devel_generate']);
    $this->installConfig(['filter']);

    $formatter = $this->createFormatter('test_dg_submit');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $formatter->save();
    $form_object = $this->getFormObject($formatter);
    $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $form_state->setValue(['preview', 'selects', 'entity_type'], 'node');
    $form_state->setValue(['preview', 'selects', 'bundle'], 'article');
    $form_state->setValue(['preview', 'selects', 'field'], 'body');
    $form_state->setValue(['preview', 'selects', 'entity'], 'devel_generate');

    $form_object->previewSubmit([], $form_state);

    $output = $form_state->get('preview_output');
    $this->assertNotNull($output);
    $this->assertArrayNotHasKey('#theme', $output);

    $node_count = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $node_count);
  }

  /**
   * Tests previewSubmit shows error when Devel Generate is unavailable.
   */
  public function testPreviewSubmitDevelGenerateNotAvailable(): void {
    $formatter = $this->createFormatter('test_dg_unavailable');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $formatter->save();
    $form_object = $this->getFormObject($formatter);
    $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $form_state->setValue(['preview', 'selects', 'entity_type'], 'node');
    $form_state->setValue(['preview', 'selects', 'bundle'], 'article');
    $form_state->setValue(['preview', 'selects', 'field'], 'body');
    $form_state->setValue(['preview', 'selects', 'entity'], 'devel_generate');

    $form_object->previewSubmit([], $form_state);

    $output = $form_state->get('preview_output');
    $this->assertNotNull($output);
    $this->assertEquals('status_messages', $output['#theme']);
    $this->assertEquals('Devel Generate is not available.', (string) $output['#message_list']['error'][0]);
  }

  /**
   * Tests previewSubmit shows error when entity generation fails.
   */
  public function testPreviewSubmitDevelGenerateGenerationFails(): void {
    $this->enableModules(['devel_generate']);

    $mock = $this->createMock(DevelGenerateIntegration::class);
    $mock->method('isAvailable')->willReturn(TRUE);
    $mock->method('isEntityTypeSupported')->willReturn(TRUE);
    $mock->method('generateEntity')->willReturn(NULL);
    $this->container->set('custom_formatters.devel_generate_integration', $mock);

    $formatter = $this->createFormatter('test_dg_gen_fail');
    $formatter->set('field_types', ['text', 'text_with_summary']);
    $formatter->save();
    $form_object = $this->getFormObject($formatter);
    $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $form_state->setValue(['preview', 'selects', 'entity_type'], 'node');
    $form_state->setValue(['preview', 'selects', 'bundle'], 'article');
    $form_state->setValue(['preview', 'selects', 'field'], 'body');
    $form_state->setValue(['preview', 'selects', 'entity'], 'devel_generate');

    $form_object->previewSubmit([], $form_state);

    $output = $form_state->get('preview_output');
    $this->assertNotNull($output);
    $this->assertEquals('status_messages', $output['#theme']);
    $this->assertStringContainsString('Unable to generate', (string) $output['#message_list']['error'][0]);
  }

  /**
   * Tests getPreviewEntities label format includes entity ID.
   */
  public function testGetPreviewEntitiesLabelFormat(): void {
    $user = $this->createUser(['bypass node access']);
    assert($user instanceof AccountInterface);
    $this->container->get('current_user')->setAccount($user);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'title' => 'Test label format',
        'body' => 'Some body text',
      ]);
    $node->save();

    $formatter = $this->createFormatter('test_entity_labels');
    $form_object = $this->getFormObject($formatter);

    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('getPreviewEntities');
    $method->setAccessible(TRUE);

    $entities = $method->invoke($form_object, 'node', 'article', 'body');
    $this->assertNotEmpty($entities);
    $this->assertArrayHasKey($node->id(), $entities);
    $this->assertEquals("Test label format [eid:{$node->id()}]", $entities[$node->id()]);
  }

  /**
   * Tests settings widgets render in preview without a saved form display.
   */
  public function testPreviewSettingsFieldsRenderWithoutSavedFormDisplay(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_preview_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_preview_flag',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_preview_settings');
    $formatter->save();
    FieldConfig::create([
      'field_name' => 'field_preview_text',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Preview text',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_preview_flag',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Preview flag',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $renderer = $this->container->get('renderer');
    $form_object = $this->getFormObject($formatter);
    $form = $renderer->executeInRenderContext(new RenderContext(), function () use ($form_object) {
      return $this->container->get('form_builder')->getForm($form_object);
    });

    $this->assertArrayHasKey('preview', $form);
    $this->assertArrayHasKey('settings', $form['preview'], 'Preview settings fieldset exists when configurable fields are present.');
    $this->assertArrayHasKey('field_preview_text', $form['preview']['settings'], 'String settings field widget renders in preview without saved form display.');
    $this->assertArrayHasKey('field_preview_flag', $form['preview']['settings'], 'Boolean settings field widget renders in preview without saved form display.');
  }

  /**
   * Tests that a string field value is extracted from form state and rendered.
   */
  public function testExtractPreviewSettingsReturnsRenderedValues(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_test_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_extract_string');
    $formatter->save();

    FieldConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Test text',
    ])->save();

    $this->createViewDisplay('formatter_setting', (string) $formatter->id(), [
      'field_test_text' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
    ]);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_test_text', 0, 'value'], 'my-class');

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertArrayHasKey('field_test_text', $result);
    $this->assertStringContainsString('my-class', $result['field_test_text']);
  }

  /**
   * Tests an empty array is returned when the formatter has no settings fields.
   */
  public function testExtractPreviewSettingsReturnsEmptyWithoutFields(): void {
    $formatter = $this->createFormatter('test_extract_empty');
    $formatter->save();

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertSame([], $result);
  }

  /**
   * Tests that a boolean field is rendered via its view formatter, not raw 0/1.
   */
  public function testExtractPreviewSettingsBooleanRendersYesNo(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_test_flag',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_extract_bool');
    $formatter->save();

    FieldConfig::create([
      'field_name' => 'field_test_flag',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Test flag',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $this->createViewDisplay('formatter_setting', (string) $formatter->id(), [
      'field_test_flag' => ['type' => 'boolean', 'settings' => ['format' => 'yes-no']],
    ]);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_test_flag', 'value'], 1);
    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);
    $this->assertArrayHasKey('field_test_flag', $result);
    $this->assertStringContainsString('Yes', $result['field_test_flag']);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_test_flag', 'value'], 0);
    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);
    $this->assertArrayHasKey('field_test_flag', $result);
    $this->assertStringContainsString('No', $result['field_test_flag']);
  }

  /**
   * Tests that extractPreviewSettings() populates the _raw sub-array.
   *
   * Verifies that each configurable field produces both a rendered value and
   * an unformatted getString() value under _raw, matching the settings
   * structure passed to engine plugins at render time.
   */
  public function testExtractPreviewSettingsPopulatesRawArray(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_raw_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_extract_raw');
    $formatter->save();

    FieldConfig::create([
      'field_name' => 'field_raw_text',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Raw text',
    ])->save();

    $this->createViewDisplay('formatter_setting', (string) $formatter->id(), [
      'field_raw_text' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
    ]);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_raw_text', 0, 'value'], 'plain-value');

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertArrayHasKey('_raw', $result, 'The _raw key is present in extracted settings.');
    $this->assertArrayHasKey('field_raw_text', $result['_raw'], 'The raw value for field_raw_text is present in _raw.');
    $this->assertEquals('plain-value', $result['_raw']['field_raw_text'], 'The raw value is the unformatted field string.');
  }

  /**
   * Tests that the _raw value differs from the rendered value for boolean.
   *
   * Boolean fields render via their formatter label (e.g. "Yes"), while the
   * _raw sub-array contains the unformatted getString() output ("1" or "0").
   */
  public function testExtractPreviewSettingsRawDiffersFromRenderedForBoolean(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_raw_bool',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_extract_raw_bool');
    $formatter->save();

    FieldConfig::create([
      'field_name' => 'field_raw_bool',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Raw bool',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $this->createViewDisplay('formatter_setting', (string) $formatter->id(), [
      'field_raw_bool' => ['type' => 'boolean', 'settings' => ['format' => 'yes-no']],
    ]);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_raw_bool', 'value'], 1);

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertStringContainsString('Yes', $result['field_raw_bool'], 'The rendered value uses the boolean formatter label.');
    $this->assertArrayHasKey('_raw', $result);
    $this->assertEquals('1', $result['_raw']['field_raw_bool'], 'The raw value is the unformatted getString() output.');
  }

  /**
   * Tests that no _raw key is added when there are no configurable fields.
   *
   * Verifies that extractPreviewSettings() returns an empty array and does
   * not inject a _raw key when there are no configurable fields to populate.
   */
  public function testExtractPreviewSettingsNoRawWithoutFields(): void {
    $formatter = $this->createFormatter('test_extract_no_raw');
    $formatter->save();

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertSame([], $result, 'The settings array is empty when no configurable fields exist.');
    $this->assertArrayNotHasKey('_raw', $result, 'No _raw key is present when there are no configurable fields.');
  }

  /**
   * Tests multiple settings fields are all extracted and rendered correctly.
   */
  public function testExtractPreviewSettingsMultipleFields(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_multi_text',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_multi_flag',
      'type' => 'boolean',
      'entity_type' => 'formatter_setting',
    ])->save();

    $formatter = $this->createFormatter('test_extract_multi');
    $formatter->save();

    FieldConfig::create([
      'field_name' => 'field_multi_text',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Multi text',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_multi_flag',
      'entity_type' => 'formatter_setting',
      'bundle' => (string) $formatter->id(),
      'label' => 'Multi flag',
      'settings' => ['on_label' => 'Yes', 'off_label' => 'No'],
    ])->save();

    $this->createViewDisplay('formatter_setting', (string) $formatter->id(), [
      'field_multi_text' => ['type' => 'string', 'settings' => ['link_to_entity' => FALSE]],
      'field_multi_flag' => ['type' => 'boolean', 'settings' => ['format' => 'yes-no']],
    ]);

    [$form, $form_state, $form_object] = $this->buildFormWithState($formatter);
    $form_state->setValue(['preview', 'settings', 'field_multi_text', 0, 'value'], 'hello');
    $form_state->setValue(['preview', 'settings', 'field_multi_flag', 'value'], 1);

    $result = $this->invokeExtractPreviewSettings($form_object, $form, $form_state);

    $this->assertArrayHasKey('field_multi_text', $result);
    $this->assertStringContainsString('hello', $result['field_multi_text']);
    $this->assertArrayHasKey('field_multi_flag', $result);
    $this->assertStringContainsString('Yes', $result['field_multi_flag']);
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
   * Builds the formatter edit form, exposing the form state.
   *
   * Unlike buildForm(), this exposes the form state so callers can read values
   * set by #process callbacks (e.g. preview_settings_entity) and inject widget
   * values before calling extractPreviewSettings().
   *
   * @return array
   *   Tuple of the built form array, the populated form state, and the
   *   initialised form object.
   */
  private function buildFormWithState(FormatterInterface $formatter): array {
    $form_object = $this->getFormObject($formatter);
    $form_state = new FormState();
    $renderer = $this->container->get('renderer');
    $form = $renderer->executeInRenderContext(new RenderContext(), function () use ($form_object, $form_state) {
      return $this->container->get('form_builder')->buildForm($form_object, $form_state);
    });
    return [$form, $form_state, $form_object];
  }

  /**
   * Invokes the protected extractPreviewSettings method via reflection.
   *
   * @param \Drupal\custom_formatters\Form\FormatterForm $form_object
   *   The form object.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The extracted settings keyed by field name.
   */
  private function invokeExtractPreviewSettings(FormatterForm $form_object, array $form, FormStateInterface $form_state): array {
    $ref = new \ReflectionClass($form_object);
    $method = $ref->getMethod('extractPreviewSettings');
    $method->setAccessible(TRUE);
    return $method->invoke($form_object, $form, $form_state);
  }

  /**
   * Creates and saves an EntityViewDisplay for the given entity type/bundle.
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
   * Invokes the protected buildPreviewOutput method via reflection.
   *
   * @param \Drupal\custom_formatters\Form\FormatterForm $form_object
   *   The form object.
   * @param string $plugin_id
   *   The formatter type plugin ID (e.g. 'php', 'html_token').
   * @param array $settings
   *   The preview debug settings.
   * @param bool $toggle
   *   Whether to render with full field theming.
   *
   * @return array
   *   The preview output render array.
   */
  private function invokeBuildPreviewOutput(FormatterForm $form_object, string $plugin_id, array $settings, bool $toggle = FALSE): array {
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
      $toggle,
      $formatter_type
    );
  }

}
