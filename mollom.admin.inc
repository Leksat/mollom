<?php

/**
 * @file
 * Administrative page callbacks for Mollom module.
 */

/**
 * Helper function to prepare a list of available languages.
 */
function _mollom_supported_languages() {
  // D7-: _locale_prepare_predefined_list() removes installed languages, which
  // is the exact opposite of what we want.
  include_once DRUPAL_ROOT . '/includes/iso.inc';
  $predefined = _locale_get_predefined_list();
  $supported = array_flip(MOLLOM::$LANGUAGES_SUPPORTED);
  $supported = array_combine(array_keys($supported), array_keys($supported));
  
  // Define those mappings that differ between Drupal codes and Mollom codes.
  $mapped = array(
    'nb' => 'no',
    'zh-hans' => 'zh-cn',
    'zh-hant' => 'zh-tw',
  );
  foreach($mapped as $drupal_key => $mollom_key) {
    if (isset($supported[$mollom_key])) {
      $supported[$drupal_key] = $mollom_key;
      unset($supported[$mollom_key]);
    }
  }

  $options = array();
  $languages_enabled = language_list();
  $installed_languages = array();

  // This does assume that all Mollom supported languages are in the predefined
  // Drupal list.
  foreach ($predefined as $langcode => $language) {
    $found = FALSE;
    $simplified_code = strtok($langcode, '-');
    if (isset($supported[$simplified_code]) && !isset($options[$simplified_code])) {
      $options[$supported[$simplified_code]] = t($language[0]);
      $found = TRUE;
    }
    else if (isset($supported[$langcode]) && !isset($options[$langcode])) {
      $options[$supported[$langcode]] = t($language[0]);
      $found = TRUE;
    }
    if ($found) {
      // Update the list of installed languages that are supported.
      if (isset($languages_enabled[$langcode])) {
        $installed_languages[$supported[$simplified_code]] = $simplified_code;
      }
    }
  }
  // Sort by translated option labels.
  asort($options);
  // UX: Sort installed languages first.
  return array_intersect_key($options, $installed_languages) + $options;
}

/**
 * Checks the configuration status on Mollom administration pages.
 *
 * On all Mollom administration pages, check the module configuration and
 * display the corresponding requirements error, if invalid.
 */
function mollom_admin_site_status($force = FALSE, $update = FALSE) {
  $status = _mollom_status($force, $update);
  if (empty($_POST) && !$status['isVerified']) {
    // Fetch and display requirements error message, without re-checking.
    module_load_install('mollom');
    $requirements = mollom_requirements('runtime', FALSE);
    if (isset($requirements['mollom']['description'])) {
      drupal_set_message($requirements['mollom']['description'], 'error');
    }
  }
  return $status;
}


/**
 * Form submit handler for 'Next' button on Mollom form configuration form.
 */
function mollom_admin_configure_form_next_submit($form, &$form_state) {
  $form_id = $form_state['values']['mollom']['form_id'];
  $form_state['redirect'] = $_GET['q'] . '/' . $form_id;
}

/**
 * Form validation handler for mollom_admin_configure_form().
 */
function mollom_admin_configure_form_validate(&$form, &$form_state) {
  // For the 'configure' step, output custom #required form element errors for
  // 'checks' and 'enabled_fields', as their labels do not work with the default
  // #required form error message.
  if ($form_state['storage']['step'] == 'configure') {
    // Make field checkboxes required, if protection mode is text analysis.
    // @see http://drupal.org/node/875722
    $required = ($form_state['values']['mollom']['mode'] == MOLLOM_MODE_ANALYSIS);
    $form['mollom']['checks']['#required'] = $required;
    $form['mollom']['discard']['#required'] = $required;

    if ($required && !array_filter($form_state['values']['mollom']['checks'])) {
      form_error($form['mollom']['checks'], t('At least one text analysis check is required.'));
    }
  }
}

/**
 * Form submit handler for mollom_admin_configure_form().
 */
function mollom_admin_configure_form_submit($form, &$form_state) {
  $mollom_form = $form_state['values']['mollom'];
  // Merge in form information from $form_state.
  $mollom_form += $form_state['storage']['mollom_form'];

  // Only store a list of enabled textual analysis checks.
  $mollom_form['checks'] = array_keys(array_filter($mollom_form['checks']));
  // Prepare selected fields for storage.
  $enabled_fields = array();
  foreach (array_keys(array_filter($mollom_form['enabled_fields'])) as $field) {
    $enabled_fields[] = rawurldecode($field);
  }
  $mollom_form['enabled_fields'] = $enabled_fields;

  $status = mollom_form_save($mollom_form);
  if ($status === SAVED_NEW) {
    drupal_set_message(t('The form protection has been added.'));
  }
  else {
    drupal_set_message(t('The form protection has been updated.'));
  }

  $form_state['redirect'] = 'admin/config/content/mollom';
}

/**
 * Form builder; Remove Mollom protection from a form.
 */
function mollom_admin_unprotect_form($form, &$form_state, $mollom_form) {
  $form['#tree'] = TRUE;
  $form['form'] = array(
    '#type' => 'item',
    '#title' => t('Form'),
    '#markup' => $mollom_form['title'],
  );
  $form['mollom']['form_id'] = array(
    '#type' => 'value',
    '#value' => $mollom_form['form_id'],
  );

  return confirm_form($form,
    t('Are you sure you want to unprotect this form?'),
    'admin/config/content/mollom',
    t('Mollom will no longer protect this form from spam.')
  );
}

/**
 * Form submit handler for mollom_admin_unprotect_form().
 */
function mollom_admin_unprotect_form_submit($form, &$form_state) {
  mollom_form_delete($form_state['values']['mollom']['form_id']);

  drupal_set_message(t('The form protection has been removed.'));

  $form_state['redirect'] = 'admin/config/content/mollom';
}

/**
 * Form constructor to configure the blacklist.
 *
 * @param $type
 *   The type of blacklist; i.e., 'spam', 'profanity', or 'unwanted'.
 */
function mollom_admin_blacklist_form($form, &$form_state, $type = 'spam') {
  $form['#tree'] = TRUE;
  $form['#attached']['css'][] = drupal_get_path('module', 'mollom') . '/mollom.admin.css';

  // Translate internal reason values for rendering and select list in form.
  $contexts = array(
    'allFields' => t('- All fields -'),
    'author' => t('- All author fields -'),
    'authorName' => t('Author name'),
    'authorMail' => t('Author e-mail'),
    'authorIp' => t('Author IP'),
    'authorId' => t('Author User ID'),
    'post' => t('- All post fields -'),
    'postTitle' => t('Post title'),
    'links' => t('Links'),
  );
  $matches = array(
    'contains' => t('contains'),
    'exact' => t('exact'),
  );

  $form['blacklist'] = array();
  // Do not retrieve the current blacklist when submitting the form.
  $blacklist = (empty($form_state['input']) ? mollom()->getBlacklist() : array());
  if (is_array($blacklist)) {
    foreach ($blacklist as $entry) {
      if ($entry['reason'] != $type) {
        continue;
      }
      $row = array();
      // #class property is internally used by
      // theme_mollom_admin_blacklist_form().
      $row['context'] = array(
        '#markup' => check_plain(isset($contexts[$entry['context']]) ? $contexts[$entry['context']] : $entry['context']),
        '#class' => 'mollom-blacklist-context value-' . check_plain($entry['context']),
      );
      $row['match'] = array(
        '#markup' => check_plain($matches[$entry['match']]),
        '#class' => 'mollom-blacklist-match value-' . check_plain($entry['match']),
      );
      $row['value'] = array(
        '#markup' => check_plain($entry['value']),
        '#class' => 'mollom-blacklist-value',
      );
      $row['actions']['delete'] = array(
        '#type' => 'link',
        '#title' => t('delete'),
        '#href' => 'admin/config/content/mollom/blacklist/delete',
        '#options' => array(
          'query' => array('id' => $entry['id']) + drupal_get_destination(),
        ),
      );
      $form['blacklist'][$entry['id']] = $row;
    }
  }

  $form['entry']['context'] = array(
    '#type' => 'select',
    '#title' => t('Context'),
    '#title_display' => 'invisible',
    '#options' => $contexts,
    '#required' => TRUE,
    '#id' => 'mollom-blacklist-filter-context',
  );
  $form['entry']['match'] = array(
    '#type' => 'select',
    '#title' => t('Match'),
    '#title_display' => 'invisible',
    '#options' => $matches,
    '#required' => TRUE,
    '#id' => 'mollom-blacklist-filter-match',
  );
  $form['entry']['value'] = array(
    '#type' => 'textfield',
    '#title' => t('Value'),
    '#title_display' => 'invisible',
    '#size' => 40,
    '#required' => TRUE,
    '#maxlength' => 64,
    '#id' => 'mollom-blacklist-filter-value',
    '#attributes' => array(
      'autocomplete' => 'off',
    ),
  );
  $form['entry']['reason'] = array(
    '#type' => 'value',
    '#value' => $type,
  );
  $form['entry']['actions'] = array(
    '#type' => 'actions',
    '#tree' => FALSE,
  );
  $form['entry']['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Add'),
  );

  return $form;
}

/**
 * Form submit handler to save a text string to the Mollom blacklist.
 */
function mollom_admin_blacklist_form_submit($form, &$form_state) {
  $data = array(
    'value' => $form_state['values']['entry']['value'],
    'context' => $form_state['values']['entry']['context'],
    'match' => $form_state['values']['entry']['match'],
    'reason' => $form_state['values']['entry']['reason'],
  );
  $result = mollom()->saveBlacklistEntry($data);

  $args = array(
    '@value' => $data['value'],
    '@context' => $data['context'],
    '@match' => $data['match'],
    '@reason' => $data['reason'],
  );
  if (!empty($result['id'])) {
    drupal_set_message(t('The entry was added to the blacklist.'));
    mollom_log(array(
      'message' => 'Added @value (@context, @match) to @reason blacklist.',
      'arguments' => $args,
    ));
  }
  else {
    drupal_set_message(t('An error occurred upon trying to add the value to the blacklist.'), 'error');
    mollom_log(array(
      'message' => 'Failed to add @value (@context, @match) to @reason blacklist.',
      'arguments' => $args,
    ), WATCHDOG_ERROR);
  }
}

/**
 * Formats the blacklist form as table to embed the form.
 */
function theme_mollom_admin_blacklist_form($variables) {
  $form = $variables['form'];
  $header = array(
    t('Context'),
    t('Matches'),
    t('Value'),
    '',
  );
  $rows = array();

  $rows[] = array(
    drupal_render($form['entry']['context']),
    drupal_render($form['entry']['match']),
    drupal_render($form['entry']['value']),
    drupal_render($form['entry']['actions']),
  );

  foreach (element_children($form['blacklist']) as $id) {
    $rows[] = array(
      array(
        'data' => drupal_render($form['blacklist'][$id]['context']),
        'class' => $form['blacklist'][$id]['context']['#class'],
      ),
      array(
        'data' => drupal_render($form['blacklist'][$id]['match']),
        'class' => $form['blacklist'][$id]['match']['#class'],
      ),
      array(
        'data' => drupal_render($form['blacklist'][$id]['value']),
        'class' => $form['blacklist'][$id]['value']['#class'],
      ),
      drupal_render($form['blacklist'][$id]['actions']),
    );
  }

  // This table is never empty due to the form.
  $output  = theme('table', array('header' => $header, 'rows' => $rows, 'attributes' => array('id' => 'mollom-blacklist')));
  $output .= drupal_render_children($form);

  drupal_add_js(drupal_get_path('module', 'mollom') . '/mollom.admin.blacklist.js');

  return $output;
}

/**
 * Form builder; Builds the confirmation form for deleting a blacklist item.
 *
 * @ingroup forms
 * @see mollom_admin_blacklist_delete_submit()
 */
function mollom_admin_blacklist_delete($form, &$form_state) {
  $id = $_GET['id'];
  if (empty($id) || !($entry = mollom()->getBlacklistEntry($id))) {
    drupal_not_found();
    return;
  }
  $form['entry'] = array(
    '#type' => 'value',
    '#value' => $entry,
  );

  return confirm_form(
    $form,
    t('Are you sure you want to delete %value from the blacklist?', array('%value' => $entry['value'])),
    'admin/config/content/mollom/blacklist',
    t('This action cannot be undone.'),
    t('Delete'), t('Cancel')
  );
}

/**
 * Form submit handler to delete an entry from the blacklist.
 */
function mollom_admin_blacklist_delete_submit($form, &$form_state) {
  $result = mollom()->deleteBlacklistEntry($form_state['values']['entry']['id']);

  $args = array(
    '@value' => $form_state['values']['entry']['value'],
    '@context' => $form_state['values']['entry']['context'],
    '@reason' => $form_state['values']['entry']['reason'],
  );
  if ($result === TRUE) {
    drupal_set_message(t('The entry was removed from the blacklist.'));
    mollom_log(array(
      'message' => 'Removed @value (@context) from @reason blacklist.',
      'arguments' => $args,
    ));
  }
  else {
    drupal_set_message(t('An error occurred upon trying to remove the item from the blacklist.'), 'error');
    mollom_log(array(
      'message' => 'Failed to remove @value (%context) from @reason blacklist.',
      'arguments' => $args,
    ), WATCHDOG_ERROR);
  }

  $form_state['redirect'] = 'admin/config/content/mollom/blacklist';
}

/**
 * Form builder; Global Mollom settings form.
 *
 * This form does not validate Mollom API keys, since the fallback method still
 * needs to be able to be reconfigured in case Mollom services are down.
 * mollom.verifyKey would invalidate the keys and throw an error; hence,
 * _mollom_fallback() would invoke form_set_error(), effectively preventing this
 * form from submitting.
 *
 * @todo Implement proper form validation now that mollom() no longer triggers
 *   the fallback mode.
 */
function mollom_admin_settings($form, &$form_state) {
  $mollom = mollom();
  $check = empty($_POST);
  $status = mollom_admin_site_status($check);
  if ($check && $status['isVerified'] && !variable_get('mollom_testing_mode', 0)) {
    drupal_set_message(t('Mollom servers verified your keys. The services are operating correctly.'));
  }

  $form['access-keys'] = array(
    '#type' => 'fieldset',
    '#title' => t('Mollom API keys'),
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
  $form['access-keys']['mollom_public_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Public key'),
    '#default_value' => variable_get('mollom_public_key'),
    '#element_validate' => array('mollom_admin_settings_validate_key'),
    '#description' => t('Used to uniquely identify this site.'),
  );
  $form['access-keys']['mollom_private_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Private key'),
    '#default_value' => variable_get('mollom_private_key'),
    '#element_validate' => array('mollom_admin_settings_validate_key'),
    '#description' => t('Used for authentication. Similar to a password, the private key should not be shared with anyone.'),
  );

  $form['mollom_fallback'] = array(
    '#type' => 'radios',
    '#title' => t('When the Mollom service is unavailable'),
    '#default_value' => variable_get('mollom_fallback', MOLLOM_FALLBACK_ACCEPT),
    '#options' => array(
      MOLLOM_FALLBACK_ACCEPT => t('Accept all form submissions'),
      MOLLOM_FALLBACK_BLOCK => t('Block all form submissions'),
    ),
    '#description' => t('Mollom offers a <a href="@pricing-url">high-availability</a> infrastructure for users on paid plans to reduce potential downtime.', array(
      '@pricing-url' => 'https://mollom.com/pricing',
    )),
  );

  $options = _mollom_supported_languages();
  $default_languages = array();
  if (isset($status['expectedLanguages'])) {
    $default_languages = $status['expectedLanguages'];
  }
  else {
    $default_languages = $mollom->loadConfiguration('expectedLanguages');
  }
  $path = drupal_get_path('module', 'mollom');
  $form[$mollom->configuration_map['expectedLanguages']] = array(
    '#type' => 'select',
    '#title' => t('Expected languages'),
    '#options' => $options,
    '#multiple' => TRUE,
    '#size' => 6,
    '#default_value' => $default_languages,
    '#description' => t('Restricts all posts to selected languages. Used by text analysis only. Leave empty if users may post in other languages.'),
    // Ensure that selected languages are apparent for site administrators and
    // not potentially hidden in a large select widget.
    '#attributes' => array(
      // @see form_process_select()
      'data-placeholder' => t('- Empty -'),
      'id' => $mollom->configuration_map['expectedLanguages'],
    ),
    '#attached' => array(
      'css' => array(
        $path . '/assets/chosen/chosen.min.css',
      ),
      'js' => array(
        $path . '/assets/chosen/chosen.jquery.min.js',
        $path . '/mollom.admin.js',
      ),
    ),
  );

  $form['mollom_privacy_link'] = array(
    '#type' => 'checkbox',
    '#title' => t("Show a link to Mollom's privacy policy"),
    '#return_value' => 1,
    '#default_value' => variable_get('mollom_privacy_link', 1),
    '#description' => t('Only applies to forms protected with text analysis. When disabling this option, you should inform visitors about the privacy of their data through other means.'),
  );

  $form['mollom_testing_mode'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable testing mode'),
    '#default_value' => variable_get('mollom_testing_mode', 0),
    '#description' => t('Submitting "ham", "unsure", or "spam" triggers the corresponding behavior; image CAPTCHAs only respond to "correct" and audio CAPTCHAs only respond to "demo". Do not enable this option if this site is publicly accessible.'),
  );

  $form['mollom_advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced configuration'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  // Lower severity numbers indicate a high severity level.
  $min_severity = variable_get('mollom_log_minimum_severity', WATCHDOG_WARNING);
  $form['mollom_advanced']['mollom_log_minimum_severity'] = array(
    '#type' => 'radios',
    '#title' => t('Mollom logging level warning'),
    '#options' => array(
      WATCHDOG_WARNING => t('Only log warnings and errors'),
      WATCHDOG_DEBUG => t('Log all Mollom messages'),
    ),
    '#default_value' => $min_severity <= WATCHDOG_WARNING ? WATCHDOG_WARNING : WATCHDOG_DEBUG,
  );
  $form['mollom_advanced']['mollom_audio_captcha_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable audio CAPTCHAs.'),
    '#description' => t('Allows users to switch to an audio verification using the <a href="!faq-url">NATO alphabet</a>.  This may not be appropriate for non-English language sites.', array(
      '!faq-url' => 'https://mollom.com/faq/mollom-audible-captcha-language',
    )),
    '#default_value' => variable_get('mollom_audio_captcha_enabled', 1),
  );
  $form['mollom_advanced']['mollom_connection_timeout'] = array(
    '#type' => 'textfield',
    '#title' => t('Time-out when attempting to contact Mollom servers.'),
    '#description' => t('This is the length of time that a call to Mollom will wait before timing out.'),
    '#default_value' => variable_get('mollom_connection_timeout', 3),
    '#size' => 5,
    '#field_suffix' => t('seconds'),
    '#required' => TRUE,
  );
  $form['mollom_advanced']['mollom_fba_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable form behavior analysis (beta).'),
    '#description' => t('This will place a small tracking image from Mollom on each form protected by textural analysis to help Mollom determine if the form is filled out by a bot.  <a href="!fba-url">Learn more</a>.', array(
      '!fba-url' => 'https://mollom.com/faq/form-behavior-analysis',
    )),
    '#default_value' => variable_get('mollom_fba_enabled', 0),
  );
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
  $form = system_settings_form($form);
  $form['#submit'][] = 'mollom_admin_settings_submit';
  return $form;
}

/**
 * Element validation handler for API key text fields.
 */
function mollom_admin_settings_validate_key($element, &$form_state) {
  if ($element['#value'] !== '') {
    // Remove any leading/trailing white-space and override submitted value.
    $element['#value'] = trim($element['#value']);
    form_set_value($element, $element['#value'], $form_state);
    // Verify the key has a length of 32 characters.
    if (drupal_strlen($element['#value']) != 32) {
      form_error($element, t('!title must be 32 characters. Ensure you copied the key correctly.', array(
        '!title' => $element['#title'],
      )));
    }
  }
}

/**
 * Form submission handler for global settings form.
 */
function mollom_admin_settings_prepare_submit($form, &$form_state) {
  // If the minimum log severity checkbox was disabled (no input), convert
  // 0 into WATCHDOG_DEBUG.
  if (!isset($form_state['input']['mollom_log_minimum_severity'])) {
    $form_state['values']['mollom_log_minimum_severity'] = WATCHDOG_DEBUG;
  }
}

/**
 * Form submission handler for global settings form.
 */
function mollom_admin_settings_submit($form, &$form_state) {
  // Update Mollom site record with local configuration.
  _mollom_status(TRUE, TRUE);
}

/**
 * Menu callback; Displays the administrative reports page.
 */
function mollom_reports_page($form, &$form_state) {
  $embed_attributes = array(
    'src' => 'https://mollom.com/statistics.swf?key=' . check_plain(variable_get('mollom_public_key', '')),
    'quality' => 'high',
    'width' => '100%',
    'height' => '430',
    'name' => 'Mollom',
    'align' => 'middle',
    'play' => 'true',
    'loop' => 'false',
    'allowScriptAccess' => 'sameDomain',
    'type' => 'application/x-shockwave-flash',
    'pluginspage' => 'http://www.adobe.com/go/getflashplayer',
    'wmode' => 'transparent',
  );
  $form['chart'] = array(
    '#type' => 'item',
    '#title' => t('Statistics'),
    '#markup' => '<embed' . drupal_attributes($embed_attributes) . '></embed>',
  );

  return $form;
}

