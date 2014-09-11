<?php

/**
 * @file
 * Contains Drupal\mollom\Form\ProtectedFormEditForm.
 */

namespace Drupal\mollom\Form;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the edit form for a protected form entity.
 */
class ProtectedFormEditForm extends ProtectedFormFormBase {

  /**
   * Returns the actions provided by this form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Edit protection');
    return $actions;
  }
}
