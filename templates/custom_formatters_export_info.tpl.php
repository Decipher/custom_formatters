<?php
// $Id$
/**
 * @file
 * Theme for Custom Formatters Export Info.
 *
 * Available variables:
 * - $name: A string containing the exported module name.
 * - $formatters: An array of formatters to export.
 * - $basic: A boolean value indicating presence of 'basic' formatters.
 *
 * Each $formatter in $formatters contains:
 * - $formatter->cfid: The numberic id of the formatter.
 * - $formatter->name: The alphanumeric id of the formatter.
 * - $formatter->label: The human-readable title of the formatter.
 * - $formatter->field_types: A serialized array of supported field types.
 * - $formatter->multiple: A boolean value determining whether the formatter
 *   supports multiple values.
 * - $formatter->description: The description of the formatter.
 * - $formatter->mode: The mode of the formatter (basic/advanced).
 * - $formatter->code: The formatter data.
 */
?>
; $Id$
name = <?php print $name . "\n"; ?>
description = Contains exported formatters for the '<?php print $name; ?>' module.
core = 6.x
<?php if (!$basic) : ?>
dependencies[] = content
<?php else : ?>
dependencies[] = custom_formatters
<?php endif;
