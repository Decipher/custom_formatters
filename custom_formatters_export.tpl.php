<?php
// $Id$
/**
 * @file
 * Theme for Custom Formatters Export.
 */
?>
/**
 * Implements hook_theme().
 */
function <?php print $name ?>_theme() {
  return array(
    '<?php print $name ?>_formatter_<?php print $formatter->name ?>' => array(
      'arguments' => array('element' => NULL),
    ),
  );
}

/**
 * Implements hook_field_formatter_info().
 */
function <?php print $name ?>_field_formatter_info() {
  return array(
    '<?php print $formatter->name ?>' => array(
      'label' => '<?php print addslashes($formatter->label) ?>',
      'description' => t('<?php print addslashes($formatter->description) ?>'),
      'field types' => array('<?php print implode("', '", unserialize($formatter->field_types)) ?>'),
      'multiple values' => <?php print $formatter->multiple ? 'CONTENT_HANDLE_MODULE' : 'CONTENT_HANDLE_CORE' ?>,
    ),
  );
}

function theme_<?php print $name ?>_formatter_<?php print $formatter->name ?>($element) {
<?php foreach (split("\n", $formatter->code) as $line) { ?>
  <?php print $line . "\n" ?><?php } ?>
}
