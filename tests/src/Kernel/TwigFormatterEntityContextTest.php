<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Form\FormState;
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
  }

  /**
   * Tests that viewElements() passes entity to the Twig template context.
   *
   * @covers ::viewElements
   */
  public function testViewElementsExposesEntity(): void {
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

}
