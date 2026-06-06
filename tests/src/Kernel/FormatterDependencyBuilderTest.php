<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\custom_formatters\FormatterDependencyBuilder;
use Drupal\custom_formatters\FormatterInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests FormatterDependencyBuilder::getDependentEntities().
 *
 * Verifies that formatter_setting view displays and field configs are excluded
 * while content entity view displays (e.g. node) are returned.
 *
 * @group custom_formatters
 */
class FormatterDependencyBuilderTest extends KernelTestBase {

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
   * The dependency builder service under test.
   */
  private FormatterDependencyBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('formatter_setting');
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

    $this->builder = \Drupal::service('custom_formatters.dependency_builder');
  }

  /**
   * Tests that a formatter with no dependents returns an empty array.
   */
  public function testNoDependentsReturnsEmpty(): void {
    $formatter = $this->createFormatter('dep_none');
    $this->assertSame([], $this->builder->getDependentEntities($formatter));
  }

  /**
   * Tests that a node view display using the formatter is returned.
   */
  public function testNodeViewDisplayIsReturned(): void {
    $formatter = $this->createFormatter('dep_node');
    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'body' => [
          'type' => 'custom_formatters:' . $formatter->id(),
          'label' => 'hidden',
          'settings' => [],
          'third_party_settings' => [],
          'weight' => 0,
          'region' => 'content',
        ],
      ],
    ])->save();

    $dependents = $this->builder->getDependentEntities($formatter);
    $this->assertCount(1, $dependents);
    $display = reset($dependents);
    $this->assertInstanceOf(EntityViewDisplay::class, $display);
    $this->assertSame('node', $display->getTargetEntityTypeId());
  }

  /**
   * Tests that a formatter_setting view display is excluded from dependents.
   */
  public function testFormatterSettingViewDisplayFiltered(): void {
    $formatter = $this->createFormatter('dep_settings_view');
    EntityViewDisplay::create([
      'targetEntityType' => 'formatter_setting',
      'bundle' => $formatter->id(),
      'mode' => 'default',
      'status' => TRUE,
      'content' => [],
    ])->save();

    $this->assertSame([], $this->builder->getDependentEntities($formatter));
  }

  /**
   * Tests that field configs on a formatter_setting bundle are excluded.
   */
  public function testFieldConfigEntitiesFiltered(): void {
    $formatter = $this->createFormatter('dep_fc');
    FieldStorageConfig::create([
      'field_name' => 'field_dep_label',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_dep_label',
      'entity_type' => 'formatter_setting',
      'bundle' => $formatter->id(),
      'label' => 'Dep label',
    ])->save();

    $this->assertSame([], $this->builder->getDependentEntities($formatter));
  }

  /**
   * Tests that only content view displays are returned when mixed deps exist.
   */
  public function testMixedDependentsOnlyReturnsContentDisplays(): void {
    $formatter = $this->createFormatter('dep_mix');

    EntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
      'content' => [
        'body' => [
          'type' => 'custom_formatters:' . $formatter->id(),
          'label' => 'hidden',
          'settings' => [],
          'third_party_settings' => [],
          'weight' => 0,
          'region' => 'content',
        ],
      ],
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'formatter_setting',
      'bundle' => $formatter->id(),
      'mode' => 'default',
      'status' => TRUE,
      'content' => [],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_mix_label',
      'type' => 'string',
      'entity_type' => 'formatter_setting',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_mix_label',
      'entity_type' => 'formatter_setting',
      'bundle' => $formatter->id(),
      'label' => 'Mix label',
    ])->save();

    $dependents = $this->builder->getDependentEntities($formatter);
    $this->assertCount(1, $dependents);
    $display = reset($dependents);
    $this->assertInstanceOf(EntityViewDisplay::class, $display);
    $this->assertSame('node', $display->getTargetEntityTypeId());
  }

  /**
   * Creates and saves a twig formatter entity with the given ID.
   */
  private function createFormatter(string $id): FormatterInterface {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => $id,
        'label' => $id,
        'type' => 'twig',
        'field_types' => ['text_with_summary', 'text_long'],
        'data' => '{{ items[0].value }}',
      ]);
    \assert($formatter instanceof FormatterInterface);
    $formatter->save();
    \Drupal::service('plugin.manager.field.formatter')->clearCachedDefinitions();
    return $formatter;
  }

}
