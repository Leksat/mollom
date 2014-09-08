<?php
/**
 * @file
 * Contains Drupal\mollom\Entity\ProtectedFormInterface.
 */

namespace Drupal\mollom\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\mollom\ProtectedFormInterface;

/**
 * Defines a Mollom protected form.
 *
 * @ConfigEntityType(
 *   id = "mollom_form",
 *   label = @Translation("Mollom protected form"),
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\mollom\Controller\ProtectedFormListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mollom\Form\ProtectedFormAddForm",
 *       "edit" = "Drupal\mollom\Form\ProtectedFormEditForm",
 *       "search" = "Drupal\mollom\Form\ProtectedFormSearchForm",
 *       "delete" = "Drupal\mollom\Form\ProtectedFormDeleteForm"
 *     }
 *   },
 *   admin_permission = "administer mollom",
 *   links = {
 *     "edit-form" = "form.edit",
 *   },
 *   config_prefix = "form",
 *   entity_keys = {
 *     "id" = "form_id",
 *     "label" = "label",
 *     "status" = "status"
 *   }
 * )
 */
class ProtectedForm extends ConfigEntityBase implements ConfigEntityInterface, ProtectedFormInterface {

  /**
   * The form id of the protected form.
   *
   * @var string
   */
  public $mollom_form_id;

  /**
   * The entity type of the form.
   *
   * @var string
   */
  public $entity;

  /**
   * The entity bundle of the form.
   *
   * @var string
   */
  public $bundle;

  /**
   * The protection mode for the form.
   *
   * @var int
   */
  public $mode = self::MOLLOM_MODE_ANALYSIS;

  /**
   * A string denoting the content checks to perform (spam, profanity, etc.)
   *
   * @var string
   */
  public $checks = 'spam';

  /**
   * The action to perform when text analysis is unsure.
   *
   * @var string
   */
  public $unsure = 'captcha';

  /**
   * Whether to discard (true) or retain (false) spam posts.
   *
   * @var bool
   */
  public $discard;

  /**
   * Whether to indicate with Mollom Content Moderation Platform.
   *
   * @var bool
   */
  public $moderate;

  /**
   * Form elements to analyze.
   *
   * @var string
   */
  public $enabled_fields;

  /**
   * Strictness of text analysis checks.
   *
   * @var string
   */
  public $strictness;

  /**
   * Module name owning the form.
   *
   * @var string
   */
  public $module;

  /**
   * The name of the protected form.
   */
  public function getFormName() {
    // @todo: Make this something better
    return $this->mollom_form_id;
  }

  /**
   * The untranslated string for any protected mode.
   */
  public static function getProtectedModeString($mode) {
    switch ($mode) {
      case MOLLOM_MODE_DISABLED:
        return 'Disabled';
        break;
      case MOLLOM_MODE_CAPTCHA:
        return 'CAPTCHA';
        break;
      case MOLLOM_MODE_ANALYSIS:
        return 'Textual analsis';
      default:
        return '';
    }
  }

  /**
   * The untranslated string representing the protected mode.
   */
  public function getProtectedMode() {
    return self::getProtectedModeString($this->mode);
  }

} 
