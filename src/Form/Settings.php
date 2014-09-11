<?php

/**
 * @file
 * Contains \Drupal\mollom\Form\Settings.
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures devel settings.
 */
class Settings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'mollom_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $mollom_config = $this->config('mollom.settings');

    $form['some_setting'] = array(
      '#type' => 'checkbox',
      '#title' => t('First checkbox etc'),
      '#description' => t('Just something here'),
      '#default_value' => $mollom_config->get('rebuild_theme_registry'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mollom.settings')
      ->set('some_setting', $form_state['values']['some_setting'])
      ->save();
  }

}
