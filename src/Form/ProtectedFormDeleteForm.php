<?php

/**
 * @file
 * Contains Drupal\mollom\Form\ProtectedFormDeleteForm
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

/**
 * Class ProtectedFormDeleteForm.
 *
 * Provides a confirm form for deleting the entity.
 */
class ProtectedFormDeleteForm extends EntityConfirmFormBase {

  /**
   * Gathers a confirmation question.
   *
   * @return string
   *   Translated string.
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to unprotect this form?');
  }

  /**
   * Displays a custom description.
   *
   * @return string
   *   Translated string.
   */
  public function getDescription() {
    return $this->t('Mollom will no longer protect this form from spam.');
  }

  /**
   * Gather the confirmation text.
   *
   * The confirm text is used as a the text in the button that confirms the
   * question posed by getQuestion().
   *
   * @return string
   *   Translated string.
   */
  public function getConfirmText() {
    return $this->t('Unprotect');
  }

  /**
   * Gets the cancel route.
   *
   * @return array
   *   The route to go to if the user cancels the deletion. The key is
   *   'route_name'. The value is the route name.
   */
  public function getCancelRoute() {
    return new Url('mollom.forms.list');
  }

  /**
   * The submit handler for the confirm form.
   *
   * For entity delete forms, you use this to delete the entity in
   * $this->entity.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function submit(array $form, array &$form_state) {
    // Delete the entity.
    $this->entity->delete();

    drupal_set_message($this->t('The form protection was removed.'));

    // Redirect the user to the list controller when complete.
    $form_state['redirect_route']['route_name'] = 'mollom.forms.list';
  }

}
