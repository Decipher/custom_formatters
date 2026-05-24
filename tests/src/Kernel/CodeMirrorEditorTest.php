<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for CodeMirror editor integration in formatter type plugins.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\FormatterTypeBase;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests CodeMirror editor integration for formatter type plugins.
 *
 * Verifies that engine plugins produce CodeMirror form elements when the
 * codemirror_editor module is installed, and plain textarea elements when
 * it is not.
 *
 * @group custom_formatters
 */
class CodeMirrorEditorTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['custom_formatters', 'field', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('custom_formatters');
  }

  /**
   * Tests PHP engine falls back to textarea without codemirror_editor.
   */
  public function testPhpEngineFallbackTextarea(): void {
    $form = $this->buildSettingsForm('php', 'test_php_fallback');
    $this->assertSame('textarea', $form['data']['#type']);
    $this->assertArrayNotHasKey('#codemirror', $form['data']);
  }

  /**
   * Tests HTML+Token engine falls back to textarea without codemirror_editor.
   */
  public function testHtmlTokenEngineFallbackTextarea(): void {
    $form = $this->buildSettingsForm('html_token', 'test_html_fallback');
    $this->assertSame('textarea', $form['data']['#type']);
    $this->assertArrayNotHasKey('#codemirror', $form['data']);
  }

  /**
   * Tests Twig engine falls back to textarea without codemirror_editor.
   */
  public function testTwigEngineFallbackTextarea(): void {
    $form = $this->buildSettingsForm('twig', 'test_twig_fallback');
    $this->assertSame('textarea', $form['data']['#type']);
    $this->assertArrayNotHasKey('#codemirror', $form['data']);
  }

  /**
   * Tests PHP engine uses CodeMirror with PHP mode when available.
   */
  public function testPhpEngineWithCodeMirror(): void {
    $this->installCodeMirror();
    $form = $this->buildSettingsForm('php', 'test_php_cm');

    $this->assertSame('codemirror', $form['data']['#type']);
    $this->assertCodeMirrorSettings($form['data'], 'application/x-httpd-php');
    $this->assertArrayNotHasKey('autoCloseTags', $form['data']['#codemirror']);
  }

  /**
   * Tests HTML+Token engine uses CodeMirror with HTML mode and auto-close tags.
   */
  public function testHtmlTokenEngineWithCodeMirror(): void {
    $this->installCodeMirror();
    $form = $this->buildSettingsForm('html_token', 'test_html_cm');

    $this->assertSame('codemirror', $form['data']['#type']);
    $this->assertCodeMirrorSettings($form['data'], 'text/html');
    $this->assertTrue($form['data']['#codemirror']['autoCloseTags']);
  }

  /**
   * Tests Twig engine uses CodeMirror with Twig mode and auto-close tags.
   */
  public function testTwigEngineWithCodeMirror(): void {
    $this->installCodeMirror();
    $form = $this->buildSettingsForm('twig', 'test_twig_cm');

    $this->assertSame('codemirror', $form['data']['#type']);
    $this->assertCodeMirrorSettings($form['data'], 'twig');
    $this->assertTrue($form['data']['#codemirror']['autoCloseTags']);
  }

  /**
   * Tests Formatter Preset engine is unaffected by codemirror_editor.
   */
  public function testFormatterPresetNotAffectedByCodeMirror(): void {
    $this->installCodeMirror();
    $formatter_type = $this->createFormatter('test_preset_cm', 'formatter_preset')->getFormatterType();
    \assert($formatter_type !== FALSE);
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('field_types', 'text');
    $result = $formatter_type->settingsForm($form, $form_state);

    $this->assertSame('container', $result['data']['#type']);
    $this->assertArrayNotHasKey('#codemirror', $result['data']);
  }

  /**
   * Tests that base class defaults produce a plain textarea element.
   */
  public function testBaseClassDefaultsToTextarea(): void {
    $formatter = $this->createFormatter('test_base_default', 'php');
    $plugin = new class(
      ['entity' => $formatter],
      'test',
      ['id' => 'test', 'label' => 'Test'],
      \Drupal::service('module_handler'),
    ) extends FormatterTypeBase {

      /**
       * {@inheritdoc}
       *
       * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
       *   The field values to be rendered.
       * @param string $langcode
       *   The language that should be used to render the field.
       *
       * @return array
       *   A renderable array.
       */
      public function viewElements(FieldItemListInterface $items, $langcode): array {
        return [];
      }

    };

    $form = [];
    $form_state = new FormState();
    $result = $plugin->settingsForm($form, $form_state);

    $this->assertSame('textarea', $result['data']['#type']);
    $this->assertArrayNotHasKey('#codemirror', $result['data']);
  }

  /**
   * Builds a settings form for a given engine type.
   *
   * @param string $engine_type
   *   The formatter engine plugin ID.
   * @param string $id
   *   The formatter config entity ID.
   *
   * @return array
   *   The rendered form array.
   */
  private function buildSettingsForm(string $engine_type, string $id): array {
    $formatter_type = $this->createFormatter($id, $engine_type)->getFormatterType();
    \assert($formatter_type !== FALSE);
    $form = [];
    $form_state = new FormState();
    return $formatter_type->settingsForm($form, $form_state);
  }

  /**
   * Creates a formatter config entity.
   *
   * @param string $id
   *   The formatter config entity ID.
   * @param string $type
   *   The formatter engine plugin ID.
   *
   * @return \Drupal\custom_formatters\FormatterInterface
   *   The created formatter entity.
   */
  private function createFormatter(string $id, string $type): FormatterInterface {
    $formatter = \Drupal::entityTypeManager()->getStorage('formatter')->create([
      'id' => $id,
      'label' => 'Test ' . $id,
      'type' => $type,
      'field_types' => ['text'],
      'data' => 'test data',
    ]);
    \assert($formatter instanceof FormatterInterface);
    return $formatter;
  }

  /**
   * Enables the codemirror_editor module for testing.
   */
  private function installCodeMirror(): void {
    if (!\Drupal::service('extension.list.module')->exists('codemirror_editor')) {
      $this->markTestSkipped('The codemirror_editor module is not available.');
    }
    $this->enableModules(['codemirror_editor']);
  }

  /**
   * Asserts common CodeMirror settings on a form element.
   *
   * @param array $element
   *   The form element to inspect.
   * @param string $expected_mode
   *   The expected CodeMirror language mode.
   */
  private function assertCodeMirrorSettings(array $element, string $expected_mode): void {
    $this->assertArrayHasKey('#codemirror', $element);

    $settings = $element['#codemirror'];
    $this->assertSame($expected_mode, $settings['mode']);
    $this->assertTrue($settings['lineNumbers']);
    $this->assertTrue($settings['lineWrapping']);
    $this->assertTrue($settings['styleActiveLine']);
    $this->assertFalse($settings['toolbar']);
  }

}
