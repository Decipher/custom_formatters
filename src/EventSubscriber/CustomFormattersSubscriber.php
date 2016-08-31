<?php

namespace Drupal\custom_formatters\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class CustomFormattersSubscriber.
 *
 * @package Drupal\field_tokens\CustomFormattersSubscriber.
 */
class CustomFormattersSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['optionalIntegrations'];

    return $events;
  }

  /**
   * Loads all optional integration includes.
   */
  public function optionalIntegrations() {
    $modules = \Drupal::moduleHandler()->getModuleList();
    $dirname = dirname(__FILE__) . '/../../includes';
    $includes = file_scan_directory($dirname, '/.php/');
    foreach (array_keys($modules) as $module) {
      $file = "{$dirname}/{$module}.php";
      if (isset($includes[$file])) {
        require_once $file;
      }
    }
  }

}
