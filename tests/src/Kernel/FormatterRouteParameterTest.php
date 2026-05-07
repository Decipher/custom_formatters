<?php

declare(strict_types=1);

/**
 * @file
 * Kernel tests for formatter entity route parameter alignment.
 */

namespace Drupal\Tests\custom_formatters\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that Formatter entity route parameters match the entity type ID.
 *
 * Regression test for issue #3387578 - MissingMandatoryParametersException
 * when Devel module generates entity.formatter.devel_load route, because
 * the entity links used {custom_formatter} instead of {formatter}.
 *
 * @group custom_formatters
 */
class FormatterRouteParameterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['custom_formatters'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('custom_formatters');
  }

  /**
   * Test that entity link URLs generate correctly with the entity type ID.
   */
  public function testRouteParameterMatchesEntityTypeId(): void {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create([
        'id' => 'test_route_param',
        'label' => 'Test Route Param',
        'type' => 'html_token',
        'field_types' => ['text'],
      ]);
    $formatter->save();

    $edit_url = $formatter->toUrl('edit-form')->toString(TRUE)->getGeneratedUrl();
    $this->assertEquals('/admin/structure/formatters/manage/test_route_param', $edit_url);

    $delete_url = $formatter->toUrl('delete-form')->toString(TRUE)->getGeneratedUrl();
    $this->assertEquals('/admin/structure/formatters/manage/test_route_param/delete', $delete_url);

    $collection_url = $formatter->toUrl('collection')->toString(TRUE)->getGeneratedUrl();
    $this->assertEquals('/admin/structure/formatters', $collection_url);
  }

  /**
   * Test that entity link templates use the entity type ID as parameter.
   */
  public function testLinkTemplatesUseEntityTypeIdParameter(): void {
    $entity_type = \Drupal::entityTypeManager()->getDefinition('formatter');
    $links = $entity_type->get('links');

    foreach (['edit-form', 'delete-form'] as $link_key) {
      $this->assertArrayHasKey($link_key, $links);
      $this->assertStringContainsString('{formatter}', $links[$link_key],
        "Link template '{$link_key}' must use {formatter} parameter."
      );
      $this->assertStringNotContainsString('{custom_formatter}', $links[$link_key],
        "Link template '{$link_key}' must NOT use {custom_formatter} parameter."
      );
    }
  }

}
