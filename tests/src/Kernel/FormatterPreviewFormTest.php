<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the preview functionality in FormatterForm.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\custom_formatters\DevelGenerateIntegration;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
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
