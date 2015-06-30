<?php

/**
 * @file
 * Entity tokens module integration.
 */

/**
 * Implements hook_custom_formatters_token_data_alter() on behalf of
 * entity_token.module.
 */
function entity_token_custom_formatters_token_data_alter(&$token_data, &$text, $field, $delta) {
  module_load_include('inc', 'entity_token', 'entity_token.tokens');
  $token_types = entity_token_types_chained();
  $field_name = str_replace('_', '-', $field['field_name']);
  $info       = module_exists('token') ? token_get_info() : token_info();
  foreach (element_children($info['tokens']) as $type) {
    if (isset($token_data[$type]) && isset($info['tokens'][$type][$field_name])) {
      $wrapper = !isset($wrapper) ? _entity_token_wrap_data($type, $token_types[$type], $token_data[$type], array()) : $wrapper;
      if (isset($wrapper->{$field['field_name']})) {
        if ($wrapper->{$field['field_name']} instanceof EntityListWrapper && isset($wrapper->{$field['field_name']}[$delta])) {
          $token_data['struct'] = $wrapper->{$field['field_name']}[$delta];
        }
        else {
          $token_data['struct'] = $wrapper->{$field['field_name']};
        }
        return;
      }
    }
  }
}
