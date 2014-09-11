<?php

/**
 * @file
 * Mollom API Keys class.
 *
 * @license MIT|GNU GPL v2
 *   See LICENSE-MIT.txt or LICENSE-GPL.txt shipped with this library.
 */
namespace Drupal\mollom\API;

/**
 * The base class for Mollom client implementations.
 */
class APIKeys {

  public static function getStatus($check = FALSE) {
    return TRUE;
  }
} 