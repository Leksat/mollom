<?php

/**
 * @file
 * Contains Drupal\mollom\Form\ProtectedFormAddForm.
 */

namespace Drupal\mollom\Form;

/**
 * Provides the add form for a protected form entity.
 */
class ProtectedFormAddForm extends ProtectedFormFormBase {

  /**
   * Returns the actions provided by this form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, array &$form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Add protection');
    return $actions;
  }
}
