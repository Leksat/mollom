<?php
/**
 * Created by PhpStorm.
 * User: lisa.backer
 * Date: 7/3/14
 * Time: 1:09 PM
 */

namespace Drupal\mollom\Form;


class BlacklistEntryEditForm extends BlacklistEntryFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mollom_blacklist_edit';
  }

  /*
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $entry_id = NULL) {
    $form = parent::buildForm($form, $form_state, $entry_id);

    $form['blacklist_entry_id'] = array(
      '#type' => 'value',
      '#value' => $entry_id,
    );

    $form['actions']['submit']['#value'] = $this->t('Save blacklist entry');
    return $form;
  }
} 
