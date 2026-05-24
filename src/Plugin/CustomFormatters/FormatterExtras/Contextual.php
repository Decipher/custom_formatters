<?php

declare(strict_types=1);

/**
 * @file
 * Contextual links integration plugin for custom formatters.
 */

namespace Drupal\custom_formatters\Plugin\CustomFormatters\FormatterExtras;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\custom_formatters\FormatterExtrasBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

define('CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_DISABLED', 0);
define('CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_ENABLED', 1);

/**
 * Contextual links optional integration plugin.
 *
 * @FormatterExtras(
 *   id = "contextual",
 *   label = "Contextual links",
 *   description = "Behaviour for Contextual links integration.",
 *   dependencies = {
 *     "module" = {
 *       "contextual"
 *     }
 *   }
 * )
 */
class Contextual extends FormatterExtrasBase implements ContainerFactoryPluginInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = [];

    $form['mode'] = [
      '#type'          => 'radios',
      '#title'         => $this->t('Mode'),
      '#options'       => [
        CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_DISABLED => $this->t('Disabled'),
        CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_ENABLED  => $this->t('Enabled'),
      ],
      '#default_value' => $this->entity->getThirdPartySetting('contextual', 'mode', CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_ENABLED),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSave(array $form, FormStateInterface $form_state) {
    $this->entity->setThirdPartySetting('contextual', 'mode', $form_state->getValues()['extras']['contextual']['mode']);
  }

  /**
   * {@inheritdoc}
   */
  public function formatterViewElementsAlter(array &$element) {
    $request_format = $this->requestStack->getCurrentRequest()?->getRequestFormat() ?? 'html';
    if ($request_format === 'html' && $this->entity->getThirdPartySetting('contextual', 'mode', CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_ENABLED) == CUSTOM_FORMATTERS_EXTRAS_CONTEXTUAL_ENABLED) {
      // Wrap the first element in a container so contextual links can be added
      // as a sibling without overwriting the formatter's render output.
      $element[0] = ['markup' => $element[0]];
      $element[0]['contextual_links'] = [
        '#type' => 'contextual_links_placeholder',
        '#id'   => _contextual_links_to_id([
          'custom_formatters' => [
            'route_parameters' => ['formatter' => $this->entity->id()],
          ],
        ]),
      ];
      $element['#attributes']['class'][] = 'contextual-region';
    }
  }

}
