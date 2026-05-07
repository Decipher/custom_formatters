<?php

declare(strict_types=1);

/**
 * @file
 * Deriver for field formatter plugins from custom formatter config entities.
 */

namespace Drupal\custom_formatters\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\custom_formatters\FormatterDependencyBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves field formatter plugin definitions for all custom formatters.
 */
class CustomFormatters extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Formatter settings.
   *
   * @var array
   */
  protected $settings = [];

  /**
   * The formatter dependency builder service.
   */
  protected FormatterDependencyBuilder $dependencyBuilder;

  /**
   * CustomFormatters constructor.
   *
   * @param \Drupal\custom_formatters\FormatterDependencyBuilder $dependency_builder
   *   The formatter dependency builder service.
   */
  public function __construct(FormatterDependencyBuilder $dependency_builder) {
    $this->dependencyBuilder = $dependency_builder;
    $this->settings = $this->dependencyBuilder->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('custom_formatters.dependency_builder'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $formatters = $this->dependencyBuilder->getEntityStorage()->loadMultiple();
    /** @var \Drupal\custom_formatters\FormatterInterface $formatter */
    foreach ($formatters as $formatter) {
      if ($formatter->get('status')) {
        $this->derivatives[$formatter->id()] = $base_plugin_definition;
        $this->derivatives[$formatter->id()]['label'] = $this->getLabel((string) ($formatter->label() ?? ''));
        $field_types = $formatter->get('field_types');
        if (!is_array($field_types)) {
          $field_types = !empty($field_types) ? [$field_types] : [];
        }
        $this->derivatives[$formatter->id()]['field_types'] = $field_types;
        $this->derivatives[$formatter->id()]['formatter'] = $formatter->id();
        $this->derivatives[$formatter->id()]['config_dependencies'] = $formatter->getDependencies();
        $this->derivatives[$formatter->id()]['config_dependencies']['config'][] = $formatter->getConfigDependencyName();
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Returns Formatter label with optional prefix.
   *
   * @param string $label
   *   Formatter label.
   *
   * @return string
   *   The Formatter label with optional prefix.
   */
  protected function getLabel(string $label): string {
    // Label prefix.
    if (!empty($this->settings['label_prefix'])) {
      $label = "{$this->settings['label_prefix_value']}: {$label}";
    }

    return $label;
  }

}
