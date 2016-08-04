<?php

namespace Drupal\custom_formatters\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\custom_formatters\FormatterTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the shortcut set entity edit forms.
 */
class FormatterForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\custom_formatters\FormatterInterface
   */
  protected $entity;

  /**
   * Field type plugin manager.
   *
   * @var FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * Formatter type plugin manager.
   *
   * @var FormatterTypeManager
   */
  protected $formatterTypeManager;

  /**
   * Constructs a FormatterForm object.
   */
  public function __construct(FieldTypePluginManagerInterface $field_type_manager, FormatterTypeManager $formatter_type_manager) {
    $this->fieldTypeManager = $field_type_manager;
    $this->formatterTypeManager = $formatter_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.custom_formatters.formatter_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $formatter_type = $this->entity->getFormatterType();

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Formatter name'),
      '#description'   => $this->t('This will appear in the administrative interface to easily identify it.'),
      '#required'      => TRUE,
      '#default_value' => $this->entity->label(),
    ];

    $form['id'] = [
      '#type'          => 'machine_name',
      '#machine_name'  => [
        'exists'          => '\Drupal\custom_formatters\Entity\Formatter::load',
        'source'          => ['label'],
        'replace_pattern' => '[^a-z0-9_]+',
        'replace'         => '_',
      ],
      '#default_value' => $this->entity->id(),
      '#disabled'      => !$this->entity->isNew(),
      '#maxlength'     => 255,
    ];

    $form['type'] = [
      '#type'  => 'value',
      '#value' => $this->entity->get('type'),
    ];

    $form['status'] = [
      '#type'  => 'value',
      '#value' => TRUE,
    ];

    $form['description'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['field_types'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Field type(s)'),
      '#options'       => $this->getFieldTypes(),
      '#default_value' => $this->entity->get('field_types'),
      '#required'      => TRUE,
      '#multiple'      => $formatter_type->getPluginDefinition()['multipleFields'],
      '#ajax'          => [
        'callback' => '::formAjax',
        'wrapper'  => 'plugin-wrapper',
      ],
      '#disabled'      => $this->entity->isActive(),
    ];

    // Get Formatter type settings form.
    $plugin_form = [];
    $form['plugin'] = $formatter_type->settingsForm($plugin_form, $form_state);
    $form['plugin']['#type'] = 'container';
    $form['plugin']['#prefix'] = "<div id='plugin-wrapper'>";
    $form['plugin']['#suffix'] = "</div>";

    return $form;
  }

  /**
   * Ajax callback for form.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state object.
   *
   * @return mixed
   *   The ajax form element.
   */
  public function formAjax(array $form, FormStateInterface $form_state) {
    return $form['plugin'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->getFormatterType()->submitForm($form, $form_state);

    $entity = $this->entity;
    $is_new = !$entity->getOriginalId();
    $entity->save();

    // Clear cached formatters.
    // @TODO - Tag custom formatters?
    $this->formatterTypeManager->clearCachedDefinitions();

    if ($is_new) {
      drupal_set_message($this->t('Added formatter %formatter.', ['%formatter' => $entity->label()]));
    }
    else {
      drupal_set_message($this->t('Updated formatter %formatter.', ['%formatter' => $entity->label()]));
    }
    $form_state->setRedirectUrl(new Url('entity.formatter.collection'));
  }

  /**
   * Returns an array of available field types.
   *
   * @TODO - Allow formatter type plugin to modify this list.
   *
   * @return mixed
   *   Array of field types grouped by their providers.
   */
  protected function getFieldTypes() {
    $options = [];

    $field_types = $this->fieldTypeManager->getDefinitions();
    $this->moduleHandler->alter('custom_formatters_fields', $field_types);

    ksort($field_types);
    foreach ($field_types as $field_type) {
      $options[$field_type['provider']][$field_type['id']] = $field_type['label']->render();
    }
    ksort($options);

    return $options;
  }

}
