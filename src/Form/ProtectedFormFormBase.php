<?php
/**
 * Created by PhpStorm.
 * User: lisa.backer
 * Date: 6/17/14
 * Time: 6:10 AM
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityForm;
use \Drupal\Core\Entity\Query\QueryFactory;
use Drupal\mollom\Entity\ProtectableForms;
use Drupal\mollom\Entity\ProtectedForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProtectedFormFormBase extends EntityForm {

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Construct the ProtectedFormFormBase.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   An entity query factory for the robot entity type.
   */
  public function __construct(QueryFactory $query_factory) {
    $this->entityQueryFactory = $query_factory;
  }

  /**
   * Factory method for ProtectedFormFormBase.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.query'));
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   *
   * Builds the entity add/edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the add/edit form.
   */
  public function buildForm(array $form, array &$form_state) {
    // Get anything we need form the base class.
    $form = parent::buildForm($form, $form_state);
    $protected_form = $this->entity;

    if (empty($protected_form->mollom_form_id)) {
      $form['mollom_form_id'] = array(
        '#type' => 'select',
        '#title' => $this->t('Form'),
        '#options' => ProtectableForms::getAdminFormOptions(),
      );
    }
    else {
      $form['mollom_form_id'] = array(
        '#type' => 'value',
        '#value' => $protected_form->mollom_form_id,
      );
    }

    $recommended = $this->t('recommended');

    // Analysis mode.
    $modes = array();
    $modes[ProtectedForm::MOLLOM_MODE_ANALYSIS] = t('!option <em>(!recommended)</em>', array(
      '!option' => $this->t(ProtectedForm::getProtectedModeString(ProtectedForm::MOLLOM_MODE_ANALYSIS)),
      '!recommended' => $recommended,
    ));
    $modes[ProtectedForm::MOLLOM_MODE_CAPTCHA] = t(ProtectedForm::getProtectedModeString(ProtectedForm::MOLLOM_MODE_CAPTCHA));
    $form['mode'] = array(
      '#type' => 'radios',
      '#title' => t('Protection mode'),
      '#options' => $modes,
      '#default_value' => $protected_form->mode,
    );
    $form['mode'][ProtectedForm::MOLLOM_MODE_ANALYSIS] = array(
      '#description' => t('Mollom will analyze the post and will only show a CAPTCHA when it is unsure.'),
    );
    $form['mode'][ProtectedForm::MOLLOM_MODE_CAPTCHA] = array(
      '#description' => t('A CAPTCHA will be shown for every post.  Only choose this if there are too few test fields to analyze.'),
    );
    $form['mode'][ProtectedForm::MOLLOM_MODE_CAPTCHA]['#description'] .= '<br />' . t('Note: Page caching is disabled on all pages containing a CAPTCHA-only protected form.');

    // Bypass permissions.
    $all_permissions = array();
    $module_handler = \Drupal::moduleHandler();
    foreach ($module_handler->getImplementations('permissions') as $module) {
      if ($module_permissions = $module_handler->invoke($module, 'permission')) {
        foreach ($module_permissions as &$info) {
          $info += array('module' => $module);
        }
        $all_permissions += $module_permissions;
      }
    }

    return $form;
  }


  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * To set the submit button text, we need to override actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, array &$form_state) {
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::validate().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function validate(array $form, array &$form_state) {
    parent::validate($form, $form_state);

    // Add code here to validate your config entity's form elements.
    // Nothing to do here.
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * Saves the protected form.
   */
  public function save(array $form, array &$form_state) {
    $mollom_form = $this->entity;

    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class.
    $status = $mollom_form->save();

    $uri = $mollom_form->url();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('The form is now protected.'));
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Form protection has been edited.'));
    }

    $form_state['route_redirect'] = new Url('mollom.forms.list');
  }

}
