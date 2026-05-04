<?php

declare(strict_types=1);

namespace Drupal\Tests\custom_formatters\Functional;

use Drupal\custom_formatters\Entity\Formatter;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests general Custom Formatters functionality.
 *
 * @coversDefaultClass \Drupal\custom_formatters\FormatterInterface
 * @group custom_formatters
 */
class CustomFormattersGeneralTest extends CustomFormattersTestBase {
  use TestFileCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test General UI related functionality.
   */
  public function testCustomFormattersUi() {
    // Ensure the Formatters administration is linked in the structure section.
    $this->drupalGet('admin/structure');
    $this->assertSession()->linkByHrefExists('admin/structure/formatters');
    $this->assertSession()->pageTextContains('Administer Formatters.');

    $this->drupalGet('admin/structure/formatters');

    // Ensure the Formatters overview page is present.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Formatters');

    // Ensure the Settings link is present and correct.
    $this->assertSession()->linkExists($this->t('Settings'));
    $this->assertSession()->linkByHrefExists('admin/structure/formatters/settings');

    // Ensure our pre-prepared test formatter is present.
    $this->assertSession()->pageTextContains('Test Formatter');
    $this->assertSession()->linkByHrefExists('admin/structure/formatters/manage/test_formatter');
    $this->assertCustomFormatterExists('test_formatter');

    // Ensure our pre-prepared test formatter is present on the Manage display
    // page.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->responseContains('custom_formatters:test_formatter');
    $this->assertSession()->responseContains('Custom: Test Formatter');

    // Change the Label prefix.
    $this->drupalGet('admin/structure/formatters/settings');
    $edit = ['label_prefix_value' => $this->randomMachineName()];
    $this->submitForm($edit, $this->t('Save configuration'));
    $this->assertSession()->pageTextContains($this->t('Custom Formatters settings have been updated.'));

    // Ensure our pre-prepared test formatter is present on the Manage display
    // page with the altered label prefix.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->responseContains($this->t('@prefix: Test Formatter', ['@prefix' => $edit['label_prefix_value']]));

    // Remove the Label prefix.
    $this->drupalGet('admin/structure/formatters/settings');
    $edit = ['label_prefix' => FALSE];
    $this->submitForm($edit, $this->t('Save configuration'));
    $this->assertSession()->pageTextContains($this->t('Custom Formatters settings have been updated.'));

    // Ensure our pre-prepared test formatter is present on the Manage display
    // page without a label prefix.
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->assertSession()->responseContains('Test Formatter');
  }

  /**
   * Test the Formatter preset Engine.
   *
   * @todo Add manual creation test.
   */
  public function testFormatterTypeFormatterPreset() {
    // Create a Custom formatter.
    $this->formatter = $this->createCustomFormatter([
      'type' => 'formatter_preset',
      'data' => [
        'formatter' => 'text_trimmed',
        'settings'  => [
          'trim_length' => 10,
        ],
      ],
    ]);

    // Set the formatter active on the Body field.
    $this->setCustomFormatter($this->formatter->id(), 'body', 'article');

    // Ensure Formatter rendered correctly.
    $this->drupalGet($this->node->toUrl());
    $this->assertTrue(!strstr($this->getSession()->getPage()->getContent(), $this->node->get('body')[0]->value) && strstr($this->getSession()->getPage()->getContent(), substr($this->node->get('body')[0]->value, 0, 7)), (string) $this->t('Custom formatter output found.'));
  }

  /**
   * Test the PHP Engine.
   *
   * @todo Add manual creation test.
   */
  public function testCustomFormatterTypePhp() {
    // Create a Custom formatter.
    $text = $this->randomMachineName();
    $this->formatter = $this->createCustomFormatter([
      'type' => 'php',
      'data' => "return '{$text}';",
    ]);

    // Set the formatter active on the Body field.
    $this->setCustomFormatter($this->formatter->id(), 'body', 'article');

    // Ensure Formatter rendered correctly.
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->pageTextContains($text, $this->t('Custom formatter output found.'));
  }

  /**
   * Test the Twig engine.
   *
   * @todo Add manual creation test.
   */
  public function testCustomFormatterTypeTwig() {
    // Create a Custom formatter.
    $text = $this->randomMachineName();
    $this->formatter = $this->createCustomFormatter([
      'type' => 'twig',
      'data' => $text,
    ]);

    // Set the formatter active on the Body field.
    $this->setCustomFormatter($this->formatter->id(), 'body', 'article');

    // Ensure Formatter rendered correctly.
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->pageTextContains($text, $this->t('Custom formatter output found.'));
  }

  /**
   * Test the HTML + Token engine.
   *
   * @todo Add manual creation test.
   */
  public function testCustomFormatterTypeHtmlToken() {
    // Create a Custom formatter.
    $text = $this->randomMachineName();
    $this->formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => $text,
    ]);

    // Set the formatter active on the Body field.
    $this->setCustomFormatter($this->formatter->id(), 'body', 'article');

    // Ensure Formatter rendered correctly.
    $this->drupalGet($this->node->toUrl());
    $this->assertSession()->pageTextContains($text, $this->t('Custom formatter output found.'));
  }

  /**
   * Test that formatter entity properties are loaded from storage.
   *
   * Regression test for issue #3188668
   * - entity properties not loaded from storage.
   */
  public function testFormatterEntityLoadsPropertiesFromStorage() {
    $formatter = Formatter::load('test_formatter');

    $this->assertNotNull($formatter, 'Formatter loaded successfully.');
    $this->assertEquals('test_formatter', $formatter->id(), 'ID is loaded correctly.');
    $this->assertEquals('Test Formatter', $formatter->label(), 'Label is loaded correctly.');
    $this->assertEquals('php', $formatter->get('type'), 'Type is loaded correctly.');
    $this->assertNotEmpty($formatter->get('field_types'), 'Field types are loaded.');
  }

  /**
   * Test that formatters with undefined types display gracefully.
   *
   * Regression test for issue #3404747 - undefined array key warning on
   * PHP 8.2 / Drupal 10 when formatter type is not defined.
   */
  public function testFormatterListBuilderWithUndefinedType() {
    $this->drupalGet('admin/structure/formatters');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Formatters');
  }

  /**
   * Test that formatter edit page loads without errors.
   *
   * Regression test for issue #3188668 - edit form crashes due to empty entity.
   */
  public function testFormatterEditPageLoads() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'label' => 'Editable Formatter',
    ]);

    $this->drupalGet('admin/structure/formatters/manage/' . $formatter->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('label');
    $this->assertSession()->fieldExists('type');
  }

  /**
   * Test that formatter presets render entity reference-based fields.
   *
   * Regression test for empty prepareView() - image fields using formatter
   * presets would render nothing because referenced file entities were never
   * loaded.
   */
  public function testFormatterPresetWithImageField() {
    // Create an image field on the article content type.
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Image',
    ])->save();

    // Create a formatter preset that uses the core image formatter.
    $formatter = $this->createCustomFormatter([
      'type' => 'formatter_preset',
      'field_types' => ['image'],
      'data' => [
        'formatter' => 'image',
        'settings' => [
          'image_style' => 'thumbnail',
          'image_link' => '',
        ],
      ],
    ]);

    // Assign the formatter to the image field.
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('field_image', [
        'type' => 'custom_formatters:' . $formatter->id(),
        'label' => 'hidden',
      ])
      ->save();

    // Create a test image file.
    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri,
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    // Create a node with the image.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test image',
      ],
    ]);

    // Verify the image renders on the node page.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'img');
  }

  /**
   * Test that the PHP engine example formatter renders image fields.
   *
   * Regression test for broken D7 code in example_php_image config.
   */
  public function testPhpEngineExampleImageField() {
    FieldStorageConfig::create([
      'field_name' => 'field_test_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Test Image',
    ])->save();

    $formatter = $this->createCustomFormatter([
      'type' => 'php',
      'field_types' => ['image'],
      'data' => "// Display a Thumbnail image linked to a Large image.\r\n// Available variables:\r\n// - \$items: FieldItemListInterface - The field values to be rendered.\r\n// - \$langcode: string - The language that should be used to render the field.\r\n\$element = [];\r\nforeach (\$items as \$delta => \$item) {\r\n  if (\$item->entity) {\r\n    \$file_uri = \$item->entity->getFileUri();\r\n    \$element[\$delta] = [\r\n      '#type' => 'link',\r\n      '#url' => \\Drupal\\Core\\Url::fromUri(\r\n        \\Drupal\\image\\Entity\\ImageStyle::load('large')->buildUrl(\$file_uri)\r\n      ),\r\n      '#title' => [\r\n        '#theme' => 'image_style',\r\n        '#style_name' => 'thumbnail',\r\n        '#uri' => \$file_uri,\r\n      ],\r\n    ];\r\n  }\r\n}\r\nreturn \$element;",
    ]);

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('field_test_image', [
        'type' => 'custom_formatters:' . $formatter->id(),
        'label' => 'hidden',
      ])
      ->save();

    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri,
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_test_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test PHP image',
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'img');
    $this->assertSession()->elementExists('css', 'a img');
  }

  /**
   * Test HTMLToken settings form shows proper help, not @TODO text.
   */
  public function testHtmlTokenSettingsForm() {
    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'data' => '[node:title]',
    ]);

    $this->drupalGet('admin/structure/formatters/manage/' . $formatter->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('@TODO');

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $this->assertSession()->elementExists('css', '.token-tree');
    }
    else {
      $this->assertSession()->pageTextContains('Token');
      $this->assertSession()->linkExists('Token');
    }
  }

  /**
   * Test HTMLToken engine renders file tokens for image fields.
   *
   * Regression test for missing entity reference in token data -
   * [file:url] tokens would not be replaced because the referenced
   * file entity was not included in token data.
   */
  public function testHtmlTokenFileTokensWithImageField() {
    FieldStorageConfig::create([
      'field_name' => 'field_ht_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_ht_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'HT Image',
    ])->save();

    $formatter = $this->createCustomFormatter([
      'type' => 'html_token',
      'field_types' => ['image'],
      'data' => '<img src="[file:url]" />',
    ]);

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('field_ht_image', [
        'type' => 'custom_formatters:' . $formatter->id(),
        'label' => 'hidden',
      ])
      ->save();

    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri,
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_ht_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test token image',
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'img');
    $this->assertStringContainsString('.png', $this->getSession()->getPage()->getContent());
  }

  /**
   * Test Twig engine renders image fields.
   */
  public function testTwigEngineImageField() {
    FieldStorageConfig::create([
      'field_name' => 'field_twig_image',
      'type' => 'image',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_twig_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Twig Image',
    ])->save();

    $formatter = $this->createCustomFormatter([
      'type' => 'twig',
      'field_types' => ['image'],
      'data' => "{% for item in items %}\r\n<img src=\"{{ file_url(item.entity.uri.value) }}\" alt=\"{{ item.alt|default('') }}\" />\r\n{% endfor %}",
    ]);

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'default')
      ->setComponent('field_twig_image', [
        'type' => 'custom_formatters:' . $formatter->id(),
        'label' => 'hidden',
      ])
      ->save();

    $images = $this->getTestFiles('image');
    $image = reset($images);
    $file = File::create([
      'uri' => $image->uri,
      'uid' => 1,
      'status' => 1,
    ]);
    $file->save();

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_twig_image' => [
        'target_id' => $file->id(),
        'alt' => 'Test twig image',
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'img');
    $this->assertStringContainsString('.png', $this->getSession()->getPage()->getContent());
    $this->assertSession()->elementAttributeContains('css', 'img', 'alt', 'Test twig image');
  }

}
