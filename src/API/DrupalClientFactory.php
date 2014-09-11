<?php
/**
 * @file
 * Contains \Drupal\mollom\API\DrupalClientFactory.
 */

namespace Drupal\mollom\API;

use Composer\Autoload\ClassLoader;

/**
 * A factory class to create instances of the Drupal Mollom API implementation.
 */
class DrupalClientFactory {

  /**
   * An array of client instances based on type of class.
   *
   * @var array
   */
  static protected $classes;

  /**
   * Factory method to return a client implementation.
   *
   * @param string $class
   *   (optional) A specific class implementation to load.
   * @return \Mollom\Client\Client
   *   A Drupal implementation of the Mollom API client.
   */
  static public function getClient($class = NULL) {
    if (!isset($class)) {
      $class = \Drupal::config('mollom.settings')->get('testing_mode') ? 'DrupalTestClient' : 'DrupalClient';
    }

    // If there is no instance yet or if it is not of the desired class, create a
    // new one.
    if (!isset(self::$classes[$class]) || !(self::$classes[$class] instanceof $class)) {

      // Add the Mollom API library into the namespaces to load.
      $loader = require(drupal_get_path('module', 'mollom') . '/vendor/autoload.php');
      $loader->setPsr4('mollom\\', drupal_get_path('module', 'mollom') . '/vendor/mollom/src');

      $namespaced_class = 'Drupal\\mollom\\API\\' . $class;
      self::$classes[$class] = new $namespaced_class();
    }

    return self::$classes[$class];
  }
}
