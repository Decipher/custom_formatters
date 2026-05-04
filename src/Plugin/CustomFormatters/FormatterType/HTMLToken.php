<?php

declare(strict_types=1);

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\FormatterTypeBase;

/**
 * Plugin implementation of the HTML + Token Formatter type.
 *
 * @FormatterType(
 *   id = "html_token",
 *   label = "HTML + Token",
 *   description = "A HTML based editor with Token support.",
 * )
 */
class HTMLToken extends FormatterTypeBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $token_types = [];
      foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type) {
        if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
          $token_types[] = $entity_type->id();
        }
      }

      $form['tokens'] = [
        '#theme'           => 'token_tree_link',
        '#token_types'     => $token_types,
        '#global_types'    => TRUE,
        '#click_insert'    => TRUE,
        '#recursion_limit' => 3,
      ];
    }
    else {
      $form['tokens'] = [
        '#type'   => 'markup',
        '#markup' => $this->t('Install the <a href=":url">Token</a> module to enable a token browser for this field.', [
          ':url' => 'https://www.drupal.org/project/token',
        ]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    $text = $this->entity->get('data');
    $token_data = [
      $items->getEntity()->getEntityTypeId() => $items->getEntity(),
    ];

    foreach ($items as $delta => $item) {
      $delta_token_data = $token_data;

      if ($item instanceof EntityReferenceItem && $item->entity) {
        $delta_token_data[$item->entity->getEntityTypeId()] = $item->entity;
      }

      $context = [
        'text'  => $text,
        'item'  => $item,
        'delta' => $delta,
      ];
      \Drupal::moduleHandler()
        ->alter('custom_formatters_token_data', $delta_token_data, $context);

      $element[$delta] = [
        '#markup' => \Drupal::token()
          ->replace($text, $delta_token_data, ['clear' => TRUE, 'langcode' => $langcode]),
      ];
    }

    return $element;
  }

}
