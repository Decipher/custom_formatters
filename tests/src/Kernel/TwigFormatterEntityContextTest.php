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
    $this->assertStringContainsString('{{ entity_url }}', $description);
  }

  /**
   * Tests that entity_url gives the canonical URL without a sandbox error.
   *
   * Drupal's default Twig sandbox policy does not allow calling toUrl()
   * directly on an entity object in a template (SecurityError). entity_url
   * is computed in PHP instead, replicating the shipped example_twig_title
   * formatter's "Link to entity" behavior.
   *
   * @covers ::viewElements
   */
  public function testViewElementsExposesEntityUrl(): void {
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
      'title' => 'Linkable article',
      'body' => [
        'value' => 'Test body content',
        'summary' => 'Test summary',
      ],
    ]);
    $node->save();

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'twig_entity_url_test',
        'label' => 'Twig Entity URL Test',
        'type' => 'twig',
        'field_types' => ['text_with_summary'],
        'data' => '<a href="{{ entity_url }}">{{ entity.label }}</a>',
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $items = $node->get('body');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result);
    $this->assertArrayHasKey('#markup', $result);
    $markup = (string) $result['#markup'];
    $this->assertStringContainsString($node->toUrl('canonical')->toString(), $markup);
    $this->assertStringContainsString('Linkable article', $markup);
  }

  /**
   * Tests that entity_url is an empty string for an entity with no ID.
   *
   * @covers ::viewElements
   */
  public function testViewElementsEntityUrlEmptyForUnsavedEntity(): void {
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
      'title' => 'Unsaved article',
      'body' => [
        'value' => 'Test body content',
      ],
    ]);

    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'twig_entity_url_unsaved_test',
        'label' => 'Twig Entity URL Unsaved Test',
        'type' => 'twig',
        'field_types' => ['text_with_summary'],
        'data' => '[{{ entity_url }}]',
      ]);
    $formatter->save();

    $formatter_type = $formatter->getFormatterType();
    $this->assertNotFalse($formatter_type);

    $items = $node->get('body');
    $result = $formatter_type->viewElements($items, 'en');

    $this->assertNotEmpty($result);
    $this->assertSame('[]', (string) $result['#markup']);
  }

}
