<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Functional;

use Drupal\custom_formatters\FormatterInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for Custom Formatters functional tests.
 *
 * @package Drupal\custom_formatters\Tests
 */
abstract class CustomFormattersTestBase extends BrowserTestBase {

  use FieldUiTestTrait;
  use StringTranslationTrait;

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser = NULL;

  /**
   * The custom formatter.
   *
   * @var \Drupal\custom_formatters\FormatterInterface|string
   */
  protected $formatter = '';

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'block',
    'custom_formatters_test',
    'field_ui',
    'image',
    'node',
    'text',
  ];

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Drupal 11.4+ caches formatter options in FormatterPluginManager::
    // $formatterOptions per service instance and does not invalidate it when
    // clearCachedDefinitions() is called. Reset it here so that formatters
    // installed by test modules (e.g. custom_formatters_test) are visible.
    $this->resetFormatterPluginCache();

    // Create an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'administer custom formatters',
      'administer node display',
    ]);

    // Ensure relevant configuration present if profile isn't 'standard'.
    if ($this->profile !== 'standard') {
      // Blocks.
      $this->drupalPlaceBlock('local_actions_block');
      $this->drupalPlaceBlock('local_tasks_block');

      // Content types.
      $this->createContentType([
        'type' => 'article',
      ]);
    }

    // Create a test node.
    $this->node = $this->drupalCreateNode(['type' => 'article']);

    // Login as admin user.
    if ($this->adminUser !== FALSE) {
      $this->drupalLogin($this->adminUser);
    }
  }

  /**
   * Pass if the Custom Formatter is found.
   *
   * @param string $name
   *   The name of the formatter to check.
   * @param string $message
   *   Message to display.
   * @param string $group
   *   The group this message belong to, default to 'Other'.
   *
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  public function assertCustomFormatterExists($name, string $message = '', string $group = 'Other'): bool {
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->load($name);
    $message = !empty($message) ? $message : (string) $this->t('Custom Formatter %name found.', ['%name' => $name]);

    $this->assertTrue(!is_null($formatter), $message);

    return TRUE;
  }

  /**
   * Create a Custom Formatter.
   *
   * @param array $values
   *   The values to set for the Custom Formatter.
   *
   * @return \Drupal\custom_formatters\FormatterInterface
   *   The Custom Formatter object.
   */
  protected function createCustomFormatter(array $values = []): FormatterInterface {
    // Prepare the default values.
    $name = $this->randomMachineName();
    $defaults = [
      'label'       => $name,
      'id'          => mb_strtolower($name),
      // Include both types: Drupal 11.0 creates body as text_with_summary,
      // while Drupal 11.1+ creates it as text_long.
      'field_types' => ['text_with_summary', 'text_long'],
    ];
    $values += $defaults;

    // Create the Custom Formatter.
    $formatter = \Drupal::entityTypeManager()
      ->getStorage('formatter')
      ->create($values);
    $formatter->save();
    $this->resetFormatterPluginCache();

    \assert($formatter instanceof FormatterInterface);

    return $formatter;
  }

  /**
   * Set a Custom Formatter to be used by a specified field/bundle/view mode.
   *
   * @param string $formatter_name
   *   A Custom Formatter name.
   * @param string $field_name
   *   A Field name.
   * @param string $bundle_name
   *   A Node content type.
   * @param string $view_mode
   *   A Node view mode.
   */
  protected function setCustomFormatter(string $formatter_name, string $field_name, string $bundle_name, string $view_mode = 'default'): void {
    $this->drupalGet("admin/structure/types/manage/{$bundle_name}/display/{$view_mode}");
    $this->submitForm(["fields[{$field_name}][type]" => "custom_formatters:{$formatter_name}"], (string) $this->t('Save'));
    $this->assertSession()->pageTextContains((string) $this->t('Your settings have been saved.'));
  }

  /**
   * Clears the formatter plugin manager's persistent and instance-level caches.
   *
   * Drupal 11.4+ caches formatter options in the FormatterPluginManager service
   * instance ($formatterOptions) in addition to the standard plugin definitions
   * cache. clearCachedDefinitions() only clears the latter, so this method
   * resets both to ensure newly-created or newly-installed formatters are
   * visible on subsequent page requests.
   */
  protected function resetFormatterPluginCache(): void {
    $manager = \Drupal::service('plugin.manager.field.formatter');
    $manager->clearCachedDefinitions();
    $ref = new \ReflectionProperty($manager, 'formatterOptions');
    $ref->setValue($manager, NULL);
  }

}
