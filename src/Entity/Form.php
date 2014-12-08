<?php

/**
 * @file
 * Contains Drupal\mollom\Entity\Form.
 *
 * This contains a form that is protected by Mollom.
 */

namespace Drupal\mollom\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the form entity.
 *
 * @ingroup mollom
 *
 * @ConfigEntityType(
 *   id = "mollom_form",
 *   label = @Translation("Mollom Form Configuration"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigEntityStorage",
 *     "list_builder" = "Drupal\mollom\Controller\FormListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mollom\Form\FormAdd",
 *       "edit" = "Drupal\mollom\Form\FormEdit",
 *       "delete" = "Drupal\mollom\Form\FormDelete",
 *     },
 *   },
 *   admin_permission = "administer mollom",
 *   config_prefix = "form",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "edit-form" = "mollom.form.edit",
 *     "delete-form" = "mollom.form.delete",
 *   }
 * )
 */
class Form extends ConfigEntityBase implements FormInterface {

  /**
   * The form ID.
   *
   * @var string
   */
  public $id;

  /**
   * The form UUID.
   *
   * @var string
   */
  public $uuid;

  /**
   * The form label.
   *
   * @var string
   */
  public $label;

  /**
   * The form checks.
   *
   * @var string
   */
  public $checks;

  /**
   * The form mode.
   *
   * @var string
   */
  public $mode;

  /**
   * The form fields to analyze.
   *
   * @var array
   */
  public $enabled_fields;

  /**
   * The Strictness of the analyzer.
   *
   * @var array
   */
  public $strictness;

  /**
   * what to do if Mollom is not sure.
   *
   * @var array
   */
  public $unsure;

  /**
   * What to do if Mollom identified it as spam.
   *
   * @var array
   */
  public $discard;

  /**
   * Moderation platform enabled or disabled.
   *
   * @var array
   */
  public $moderation;

  /**
   * Stored mapping of the Drupal fields to Mollom fields.
   *
   * @var array
   */
  public $mapping;

}
