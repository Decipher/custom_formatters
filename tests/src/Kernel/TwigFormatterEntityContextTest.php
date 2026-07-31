<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\custom_formatters\Entity\Formatter;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that the Twig formatter exposes the parent entity in template context.
 *
 * @coversDefaultClass \Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType\Twig
 * @group custom_formatters
 */
class TwigFormatterEntityContextTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['custom_formatters', 'filter']);

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
  }

  /**
   * Tests that viewElements() passes entity to the Twig template context.
   *
   * @covers ::viewElements
   */
  public function testViewElementsExposesEntity(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test article title',
      'body' => [
        'value' => 'Test body content',
        'summary' => 'Test summary',
      ],
    ]);
    $node->save();

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'twig_entity_test',
        'label' => 'Twig Entity Test',
        'type' => 'twig',
        'field_types' => ['text_with_summary'],
        'data' => '{{ entity.label }}',
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $items = $node->get('body');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('#markup', $result);
    $this->assertStringContainsString('Test article title', (string) $result['#markup']);
  }

  /**
   * Tests that the settings form documents the entity variable.
   *
   * @covers ::settingsForm
   */
  public function testSettingsFormDocumentsEntity(): void {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'twig_settings_test',
        'label' => 'Twig Settings Test',
        'type' => 'twig',
        'data' => '{{ items }}',
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $form = [];
    $form_state = new FormState();
    $result = $formatter_type->settingsForm($form, $form_state);

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('#description', $result['data']);
    $description = (string) $result['data']['#description'];
    $this->assertStringContainsString('EntityInterface', $description);
    $this->assertStringContainsString('{{ entity }}', $description);
  }

  /**
   * Tests the shipped example_twig_title renders a sandbox-safe link.
   *
   * Loads the actual config/optional formatter entity so the test exercises
   * the real Twig template rather than a duplicated copy. Guards the link
   * branch: if the YAML reverts to entity.toUrl(), Drupal's TwigSandboxPolicy
   * blocks the method call (not in the allowed list, no get/has/is prefix)
   * and viewElements() returns empty markup, failing this test.
   *
   * @covers ::viewElements
   */
  public function testEntityLinkRendersUnderSandbox(): void {
    // The example_twig_title optional config is installed automatically once
    // its module dependency is met. Loading the real entity (instead of
    // duplicating the template) ensures the test fails if the shipped YAML
    // regresses.
    $formatter = Formatter::load('example_twig_title');
    $this->assertNotNull($formatter, 'The example_twig_title optional config is installed.');

    $node = Node::create([
      'type' => 'article',
      'title' => 'Linked title',
      'body' => ['value' => 'Body text'],
    ]);
    $node->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $result = $formatter_type->viewElements($node->get('body'), 'en', ['field_link' => 'Yes']);

    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('#markup', $result);
    $markup = (string) $result['#markup'];
    $this->assertStringContainsString('href="/node/' . $node->id() . '"', $markup);
    $this->assertStringNotContainsString('not allowed', $markup);
  }

}
