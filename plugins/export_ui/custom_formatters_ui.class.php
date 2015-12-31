<?php

/**
 * @file
 * The Custom Formatters Export UI plugin.
 */

/**
 * Class custom_formatters_ui.
 */
class custom_formatters_ui extends ctools_export_ui {
  /**
   * {@inheritdoc}
   */
  function list_build_row($item, &$form_state, $operations) {
    // Set up sorting.
    $name = $item->{$this->plugin['export']['key']};
    $schema = ctools_export_get_schema($this->plugin['schema']);

    // Load Custom Formatters engines.
    $engines = module_invoke_all('custom_formatters_engine_info');

    // Hide Formatters using missing engines.
    if (!isset($engines[$item->mode])) {
      unset($this->row);

      return;
    }

    // Note: $item->{$schema['export']['export type string']} should have
    // already been set up by export.inc so we can use it safely.
    switch ($form_state['values']['order']) {
      case 'disabled':
        $this->sorts[$name] = empty($item->disabled) . $name;
        break;

      case 'title':
        $this->sorts[$name] = $item->{$this->plugin['export']['admin_title']};
        break;

      case 'name':
        $this->sorts[$name] = $name;
        break;

      case 'storage':
        $this->sorts[$name] = $item->{$schema['export']['export type string']} . $name;
        break;
    }

    $this->rows[$name]['data'] = array();
    $this->rows[$name]['class'] = !empty($item->disabled) ? array('ctools-export-ui-disabled') : array('ctools-export-ui-enabled');

    // If we have an admin title, make it the first row.
    if (!empty($this->plugin['export']['admin_title'])) {
      $this->rows[$name]['data'][] = array(
        'data'  => check_plain($item->{$this->plugin['export']['admin_title']}),
        'class' => array('ctools-export-ui-title'),
      );
    }
    $this->rows[$name]['data'][] = array(
      'data'  => check_plain($name),
      'class' => array('ctools-export-ui-name'),
    );
    $this->rows[$name]['data'][] = array(
      'data'  => check_plain($engines[$item->mode]['label']),
      'class' => array('ctools-export-ui-format'),
    );
    $this->rows[$name]['data'][] = array(
      'data'  => !empty($item->fapi) && drupal_strlen($item->fapi) > 17 ? t('Yes') : t('No'),
      'class' => array('ctools-export-ui-fapi'),
    );
    $this->rows[$name]['data'][] = array(
      'data'  => check_plain($item->{$schema['export']['export type string']}),
      'class' => array('ctools-export-ui-storage'),
    );

    $ops = theme('links__ctools_dropbutton', array(
      'links'      => $operations,
      'attributes' => array(
        'class' => array(
          'links',
          'inline',
        ),
      ),
    ));

    $this->rows[$name]['data'][] = array(
      'data'  => $ops,
      'class' => array('ctools-export-ui-operations'),
    );

    // Add an automatic mouseover of the description if one exists.
    if (!empty($this->plugin['export']['admin_description'])) {
      $this->rows[$name]['title'] = $item->{$this->plugin['export']['admin_description']};
    }
  }

  /**
   * {@inheritdoc}
   */
  function list_table_header() {
    $header = array();
    if (!empty($this->plugin['export']['admin_title'])) {
      $header[] = array(
        'data'  => t('Title'),
        'class' => array('ctools-export-ui-title'),
      );
    }

    $header[] = array(
      'data'  => t('Name'),
      'class' => array('ctools-export-ui-name'),
    );
    $header[] = array(
      'data'  => t('Format'),
      'class' => array('ctools-export-ui-format'),
    );
    $header[] = array(
      'data'  => t('Formatter settings'),
      'class' => array('ctools-export-ui-fapi'),
    );
    $header[] = array(
      'data'  => t('Storage'),
      'class' => array('ctools-export-ui-storage'),
    );
    $header[] = array(
      'data'  => t('Operations'),
      'class' => array('ctools-export-ui-operations'),
    );

    return $header;
  }

  /**
   * {@inheritdoc}
   */
  function enable_page($js, $input, $item) {
    field_cache_clear();

    return $this->set_item_state(FALSE, $js, $input, $item);
  }

  /**
   * {@inheritdoc}
   */
  function disable_page($js, $input, $item) {
    field_cache_clear();

    return $this->set_item_state(TRUE, $js, $input, $item);
  }

  /**
   * {@inheritdoc}
   */
  function export_page($js, $input, $item) {
    drupal_set_title($this->get_page_title('export', $item));

    return drupal_get_form('custom_formatters_export_ui_export_form', $item, t('Export'));
  }

}
