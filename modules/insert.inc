<?php

/**
 * @file
 * Insert module integration for Custom Formatters.
 *
 * Loaded automatically by hook_module_implements_alter() when the
 * insert module is installed.
 */

declare(strict_types=1);

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FormatterInterface;
use Drupal\custom_formatters\FormatterInterface as CustomFormattersFormatterInterface;
use Drupal\custom_formatters\InsertFieldItemList;
use Drupal\file\FileInterface;

/**
 * Implements hook_insert_styles().
 */
function custom_formatters_insert_styles(string $insert_type): array {
  if (!\Drupal::moduleHandler()->moduleExists('insert')) {
    return [];
  }

  if (!in_array($insert_type, ['image', 'file'], TRUE)) {
    return [];
  }

  $compatible_field_types = $insert_type === 'image' ? ['image'] : ['file', 'entity_reference'];

  $formatters = \Drupal::entityTypeManager()
    ->getStorage('formatter')
    ->loadByProperties(['status' => TRUE]);

  $styles = [];
  foreach ($formatters as $formatter) {
    $formatter_field_types = $formatter->get('field_types') ?? [];
    if (empty(array_intersect($compatible_field_types, $formatter_field_types))) {
      continue;
    }

    $style_name = 'custom_formatters__' . $formatter->id();
    $styles[$style_name] = [
      'label' => $formatter->label(),
      'weight' => 0,
    ];
  }

  return $styles;
}

/**
 * Implements hook_insert_render().
 */
function custom_formatters_insert_render(string $style_name, array $vars, array $insert_element): string {
  if (!\Drupal::moduleHandler()->moduleExists('insert')) {
    return '';
  }

  if (!str_starts_with($style_name, 'custom_formatters__')) {
    return '';
  }

  $formatter_id = substr($style_name, strlen('custom_formatters__'));

  $formatter = \Drupal::entityTypeManager()
    ->getStorage('formatter')
    ->load($formatter_id);

  if (!$formatter instanceof CustomFormattersFormatterInterface || !$formatter->status()) {
    return '';
  }

  $file = $vars['file'] ?? NULL;
  if (!$file instanceof FileInterface) {
    return '';
  }

  // Determine the field type based on what the formatter supports.
  $formatter_field_types = $formatter->get('field_types') ?? [];
  $field_type = NULL;
  foreach (['image', 'file', 'entity_reference'] as $type) {
    if (in_array($type, $formatter_field_types, TRUE)) {
      $field_type = $type;
      break;
    }
  }

  if ($field_type === NULL) {
    return '';
  }

  // Create a temporary field definition matching the formatter's field type.
  $field_definition = BaseFieldDefinition::create($field_type)
    ->setName('_insert_file')
    ->setTargetEntityTypeId('file');

  // Suppress alt/title for image fields in this context.
  if ($field_type === 'image') {
    $field_definition->setSetting('alt_field', FALSE);
    $field_definition->setSetting('title_field', FALSE);
  }

  // Create a field item list with the file entity reference.
  $items = new InsertFieldItemList(
    $field_definition,
    '_insert_file',
    $file,
    \Drupal::languageManager()->getCurrentLanguage()->getId(),
  );

  // Build the field item value based on the resolved field type.
  $value = ['target_id' => (int) $file->id()];
  if ($field_type === 'image') {
    $value['alt'] = '';
    $value['title'] = $file->getFilename();
  }
  elseif ($field_type === 'file') {
    $value['display'] = 1;
    $value['description'] = '';
  }
  $items->appendItem($value);

  // Instantiate the field formatter plugin and render.
  $ob_level = ob_get_level();
  try {
    $plugin_manager = \Drupal::service('plugin.manager.field.formatter');
    $formatter_instance = $plugin_manager->createInstance(
      'custom_formatters:' . $formatter_id,
      [
        'field_definition' => $field_definition,
        'settings' => [],
        'label' => 'hidden',
        'view_mode' => '_custom',
        'third_party_settings' => [],
      ],
    );

    \assert($formatter_instance instanceof FormatterInterface);
    $elements = $formatter_instance->viewElements($items, $items->getLangcode());
    if (empty($elements)) {
      return '';
    }

    return (string) \Drupal::service('renderer')->renderInIsolation($elements);
  }
  catch (\Throwable $e) {
    // Close any output buffers the formatter engine opened before throwing.
    while (ob_get_level() > $ob_level) {
      ob_end_clean();
    }
    \Drupal::logger('custom_formatters')->error(
      'Insert render failed for formatter %id: @message',
      ['%id' => $formatter_id, '@message' => $e->getMessage()],
    );
    return '';
  }
}
