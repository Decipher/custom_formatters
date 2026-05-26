<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for the DevelGenerateIntegration service.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\custom_formatters\DevelGenerateIntegration;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeTypeInterface;

/**
 * Tests the DevelGenerateIntegration service.
 *
 * @group custom_formatters
 */
class DevelGenerateIntegrationTest extends KernelTestBase {

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
   * The service under test.
   */
  protected DevelGenerateIntegration $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['custom_formatters', 'filter', 'node']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('node', 'node_access');
    node_access_rebuild();

    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'type' => 'article',
        'name' => 'Article',
      ]);
    assert($node_type instanceof NodeTypeInterface);

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

    if (!FieldConfig::loadByName('node', 'article', 'body')) {
      FieldConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'Body',
      ])->save();
    }

    $this->service = $this->container->get('custom_formatters.devel_generate_integration');
  }

  /**
   * Tests isAvailable() returns FALSE when devel_generate is not enabled.
   */
  public function testIsAvailableFalseWithoutModule(): void {
    $this->assertFalse($this->service->isAvailable());
  }

  /**
   * Tests isAvailable() returns TRUE when devel_generate is enabled.
   */
  public function testIsAvailableTrueWithModule(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');
    $this->assertTrue($service->isAvailable());
  }

  /**
   * Tests isEntityTypeSupported() for supported and unsupported types.
   */
  public function testIsEntityTypeSupported(): void {
    $this->assertTrue($this->service->isEntityTypeSupported('node'));
    $this->assertTrue($this->service->isEntityTypeSupported('taxonomy_term'));
    $this->assertTrue($this->service->isEntityTypeSupported('user'));
    $this->assertTrue($this->service->isEntityTypeSupported('media'));
    $this->assertFalse($this->service->isEntityTypeSupported('block_content'));
    $this->assertFalse($this->service->isEntityTypeSupported('paragraph'));
    $this->assertFalse($this->service->isEntityTypeSupported('comment'));
  }

  /**
   * Tests generateEntity() returns NULL when Devel Generate is unavailable.
   */
  public function testGenerateEntityReturnsNullWhenUnavailable(): void {
    $this->assertNull($this->service->generateEntity('node', 'article'));
  }

  /**
   * Tests generateEntity() returns NULL for unsupported entity types.
   */
  public function testGenerateEntityReturnsNullWhenUnsupportedType(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');
    $this->assertNull($service->generateEntity('block_content', 'basic'));
  }

  /**
   * Tests generateEntity() creates a node with correct default values.
   */
  public function testGenerateEntityNode(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');

    $entity = $service->generateEntity('node', 'article');
    $this->assertNotNull($entity);
    $this->assertEquals('node', $entity->getEntityTypeId());
    $this->assertEquals('article', $entity->bundle());
    $this->assertEquals('Sample Article', $entity->label());
    $this->assertEquals(1, $entity->get('status')->value);
  }

  /**
   * Tests generated entities are transient and never saved to the database.
   */
  public function testGenerateEntityIsTransient(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');

    $entity = $service->generateEntity('node', 'article');
    $this->assertNotNull($entity);
    $this->assertTrue($entity->isNew());
    $this->assertNull($entity->id());

    $count = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    $this->assertEquals(0, $count);
  }

  /**
   * Tests generateEntity() creates a user with correct default values.
   */
  public function testGenerateEntityUser(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');

    $entity = $service->generateEntity('user', 'user');
    $this->assertNotNull($entity);
    $this->assertEquals('user', $entity->getEntityTypeId());
    $this->assertEquals('sample_user', $entity->label());
    $this->assertEquals('sample@example.com', $entity->getEmail());
    $this->assertEquals(1, $entity->get('status')->value);
  }

  /**
   * Tests generateEntity() catches exceptions and returns NULL.
   */
  public function testGenerateEntityExceptionReturnsNull(): void {
    $this->enableModules(['devel_generate']);
    $service = $this->container->get('custom_formatters.devel_generate_integration');

    $entity = $service->generateEntity('media', 'image');
    $this->assertNull($entity);
  }

  /**
   * Tests getBundleLabel() returns the human-readable bundle label.
   */
  public function testGetBundleLabel(): void {
    $ref = new \ReflectionClass($this->service);
    $method = $ref->getMethod('getBundleLabel');
    $method->setAccessible(TRUE);

    $label = $method->invoke($this->service, 'node', 'article');
    $this->assertEquals('Article', $label);
  }

  /**
   * Tests getBundleLabel() falls back to the bundle ID for unknown bundles.
   */
  public function testGetBundleLabelFallback(): void {
    $ref = new \ReflectionClass($this->service);
    $method = $ref->getMethod('getBundleLabel');
    $method->setAccessible(TRUE);

    $label = $method->invoke($this->service, 'node', 'nonexistent');
    $this->assertEquals('nonexistent', $label);
  }

}
