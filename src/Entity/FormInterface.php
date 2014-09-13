<?php

namespace Drupal\mollom\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the interface for index entities.
 */
interface FormInterface extends ConfigEntityInterface {



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

  /**
   * Server communication failure fallback mode: Block all submissions of protected forms.
   */
  const MOLLOM_FALLBACK_BLOCK = 0;

  /**
   * Server communication failure fallback mode: Accept all submissions of protected forms.
   */
  const MOLLOM_FALLBACK_ACCEPT = 1;

}