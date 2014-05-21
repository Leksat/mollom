<?php
/**
 * Mollom configuration settings form.
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\mollom\API\DrupalClient;
use Drupal\mollom\API\DrupalClientFactory;
use Drupal\mollom\API\APIKeys;
use Drupal\mollom\Utility\Settings;

class ConfigureSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mollom_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->config('mollom.settings');

    $mollom = DrupalClientFactory::getClient();
    $check = empty($form_state['values']);
    $status = APIKeys::getStatus($check);
    if ($check && $status['isVerified'] && !$config->get('testing_mode')) {
      drupal_set_message(t('Mollom servers verified your keys. The services are operating correctly.'));
    }

    $form['keys'] = array(
      '#type' => 'fieldset',
      '#title' => t('Mollom API keys'),
      '#tree' => TRUE,
      '#description' => t('To obtain API keys, <a href="@signup-url">sign up</a> or log in to your <a href="@site-manager-url">Site manager</a>, register this site, and copy the keys into the fields below.', array(
        '@signup-url' => 'https://mollom.com/pricing',
        '@site-manager-url' => 'https://mollom.com/site-manager',
      )),
      '#collapsible' => TRUE,
      // Only show key configuration fields if they are not configured or invalid.
      '#collapsed' => !$status['isVerified'],
    );
    // Keys are not #required to allow to install this module and configure it
    // later.
    $form['keys']['public'] = array(
      '#type' => 'textfield',
      '#title' => t('Public key'),
      '#default_value' => $config->get('keys.public'),
      '#description' => t('Used to uniquely identify this site.'),
    );
    $form['keys']['private'] = array(
      '#type' => 'textfield',
      '#title' => t('Private key'),
      '#default_value' => $config->get('keys.private'),
      '#description' => t('Used for authentication. Similar to a password, the private key should not be shared with anyone.'),
    );

    $form['fallback'] = array(
      '#type' => 'radios',
      '#title' => t('When the Mollom service is unavailable'),
      '#default_value' => $config->get('fallback'),
      '#options' => array(
        Settings::MOLLOM_FALLBACK_ACCEPT => t('Accept all form submissions'),
        Settings::MOLLOM_FALLBACK_BLOCK => t('Block all form submissions'),
      ),
      '#description' => t('Mollom offers a <a href="@pricing-url">high-availability</a> infrastructure for users on paid plans to reduce potential downtime.', array(
        '@pricing-url' => 'https://mollom.com/pricing',
      )),
    );

    $options = DrupalClient::getSupportedLanguages();
    $default_languages = isset($status['expectedLanguages']) ? $status['expectedLanguages'] : $mollom->loadConfiguration('expectedLanguages');
    // @todo: Add chosen UI functionality for improved UX.
    $form['expected_languages'] = array(
      '#type' => 'select',
      '#title' => t('Expected languages'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#size' => 6,
      '#default_value' => $default_languages,
      '#description' => t('Restricts all posts to selected languages. Used by text analysis only. Leave empty if users may post in other languages.'),
    );

    $form['privacy_link'] = array(
      '#type' => 'checkbox',
      '#title' => t("Show a link to Mollom's privacy policy"),
      '#return_value' => true,
      '#default_value' => $config->get('privacy_link'),
      '#description' => t('Only applies to forms protected with text analysis. When disabling this option, you should inform visitors about the privacy of their data through other means.'),
    );

    $form['testing_mode'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable testing mode'),
      '#return_value' => true,
      '#default_value' => $config->get('testing_mode'),
      '#description' => t('Submitting "ham", "unsure", or "spam" triggers the corresponding behavior; image CAPTCHAs only respond to "correct" and audio CAPTCHAs only respond to "demo". Do not enable this option if this site is publicly accessible.'),
    );

    $form['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced configuration'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    // Lower severity numbers indicate a high severity level.
    $form['advanced']['log_level'] = array(
      '#type' => 'radios',
      '#title' => t('Mollom logging level warning'),
      '#options' => array(
        WATCHDOG_WARNING => t('Only log warnings and errors'),
        WATCHDOG_DEBUG => t('Log all Mollom messages'),
      ),
      '#default_value' => $config->get('log_level'),
    );
    $form['advanced']['audio_captcha_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable audio CAPTCHAs.'),
      '#description' => t('Allows users to switch to an audio verification using the <a href="!faq-url">NATO alphabet</a>.  This may not be appropriate for non-English language sites.', array(
        '!faq-url' => 'https://mollom.com/faq/mollom-audible-captcha-language',
      )),
      '#return_value' => true,
      '#default_value' => $config->get('captcha.audio.enabled'),
    );
    $form['advanced']['connection_timeout_seconds'] = array(
      '#type' => 'number',
      '#title' => t('Time-out when attempting to contact Mollom servers.'),
      '#description' => t('This is the length of time that a call to Mollom will wait before timing out.'),
      '#default_value' => $config->get('connection_timeout_seconds'),
      '#size' => 5,
      '#field_suffix' => t('seconds'),
      '#required' => TRUE,
    );
    $form['advanced']['fba_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable form behavior analysis (beta).'),
      '#description' => t('This will place a small tracking image from Mollom on each form protected by textural analysis to help Mollom determine if the form is filled out by a bot.  <a href="!fba-url">Learn more</a>.', array(
        '!fba-url' => 'https://mollom.com/faq/form-behavior-analysis',
      )),
      '#default_value' => $config->get('fba.enabled'),
    );
    $form['advanced']['fai'] = array(
      '#type' => 'fieldset',
      '#title' => t('Flag as inappropriate'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['advanced']['fai']['fai_enabled'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable flag as inappropriate functionality.'),
      '#description' => t('This will allow users to mark content as inappropriate for your site.  <a href="!fba-url">Learn more</a>.', array(
        '!fai-url' => 'https://mollom.com/faq/flag-as-inappropriate',
      )),
      '#default_value' => $config->get('fai.enabled'),
    );
    /*
    // Available entity types are those entities that have a report access
    // callback defined.  This is limited by type even though multiple forms
    // can be for the same entity type.
    $forms = mollom_form_list();
    $options = array();
    foreach($forms as $info) {
      if (!empty($info['entity']) && !empty($info['entity report access callback'])) {
        $options[] = $info['entity'];
      }
    };
    sort($options);
    $form['mollom_advanced']['mollom_fai_entity_types'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Allow users to  "Flag as Inappropriate" for the following:'),
      '#description' => t('"Flag as inappropriate" will only appear on protected forms for users who have permission to the content type and have the permission to "Report to Mollom".  <a href="!fai-url">Learn more</a>.', array(
        '!fai-url' => 'https://mollom.com/faq/flag-as-inappropriate',
      )),
      '#options' => drupal_map_assoc($options),
      '#default_value' => variable_get('mollom_fai_entity_types', array('comment' => 'comment')),
    );
    // Only display dialog options if the user has at least one integrated
    // module installed and enabled.
    module_load_include('inc', 'mollom', 'mollom.flag');
    $implemented = mollom_flag_dialog_info();
    $available = array_intersect_key($implemented, module_list());
    $options = array();
    foreach($available as $module => $info) {
      $options[$module] = $info['title'];
    }
    if (count($options) > 0) {
      $options['mollom'] = 'Mollom custom dialog';
      $form['mollom_advanced']['mollom_fai_dialog'] = array(
        '#type' => 'radios',
        '#title' => t('Flag as inappropriate dialog type'),
        '#options' => $options,
        '#default_value' => variable_get('mollom_fai_dialog', 'mollom'),
      );
    }
    else {
      // Reset to use the Mollom dialog if no integrated modules are available.
      $form['mollom_advanced']['mollom_fai_dialog'] = array(
        '#type' => 'value',
        '#value' => 'mollom',
      );
    }

    $form['#submit'][] = 'mollom_admin_settings_prepare_submit';
    $form['#submit'][] = 'mollom_admin_settings_submit';
    */

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function validateForm(array &$form, array &$form_state) {
    $message = $this->validateKey($form_state['values']['keys']['public']);
    if (!empty($message)) {
      $this->setFormError('keys][public', $form_state, $message);
    }
    $message = $this->validateKey($form_state['values']['keys']['private']);
    if (!empty($message)) {
      $this->setFormError('keys][private', $form_state, $message);
    }
  }

  /**
   * Validates a user-submitted Mollom key value.
   *
   * @param string $key
   *   The key value to validate.
   * @return string
   *   An error message to display or NULL if no errors found.
   */
  protected function validateKey($key) {
    $error = NULL;
    if (empty($key)) {
      return $error;
    }
    $key = trim($key);
    if (drupal_strlen($key) !== 32) {
      $error = $this->t('Keys must be 32 characters long.  Ensure you copied the key correctly.');
    }
    return $error;
  }

  /**
   * Form submission handler for settings.
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = $this->config('mollom.settings');
    $config->set('keys.public', $form_state['values']['keys']['public'])
      ->set('keys.private', $form_state['values']['keys']['private'])
      ->set('fallback', $form_state['values']['fallback'])
      ->set('languages_expected', $form_state['values']['expected_languages'])
      ->set('privacy_link', $form_state['values']['privacy_link'])
      ->set('testing_mode', $form_state['values']['testing_mode'])
      ->set('log_level', $form_state['values']['log_level'])
      ->set('captcha.audio.enabled', $form_state['values']['audio_captcha_enabled'])
      ->set('connection_timeout_seconds', $form_state['values']['connection_timeout_seconds'])
      ->set('fba.enabled', $form_state['values']['fba_enabled'])
      ->set('fai.enabled', $form_state['values']['fai_enabled'])
      -> save();

    parent::submitForm($form, $form_state);
    // Update Mollom site record with local configuration.
    APIKeys::getStatus(true, true);
  }
}
