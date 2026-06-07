<?php

/**
 * @file
 * Hooks provided by the Custom Formatters module.
 */

declare(strict_types=1);

/**
 * @defgroup custom_formatters_settings Formatter Settings
 * @{
 * Per-instance settings for Custom Formatters, managed via Field UI.
 *
 * Formatter config entities act as the bundle entity type for FormatterSetting
 * content entities. Site builders add fields to a formatter via its "Manage
 * fields" and "Manage form display" tabs. When the formatter is selected on a
 * "Manage display" screen, an inline entity form renders those fields as
 * per-instance settings. The saved values are loaded at render time and passed
 * to engine plugins as a $settings array.
 *
 * @section settings_array The $settings array
 *
 * Each engine plugin receives a $settings array keyed by field machine name.
 * Two forms of each value are available:
 *
 * - Rendered values (default): processed through the field's view display
 *   formatter. Boolean fields become "Yes"/"No", entity references become
 *   linked labels, etc.
 * - Raw values (via _raw): the unformatted getString() output, suitable for
 *   use as HTML attributes, CSS class names, or numeric comparisons.
 *
 * Array structure:
 * @code
 * $settings = [
 *   // Rendered values, keyed by field machine name.
 *   'field_css_class' => '<span>featured</span>',
 *   'field_show_label' => 'Yes',
 *   // Raw values sub-array.
 *   '_raw' => [
 *     'field_css_class' => 'featured',
 *     'field_show_label' => '1',
 *   ],
 * ];
 * @endcode
 *
 * @section engine_access Accessing settings in each engine
 *
 * PHP engine:
 * @code
 * // Rendered value:
 * echo $settings['field_css_class'];
 * // Raw value:
 * echo $raw_settings['field_css_class'];
 * @endcode
 *
 * Twig engine:
 * @code
 * {# Rendered value: #}
 * {{ settings.field_css_class }}
 * {# Raw value: #}
 * {{ raw_settings.field_css_class }}
 * @endcode
 *
 * HTML+Token engine:
 * @code
 * <!-- Rendered value: -->
 * [formatter_setting:field_css_class]
 * <!-- Raw value: -->
 * [formatter_setting:field_css_class:raw]
 * @endcode
 * @}
 */
