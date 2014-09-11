<?php

/**
 * @file
 * Contains Drupal\mollom\Entity\Form.
 *
 * This contains our entity class.
 *
 * Originally based on code from blog post at
 * http://previousnext.com.au/blog/understanding-drupal-8s-config-entities
 */

namespace Drupal\mollom\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the robot entity.
 *
 * The lines below, starting with '@ConfigEntityType,' are a plugin annotation.
 * These define the entity type to the entity type manager.
 *
 * The properties in the annotation are as follows:
 *  - id: The machine name of the entity type.
 *  - label: The human-readable label of the entity type. We pass this through
 *    the "@Translation" wrapper so that the multilingual system may
 *    translate it in the user interface.
 *  - controllers: An array specifying controller classes that handle various
 *    aspects of the entity type's functionality. Below, we've specified
 *    controllers which can list, add, edit, and delete our robot entity, and
 *    which control user access to these capabilities.
 *  - config_prefix: This tells the config system the prefix to use for
 *    filenames when storing entities. This means that the default entity we
 *    include in our module has the filename
 *    'config_entity_example.robot.marvin.yml'.
 *  - entity_keys: Specifies the class properties in which unique keys are
 *    stored for this entity type. Unique keys are properties which you know
 *    will be unique, and which the entity manager can use as unique in database
 *    queries.
 *
 * @see http://previousnext.com.au/blog/understanding-drupal-8s-config-entities
 * @see annotation
 * @see Drupal\Core\Annotation\Translation
 *
 * @ingroup mollom
 *
 * @ConfigEntityType(
 *   id = "mollom_form",
 *   label = @Translation("Mollom Form Configuration"),
 *   controllers = {
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
class Form extends ConfigEntityBase {

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

}
