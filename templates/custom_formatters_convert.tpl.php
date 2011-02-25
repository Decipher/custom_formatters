<?php
/**
 * @file
 * Theme for Custom Formatters Convert.
 *
 * Available variables:
 * - $code: A string containing the formatter code for conversion.
 */
?>
$code = "<?php print addslashes($code); ?>";

// Parse tokens.
return _custom_formatters_token_replace((object) array('code' => $code, 'field_types' => $element['#formatter']->field_types), $element);<?
