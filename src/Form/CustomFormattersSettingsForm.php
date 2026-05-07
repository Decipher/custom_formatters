<?php

declare(strict_types=1);

/**
 * @file
 * Settings form for the Custom Formatters module.
 */

namespace Drupal\custom_formatters\Form;

use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Custom Formatters settings.
 */
class CustomFormattersSettingsForm extends ConfigFormBase {

  /**
   * The field formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $fieldFormatterManager;

  /**
   * Constructs a CustomFormattersSettingsForm object.
   *
   * @param \Drupal\Core\Field\FormatterPluginManager $field_formatter_manager
   *   The field formatter plugin manager.
   */
  public function __construct(FormatterPluginManager $field_formatter_manager) {
    $this->fieldFormatterManager = $field_formatter_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_formatters_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['custom_formatters.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_formatters.settings');

    $form['label_prefix'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Use Label prefix?'),
      '#description'   => $this->t('If checked, all Custom Formatters labels will be prefixed with a set value.'),
      '#default_value' => $config->get('label_prefix'),
    ];

    $form['label_prefix_value'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Label prefix'),
      '#default_value' => $config->get('label_prefix_value'),
      '#states'        => [
        'invisible' => [
          'input[name="label_prefix"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('label_prefix') && empty($form_state->getValue('label_prefix_value'))) {
      $form_state->setErrorByName('label_prefix_value', $this->t('A label prefix must be defined if you wish to use the prefix.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Custom Formatters settings have been updated.'));

    $config = $this->config('custom_formatters.settings');
    $config
      ->set('label_prefix', $form_state->getValue('label_prefix'))
      ->set('label_prefix_value', $form_state->getValue('label_prefix_value'))
      ->save();

    // Clear cached formatters.
    // @todo Tag custom formatters?
    $this->fieldFormatterManager->clearCachedDefinitions();
  }

}
