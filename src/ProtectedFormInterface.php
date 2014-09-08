<?php

/**
 * @file
 * Contains \Drupal\mollom\ProtectedFormInterface.
 */

namespace Drupal\mollom;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a form protected by Mollom.
 */
interface ProtectedFormInterface extends ConfigEntityInterface {

  /**
   * Form protection mode: No protection.
   */
  const MOLLOM_MODE_DISABLED = 0;

  /**
   * Form protection mode: CAPTCHA-only protection.
   */
  const MOLLOM_MODE_CAPTCHA = 1;

  /**
   * Form protection mode: Text analysis with fallback to CAPTCHA.
   */
  const MOLLOM_MODE_ANALYSIS = 2;

}
