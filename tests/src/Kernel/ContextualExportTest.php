<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for contextual links suppression in non-HTML request formats.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\custom_formatters\FormatterInterface;
use Drupal\custom_formatters\Plugin\CustomFormatters\FormatterExtras\Contextual;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that contextual link placeholders are suppressed in non-HTML formats.
 *
 * Verifies that the Contextual extras plugin only adds contextual link
 * placeholders when the current request format is HTML, preventing raw
 * placeholder markup from leaking into CSV/JSON exports.
 *
 * @group custom_formatters
 */
class ContextualExportTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['custom_formatters', 'field', 'system', 'user', 'contextual'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('custom_formatters');
  }

  /**
   * Tests that contextual links are added when request format is HTML.
   */
  public function testContextualLinksPresentInHtmlFormat(): void {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'test_contextual_html',
        'label' => 'Test Contextual HTML',
        'type' => 'html_token',
        'field_types' => ['string'],
        'data' => '[node:title]',
      ]);
    assert($formatter instanceof FormatterInterface);
    $formatter->save();

    $request_stack = \Drupal::requestStack();
    $request = Request::create('/');
    $request->setRequestFormat('html');
    $request_stack->push($request);

    try {
      $plugin = $this->createContextualPlugin($formatter);
      $element = [['#markup' => 'test output']];
      $plugin->formatterViewElementsAlter($element);

      $this->assertArrayHasKey('contextual_links', $element[0]);
      $this->assertEquals('contextual_links_placeholder', $element[0]['contextual_links']['#type']);
    }
    finally {
      $request_stack->pop();
    }
  }

  /**
   * Tests that contextual links are suppressed in non-HTML request formats.
   */
  public function testContextualLinksSuppressedInNonHtmlFormat(): void {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'test_contextual_csv',
        'label' => 'Test Contextual CSV',
        'type' => 'html_token',
        'field_types' => ['string'],
        'data' => '[node:title]',
      ]);
    assert($formatter instanceof FormatterInterface);
    $formatter->save();

    $request_stack = \Drupal::requestStack();
    $request = Request::create('/');
    $request->setRequestFormat('csv');
    $request_stack->push($request);

    try {
      $plugin = $this->createContextualPlugin($formatter);
      $element = [['#markup' => 'test output']];
      $plugin->formatterViewElementsAlter($element);

      $this->assertArrayNotHasKey('contextual_links', $element[0]);
      $this->assertEquals('test output', $element[0]['#markup']);
    }
    finally {
      $request_stack->pop();
    }
  }

  /**
   * Creates a Contextual extras plugin instance for testing.
   */
  private function createContextualPlugin(FormatterInterface $formatter): Contextual {
    return Contextual::create(
      \Drupal::getContainer(),
      ['entity' => $formatter],
      'contextual',
      [
        'id' => 'contextual',
        'label' => 'Contextual links',
        'description' => 'Behaviour for Contextual links integration.',
        'dependencies' => ['module' => ['contextual']],
      ],
    );
  }

}
