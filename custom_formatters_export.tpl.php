<?php
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
      'variables' => array(
        '#formatter' => NULL,
        '#item<?php print $formatter->multiple ? 's' : '' ?>' => NULL,
      ),
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
    ),
  );
}

/**
 * Implements hook_field_formatter_view().
 */
function <?php print $name ?>_field_formatter_view($obj_type, $object, $field, $instance, $langcode, $items, $display) {
<?php if (!$formatter->multiple) : ?>
  foreach ($items as $delta => $item) {
    $element[$delta] = array(
      '#theme' => '<?php print $name ?>_formatter_' . $display['type'],
      '##formatter' => $display['type'],
      '##item' => $item,
    );
  }
<?php else: ?>
  $element[0] = array(
    '#theme' => '<?php print $name ?>_formatter_' . $display['type'],
    '##formatter' => $display['type'],
    '##items' => $items,
  );
<?php endif; ?>

  return $element;
}

function theme_<?php print $name ?>_formatter_<?php print $formatter->name ?>($variables) {
<?php foreach (split("\n", $formatter->code) as $line) { ?>
  <?php print $line . "\n" ?><?php } ?>
}
