<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the codemirror_editor companion include file.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the modules/codemirror_editor.inc companion include file.
 *
 * The include file is auto-loaded by hook_module_implements_alter() using the
 * same D7-style pattern: modules/MODULE.inc is required_once'd for every
 * active module that has a matching file. These tests verify that the hook
 * implementations it defines are correctly registered and behave as expected.
 *
 * @group custom_formatters
 */
class CodeMirrorEditorIncTest extends KernelTestBase {

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
   * Tests that php, htmlmixed, and twig modes are marked as used.
   *
   * The codemirror_editor module only loads a mode's JS when the mode is
   * "active" — either listed in config or with a non-empty usage array.
   * custom_formatters must add itself to each mode it relies on so those
   * files are always included in the codemirror library.
   */
  public function testModeInfoAlterMarksFormatterModesAsUsed(): void {
    $modes = [
      'clike' => ['label' => 'C-like', 'usage' => [], 'mime_types' => ['text/x-csrc'], 'dependencies' => []],
      'php' => ['label' => 'PHP', 'usage' => [], 'mime_types' => ['text/x-php'], 'dependencies' => ['clike']],
      'htmlmixed' => ['label' => 'HTML mixed', 'usage' => [], 'mime_types' => ['text/html'], 'dependencies' => ['xml']],
      'twig' => ['label' => 'Twig', 'usage' => [], 'mime_types' => ['text/x-twig'], 'dependencies' => []],
      'css' => ['label' => 'CSS', 'usage' => [], 'mime_types' => ['text/css'], 'dependencies' => []],
    ];

    \Drupal::moduleHandler()->alter('codemirror_mode_info', $modes);

    // Clike must be listed directly; dependency ordering would otherwise load
    // clike.js after php.js, silently breaking PHP syntax highlighting.
    $this->assertContains('custom_formatters', $modes['clike']['usage']);
    $this->assertContains('custom_formatters', $modes['php']['usage']);
    $this->assertContains('custom_formatters', $modes['htmlmixed']['usage']);
    $this->assertContains('custom_formatters', $modes['twig']['usage']);
  }

  /**
   * Tests that modes not used by any formatter engine are left unchanged.
   */
  public function testModeInfoAlterIgnoresUnrelatedModes(): void {
    $modes = [
      'css' => ['label' => 'CSS', 'usage' => [], 'mime_types' => ['text/css'], 'dependencies' => []],
      'javascript' => ['label' => 'JavaScript', 'usage' => [], 'mime_types' => ['text/javascript'], 'dependencies' => []],
      'sql' => ['label' => 'SQL', 'usage' => [], 'mime_types' => ['text/x-sql'], 'dependencies' => []],
    ];

    \Drupal::moduleHandler()->alter('codemirror_mode_info', $modes);

    $this->assertNotContains('custom_formatters', $modes['css']['usage']);
    $this->assertNotContains('custom_formatters', $modes['javascript']['usage']);
    $this->assertNotContains('custom_formatters', $modes['sql']['usage']);
  }

  /**
   * Tests that existing usage entries on a mode are preserved.
   */
  public function testModeInfoAlterPreservesExistingUsage(): void {
    $modes = [
      'php' => ['label' => 'PHP', 'usage' => ['another_module'], 'mime_types' => [], 'dependencies' => []],
    ];

    \Drupal::moduleHandler()->alter('codemirror_mode_info', $modes);

    $this->assertContains('another_module', $modes['php']['usage']);
    $this->assertContains('custom_formatters', $modes['php']['usage']);
  }

  /**
   * Tests that hint addon JS files are added to the codemirror asset list.
   */
  public function testEditorAssetsAlterAddsHintJs(): void {
    $assets = ['js' => [], 'css' => []];

    \Drupal::moduleHandler()->alter('codemirror_editor_assets', $assets);

    $this->assertContains('addon/hint/show-hint.js', $assets['js']);
    $this->assertContains('addon/hint/anyword-hint.js', $assets['js']);
    $this->assertContains('addon/hint/xml-hint.js', $assets['js']);
    $this->assertContains('addon/hint/html-hint.js', $assets['js']);
  }

  /**
   * Tests that the hint stylesheet is added to the codemirror asset list.
   */
  public function testEditorAssetsAlterAddsHintCss(): void {
    $assets = ['js' => [], 'css' => []];

    \Drupal::moduleHandler()->alter('codemirror_editor_assets', $assets);

    $this->assertContains('addon/hint/show-hint.css', $assets['css']);
  }

  /**
   * Tests that existing assets are preserved when hint addons are appended.
   */
  public function testEditorAssetsAlterPreservesExistingAssets(): void {
    $assets = [
      'js' => ['lib/codemirror.js', 'mode/php/php.js'],
      'css' => ['lib/codemirror.css'],
    ];

    \Drupal::moduleHandler()->alter('codemirror_editor_assets', $assets);

    $this->assertContains('lib/codemirror.js', $assets['js']);
    $this->assertContains('mode/php/php.js', $assets['js']);
    $this->assertContains('lib/codemirror.css', $assets['css']);
  }

  /**
   * Tests that codemirror_editor/editor is added as a dependency of the lib.
   *
   * This dependency guarantees that editor.js — and the codeMirrorEditor
   * behavior — loads before codemirror-hints.js, so CodeMirror instances
   * exist in the DOM when our hints behavior's attach() runs.
   */
  public function testLibraryInfoAlterAddsCmEditorDependency(): void {
    if (!\Drupal::service('extension.list.module')->exists('codemirror_editor')) {
      $this->markTestSkipped('The codemirror_editor module is not available.');
    }
    $this->enableModules(['codemirror_editor']);

    $libraries = [
      'formatter_form' => [
        'js' => [],
        'css' => [],
        'dependencies' => ['core/claro', 'core/once'],
      ],
    ];

    $extension = 'custom_formatters';
    \Drupal::moduleHandler()->alter('library_info', $libraries, $extension);

    $this->assertContains(
      'codemirror_editor/editor',
      $libraries['formatter_form']['dependencies'],
      'The codemirror_editor/editor dependency is added when the module is enabled.',
    );
  }

  /**
   * Tests that no dependency is added when codemirror_editor is not enabled.
   *
   * Verifies the moduleExists() guard in hook_library_info_alter() so that
   * functional tests without codemirror_editor do not receive an unresolvable
   * library dependency.
   */
  public function testLibraryInfoAlterSkipsDependencyWhenCmEditorAbsent(): void {
    $libraries = [
      'formatter_form' => [
        'js' => [],
        'css' => [],
        'dependencies' => ['core/claro', 'core/once'],
      ],
    ];

    $extension = 'custom_formatters';
    \Drupal::moduleHandler()->alter('library_info', $libraries, $extension);

    $this->assertNotContains(
      'codemirror_editor/editor',
      $libraries['formatter_form']['dependencies'],
      'No codemirror_editor/editor dependency is added when the module is absent.',
    );
  }

  /**
   * Tests that library_info_alter does not touch unrelated libraries.
   */
  public function testLibraryInfoAlterIgnoresOtherExtensions(): void {
    $libraries = [
      'formatter_form' => [
        'dependencies' => [],
      ],
    ];

    $extension = 'some_other_module';
    \Drupal::moduleHandler()->alter('library_info', $libraries, $extension);

    $this->assertNotContains(
      'codemirror_editor/editor',
      $libraries['formatter_form']['dependencies'],
    );
  }

}
