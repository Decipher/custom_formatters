<?php

declare(strict_types=1);

/**
 * @file
 * Twig engine plugin for rendering custom Twig templates as formatters.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterType;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\custom_formatters\FormatterTypeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Plugin implementation of the Twig type.
 *
 * @FormatterType(
 *   id = "twig",
 *   label = "Twig",
 *   description = "A Twig based editor.",
 * )
 */
class Twig extends FormatterTypeBase {

  /**
   * The Twig environment service.
   */
  protected Environment $twigService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Environment $twig_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->twigService = $twig_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('twig'));
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['data']['#description'] = $this->t('Enter the Twig code that will be evaluated.<br /><br /><strong>Available parameters:</strong><dl><dt><em><a href=":field_item_list_inerface" target="_blank">FieldItemListInterface</a></em> {{ items }}</dt><dd>The field values to be rendered.</dd><dt><em>string</em> {{ langcode }}</dt><dd>The language that should be used to render the field.</dd><dt><em><a href=":entity_interface" target="_blank">EntityInterface</a></em> {{ entity }}</dt><dd>The parent entity the field is attached to.</dd></dl>', [
      ':field_item_list_inerface' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FieldItemListInterface.php/interface/FieldItemListInterface',
      ':entity_interface' => 'https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityInterface.php/interface/EntityInterface',
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $output = '';

    try {
      $output = $this->twigService->createTemplate((string) $this->entity->get('data'))->render([
        'items'    => $items,
        'langcode' => $langcode,
        'entity'   => $items->getEntity(),
      ]);
    }
    catch (Error $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return empty($output) ? [] : ['#markup' => $output];
  }

}
