<?php

declare(strict_types=1);

/**
 * @file
 * PHP engine plugin for evaluating custom PHP code as a formatter.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\FormatterTypeBase;

/**
 * Plugin implementation of the PHP Formatter type.
 *
 * WARNING: This engine uses eval() to execute user-provided PHP code. Access
 * is restricted to users with the "administer custom formatters" permission.
 * Exercise caution when granting this permission, as it allows arbitrary
 * PHP execution on the site.
 *
 * @FormatterType(
 *   id = "php",
 *   label = "PHP",
 *   description = "A PHP-based editor supporting multiple fields and values.",
 *   multipleFields = "true"
 * )
 */
class Php extends FormatterTypeBase {

  /**
   * {@inheritdoc}
   */
  protected function getCodeEditorMode(): ?string {
    // Pure PHP mode; the formatter evaluates bare code without <?php tags.
    return 'text/x-php';
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['data']['#description'] = $this->t('Enter the PHP code that will be evaluated. You should NOT include %php tags.<br /><br /><strong>Available parameters:</strong><dl><dt><em><a href=":field_item_list_interface" target="_blank">FieldItemListInterface</a></em> $items</dt><dd>The field values to be rendered.</dd><dt><em>string</em> $langcode</dt><dd>The language that should be used to render the field.</dd><dt><em>array</em> $settings</dt><dd>Formatter settings keyed by field machine name. Values are rendered strings from the configured view display. Access with <code>$settings[\'field_name\']</code>.</dd><dt><em>array</em> $raw_settings</dt><dd>Same fields as <code>$settings</code>, but as unformatted plain-text values. Access with <code>$raw_settings[\'field_name\']</code>.</dd></dt></dl>', [
      '%php'                      => '<?php ?>',
      ':field_item_list_interface' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FieldItemListInterface.php/interface/FieldItemListInterface',
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function previewSettingsForm(): array {
    $devel_exists = $this->moduleHandler->moduleExists('devel');
    $form = [];

    $form['debug_variables'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Output <strong>$items</strong> and <strong>$settings</strong> variables'),
      '#default_value' => FALSE,
      '#disabled'      => !$devel_exists,
      '#description'   => !$devel_exists ? $this->t('Requires Devel module.') : '',
    ];

    $form['debug_html'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Output raw HTML'),
      '#default_value' => FALSE,
      '#disabled'      => !$devel_exists,
      '#description'   => !$devel_exists ? $this->t('Requires Devel module.') : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode, array $settings = []) {
    // Extracted for eval scope; $raw_settings['field'] is shorter than
    // $settings['_raw']['field'].
    // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable,DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable -- used inside eval()
    $raw_settings = $settings['_raw'] ?? [];
    ob_start();
    $output = eval($this->entity->get('data')); // phpcs:ignore Drupal.Functions.DiscouragedFunctions.Discouraged
    $output = !empty($output) ? $output : ob_get_contents();
    ob_end_clean();

    return empty($output) ? [] : (is_array($output) ? $output : ['#markup' => $output]);
  }

  /**
   * {@inheritdoc}
   */
  public function previewDebugData(FieldItemListInterface $items, FieldableEntityInterface $entity): mixed {
    return [
      'items' => $items->getValue(),
      'langcode' => $items->getLangcode(),
      'settings' => [],
    ];
  }

}
