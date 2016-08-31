<?php

/**
 * @file
 * Contextual links module optional integration.
 */

/**
 * Implements hook_custom_formatters_field_formatter_view_elements_alter().
 */
function contextual_custom_formatters_field_formatter_view_elements_alter(&$element, $formatter) {
  //  if (_custom_formatters_contextual_access($formatter->name, $element)) {
  $element[0] = ['markup' => $element[0]];
  $element[0]['contextual_links'] = [
    '#type' => 'contextual_links_placeholder',
    '#id'   => _contextual_links_to_id([
      'custom_formatters' => [
        'route_parameters' => ['formatter' => $formatter->id()],
      ],
    ]),
  ];
  $element['#attributes']['class'][] = 'contextual-region';
  // }
}
