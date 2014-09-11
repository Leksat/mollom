<?php
/**
 * @file
 * Contains Drupal\mollom\Form\BlacklistEntryFormBase.
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mollom\Storage\BlacklistStorage;

/**
 * Class BlacklistEntryFormBase
 *
 * Provides a base form for adding/editing a blacklist entry.
 *
 * @package Drupal\mollom\Form
 */
abstract class BlacklistEntryFormBase extends FormBase {

  /**
   * The blacklist entry being manipulated by this form.
   *
   * @var $entry
   */
  protected $entry;

  /**
   * Gets the blacklist entry.
   *
   * @return array
   *   The associative array of blacklist entry data.
   */
  public function getEntry() {
    return $this->entry;
  }

  /**
   * Sets the current blacklist entry for the form.
   *
   * @param array $entry
   *   The associative array of entry data.
   *
   * @return $this
   *   A reference to the current class for chaining.
   */
  public function setEntry(array $entry) {
    if (!is_array($entry)) {
      $entry = array();
    }
    $defaults = array(
      'reason' => BlacklistStorage::TYPE_SPAM,
      'context' => BlacklistStorage::CONTEXT_ALL_FIELDS,
      'value' => '',
      'matches' => BlacklistStorage::MATCH_CONTAINS,
    );
    $this->entry = array_merge($defaults, $entry);
    return $this;
  }

  /**
   * Overrides Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entry_id = NULL) {
    $entry = $this->loadByEntryId($entry_id);

    $form['reason'] = array(
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#default_value' => $entry['type'],
      '#options' => $this->getBlacklistTypeOptions(),
      '#required' => TRUE,
    );

    $form['context'] = array(
      '#type' => 'select',
      '#title' => $this->t('Context'),
      '#title_display' => 'invisible',
      '#default_value' => $entry['context'],
      '#options' => $this->getContextOptions(),
      '#required' => TRUE,
    );

    $form['matches'] = array(
      '#type' => 'select',
      '#title' => $this->t('Matches'),
      '#title_display' => 'invisible',
      '#default_value' => $entry['matches'],
      '#options' => $this->getMatchesOptions(),
      '#required' => TRUE,
    );

    $form['value'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#title_display' => 'invisible',
      '#default_value' => $entry['value'],
      '#required' => TRUE,
    );

    $form['actions'] = array(
      '#type' => 'actions',
    );

    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save entry'),
    );

    return $form;
  }

  /**
   * Overrides Drupal\Core\Form\FormInterface::validateForm().
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * Overrides Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entry = array();
    if (isset($form_state['values']['blacklist_entry_id'])) {
      $entry['id'] = $form_state['values']['blacklist_entry_id'];
    }
    $entry['reason'] = $form_state['values']['reason'];
    $entry['context'] = $form_state['values']['reason'];
    $entry['matches'] = $form_state['values']['reason'];
    $entry['value'] = $form_state['values']['reason'];
    $saved = BlacklistStorage::saveEntry($entry);
    if ($saved) {
      drupal_set_message($this->t('The blacklist entry %entry has been saved to your %type blacklist.', array(
        '%entry' => $entry['value'],
        '%type' => $entry['reason'],
      )));
    }
  }

  /**
   * Generates the form options for blacklist entry types.
   *
   * @returns array
   *   An array suitable for use as select input options.
   */
  protected function getBlacklistTypeOptions() {
    return array(
      BlacklistStorage::TYPE_SPAM => $this->t('Spam'),
      BlacklistStorage::TYPE_PROFANITY => $this->t('Profanity'),
      BlacklistStorage::TYPE_UNWANTED => $this->t('Unwanted'),
    );
  }

  /**
   * Generates the form options for blacklist entry context.
   *
   * @returns array
   *   An array suitable for use as select input options.
   */
  protected function getContextOptions() {
    return array(
      BlacklistStorage::CONTEXT_ALL_FIELDS => $this->t('- All fields -'),
      BlacklistStorage::CONTEXT_AUTHOR_FIELDS => $this->t('- All author fields -'),
      BlacklistStorage::CONTEXT_AUTHOR_NAME => $this->t('Author name'),
      BlacklistStorage::CONTEXT_AUTHOR_MAIL => $this->t('Author e-mail'),
      BlacklistStorage::CONTEXT_AUTHOR_IP => $this->t('Author IP'),
      BlacklistStorage::CONTEXT_AUTHOR_ID => $this->t('Author User ID'),
      BlacklistStorage::CONTEXT_POST_FIELDS => $this->t('- All post fields -'),
      BlacklistStorage::CONTEXT_POST_TITLE => $this->t('Post title'),
      BlacklistStorage::CONTEXT_LINKS => $this->t('Links'),
    );
  }

  /**
   * Generates the form options for type of matching.
   *
   * @return array
   *   An array suitable for use as select input options.
   */
  protected function getMatchesOptions() {
    return array(
      BlacklistStorage::MATCH_CONTAINS => $this->t('Contains'),
      BlacklistStorage::MATCH_EXACT => $this->t('Exact'),
    );
  }

  /**
   * Loads a blacklist entry by id and saves it to the class variable.
   *
   * @param $entry_id
   *   The id of the blacklist entry
   * @return array
   *   The blacklist entry data (or data for a blank entry)
   */
  private function loadByEntryId($entry_id = NULL) {
    if (is_null($entry_id)) {
      return array();
    }
    $this->setEntry(BlacklistStorage::getEntry($entry_id));
    return $this->getEntry();
  }
}
