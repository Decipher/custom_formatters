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
<?php foreach($formatters as $formatter) : ?>
    '<?php print $name ?>_formatter_<?php print $formatter->name ?>' => array(
      'arguments' => array('element' => NULL),
    ),
<?php endforeach; ?>
  );
}

/**
 * Implements hook_field_formatter_info().
 */
function <?php print $name ?>_field_formatter_info() {
  return array(
<?php foreach($formatters as $formatter) : ?>
    '<?php print $formatter->name ?>' => array(
      'label' => '<?php print addslashes($formatter->label) ?>',
      'description' => t('<?php print addslashes($formatter->description) ?>'),
      'field types' => array('<?php print implode("', '", unserialize($formatter->field_types)) ?>'),
      'multiple values' => <?php print $formatter->multiple ? 'CONTENT_HANDLE_MODULE' : 'CONTENT_HANDLE_CORE' ?>,
    ),
<?php endforeach; ?>
  );
}
<?php foreach($formatters as $formatter) : ?>

function theme_<?php print $name ?>_formatter_<?php print $formatter->name ?>($element) {
<?php foreach (split("\n", $formatter->code) as $line) { ?>
  <?php print $line . "\n" ?><?php } ?>
}
<?php endforeach; ?>