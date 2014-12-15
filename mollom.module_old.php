<?php

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_comment_admin_overview_alter(&$form, &$form_state) {
  module_load_include('inc', 'mollom', 'mollom.flag');
  _mollom_table_add_flag_counts('comment', $form['comments']['#header'], $form['comments']['#options']);
}

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_node_form_alter(&$form, &$form_state, $form_id) {
  module_load_include('inc', 'mollom', 'mollom.flag');
  mollom_flag_node_form_alter($form, $form_state, $form_id);
}

/**
 * Implements hook_hook_info().
 */
function mollom_hook_info() {
  $hooks = array(
    'mollom_form_list',
    'mollom_form_list_alter',
    'mollom_form_info',
    'mollom_form_info_alter',
    'mollom_form_insert',
    'mollom_form_update',
    'mollom_form_delete',
    'mollom_data_insert',
    'mollom_data_update',
    'mollom_data_delete',
  );
  $hooks = array_fill_keys($hooks, array(
    'group' => 'mollom',
  ));
  return $hooks;
}



/**
 * Implements hook_help().
 */
/*function mollom_help($path, $arg) {
  $output = '';

  if ($path == 'admin/config/content/mollom') {
    $output .= '<p>';
    $output .= t('All listed forms below are protected by Mollom, unless users are able to <a href="@permissions-url">bypass Mollom\'s protection</a>.', array(
      '@permissions-url' => url('admin/people/permissions', array('fragment' => 'module-mollom')),
    ));
    $output .= ' ';
    $output .= t('You can <a href="@add-form-url">add a form</a> to protect, configure already protected forms, or remove the protection.', array(
      '@add-form-url' => url('admin/config/content/mollom/add'),
    ));
    $output .= '</p>';
    return $output;
  }

  if ($path == 'admin/config/content/mollom/blacklist') {
    $output = '<p>';
    $output .= t('Mollom automatically blocks unwanted content and learns from all participating sites to improve its filters. On top of automatic filtering, you can define a custom blacklist.');
    $output .= '</p>';
    $output .= '<p>';
    $output .= t('Use an "exact" match for short, single words that could be contained within another word.');
    $output .= '</p>';
    return $output;
  }

  if ($path == 'admin/help#mollom') {
    $output = '<p>';
    $output .= t('Allowing users to react, participate and contribute while still keeping your site\'s content under control can be a huge challenge. <a href="@mollom-website">Mollom</a> is a web service that helps you identify content quality and, most importantly, helps you stop spam. When content moderation becomes easier, you have more time and energy to interact with your site visitors and community. For more information, see <a href="@mollom-works">How Mollom Works</a> and the <a href="@mollom-faq">Mollom FAQ</a>.', array(
      '@mollom-website' => 'https://mollom.com',
      '@mollom-works' => 'https://mollom.com/how-mollom-works',
      '@mollom-faq' => 'https://mollom.com/faq',
    ));
    $output .= '</p><p>';
    $output .= t('Mollom can protect forms your site from unwanted posts. Each form can be set to one of the following options:');
    $output .= '</p><ul>';
    $output .= '<li><p><strong>';
    $output .= t('Text analysis with CAPTCHA backup');
    $output .= '</strong></p><p>';
    $output .= t('Mollom analyzes the data submitted on the form and presents a CAPTCHA challenge if necessary. This option is strongly recommended, as it takes full advantage of the Mollom service to categorize posts into ham (not spam) and spam.');
    $output .= '</p></li>';
    $output .= '<li><p><strong>';
    $output .= t('CAPTCHA only');
    $output .= '</strong></p><p>';
    $output .= t('The form data is not sent to Mollom for analysis, and a remotely-hosted CAPTCHA challenge is always presented. This option is useful when you want to send less data to the Mollom network. Note, however, that forms displayed with a CAPTCHA are never cached, so always displaying a CAPTCHA challenge may reduce performance.');
    $output .= '</p></li>';
    $output .= '</ul><p>';
    $output .= t('Data is processed and stored as explained in the <a href="@mollom-privacy">Mollom Web Service Privacy Policy</a>. It is your responsibility to provide necessary notices and obtain the appropriate consent regarding Mollom\'s use of submitted data.', array(
      '@mollom-privacy' => 'https://mollom.com/web-service-privacy-policy',
      '@mollom-works' => 'https://mollom.com/how-mollom-works',
      '@mollom-faq' => 'https://mollom.com/faq',
    ));
    $output .= '</p>';
    $output .= '<p>';
    $output .= t('If Mollom may not block a spam post for any reason, you can help to train and improve its filters by choosing the appropriate feedback option when deleting the post on your site.');
    $output .= '</p>';
    $output .= '<h3>' . t('Mollom blacklist') . '</h3>';
    $output .= '<p>';
    $output .= t("Mollom's filters are shared and trained globally over all participating sites. Due to this, unwanted content might still be accepted on your site, even after sending feedback to Mollom. By using the site-specific blacklist, the filters can be customized to your specific needs. Each entry specifies a reason for why it has been blacklisted, which further helps in improving Mollom's automated filtering.");
    $output .= '</p>';
    $output .= '<p>';
    $output .= t("All blacklist entries are applied to a context: the entire submitted post, or only links in the post. When limiting the context to links, both the link URL and the link text is taken into account.");
    $output .= '</p>';
    $output .= '<p>';
    $output .= t('Each blacklist entry defines how it matches:');
    $output .= '</p>';
    $output .= '<ul>';
    $output .= '<li>';
    $output .= t('Use "contains" matching to find a term within any other string.');
    $output .= '</li><li>';
    $output .= t('Use "exact" matching for terms made up of short, single words that could be contained within a larger permissible word.');
    $output .= '</li>';
    $output .= '</ul>';
    $output .= '<p>';
    $output .= t("If a blacklist entry contains multiple words, various combinations will be matched. For example, when adding \"<code>replica&nbsp;watches</code>\" limited to links, the following links will be blocked:");
    $output .= '</p>';
    $output .= '<ul>
<li><code>http://replica-watches.com</code></li>
<li><code>http://replica-watches.com/some/path</code></li>
<li><code>http://replicawatches.net</code></li>
<li><code>http://example.com/replica/watches</code></li>
<li><code>&lt;a href="http://example.com"&gt;replica watches&lt;/a&gt;</code></li>
</ul>';
    $output .= '<p>';
    $output .= t("The blacklist is optional. There is no whitelist, i.e., if a blacklist entry is matched in a post, it overrides any other filter result and the post will not be accepted. Blacklisting potentially ambiguous words should be avoided.");
    $output .= '</p>';
    return $output;
  }
}*/

/**
 * Implements hook_exit().
 */
/*function mollom_exit() {
  // Write log messages.
  mollom_log_write();
}*/

/**
 * Implements hook_menu().
 */
/*function mollom_menu() {
  $items['mollom/report/%/%'] = array(
    'title' => 'Report to Mollom',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('mollom_report_form', 2, 3),
    'access callback' => 'mollom_report_access',
    'access arguments' => array(2, 3),
    'file' => 'mollom.pages.inc',
    'type' => MENU_CALLBACK,
  );
  $items['mollom/moderate/%mollom_content/%'] = array(
    'page callback' => 'mollom_moderate',
    'page arguments' => array(2, 3),
    'access callback' => 'mollom_moderate_access',
    'access arguments' => array(2, 3),
    'type' => MENU_CALLBACK,
  );

  $items['admin/config/content/mollom/unprotect/%mollom_form'] = array(
    'title' => 'Unprotect form',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('mollom_admin_unprotect_form', 5),
    'access arguments' => array('administer mollom'),
    'file' => 'mollom.admin.inc',
  );

  // AJAX callback to request new CAPTCHA.
  $items['mollom/captcha/%/%'] = array(
    'page callback' => 'mollom_captcha_js',
    'page arguments' => array(2, 3),
    'access callback' => '_mollom_access',
    'file' => 'mollom.pages.inc',
    'type' => MENU_CALLBACK,
  );

  $items['mollom/fba'] = array(
    'page callback' => 'mollom_fba_js',
    'access callback' => '_mollom_access',
    'type' => MENU_CALLBACK,
  );

  // Report as inappropriate.
  $items['mollom/flag/%/%/%/%'] = array(
    'page callback' => '_mollom_flag',
    'page arguments' => array(2, 3, 4),
    'access callback' => '_mollom_flag_access',
    'access arguments' => array(3, 4),
    'type' => MENU_CALLBACK,
    'file' => 'mollom.flag.inc',
  );

  return $items;
}*/

/**
 * Implements hook_menu_local_tasks_alter().
 */
/*function mollom_menu_local_tasks_alter(&$data, $router_item, $root_path) {
  if ($router_item['tab_root'] === 'admin/config/content/mollom' && user_access('access mollom statistics')) {
    // Inject link to 'Statistics' before the last 'Settings' tab.
    // The render array supports the regular #weight, but D7 core does not
    // assign a #weight property for local tasks derived from the menu router.
    // This causes element_children() to re-sort the existing local tasks
    // (without weights) and they appear in an arbitrary order.
    // @see http://drupal.org/node/1864066
    array_splice($data['tabs'][0]['output'], -1, 0, array(array(
      '#theme' => 'menu_local_task',
      '#link' => array(
        'title' => t('Statistics'),
        'href' => 'admin/reports/mollom',
        'localized_options' => array('html' => FALSE),
      ),
    )));
  }
}*/

/**
 * Menu access callback; Checks if the module is operational.
 *
 * @param $permission
 *   An optional permission string to check with user_access().
 *
 * @return
 *   TRUE if the module has been configured and user_access() has been checked,
 *   FALSE otherwise.
 */
/*function _mollom_access($permission = FALSE) {
  $status = _mollom_status();
  return $status['isVerified'] && (!$permission || user_access($permission));
}*/

/**
 * Menu access callback; Determine access to report to Mollom.
 *
 * There are two special $entity types "mollom_content" and "mollom_captcha",
 * which do not map to actual entity types in the Drupal system. They are
 * primarily used for mails, messages, and posts, which pertain to forms
 * protected by Mollom that do no result in stored entities after submission.
 * For example, Contact module's contact form. They can be reported by anyone
 * having the link. $id is expected to be either a {mollom}.content_id or
 * {mollom}.captcha_id respectively.
 *
 * @see mollom_mail_add_report_link()
 *
 * @param $entity
 *   The entity type of the data to report.
 * @param $id
 *   The entity id of the data to report.
 *
 * @todo Revamp this based on new {mollom}.form_id info.
 */
function mollom_report_access($entity, $id) {
  // The special entity types can be reported by anyone.
  if ($entity == 'mollom_content' || $entity == 'mollom_captcha') {
    return !empty($id) ? TRUE : FALSE;
  }
  // Retrieve information about all protectable forms. We use the first valid
  // definition, because we assume that multiple form definitions just denote
  // variations of the same entity (e.g. node content types).
  foreach (mollom_form_list() as $form_id => $info) {
    if (!isset($info['entity']) || $info['entity'] != $entity) {
      continue;
    }
    // If there is a 'report access callback', invoke it.
    if (isset($info['report access callback']) && function_exists($info['report access callback'])) {
      $function = $info['report access callback'];
      return $function($entity, $id);
    }
    // Otherwise, if there is a 'report access' list of permissions, iterate
    // over them.
    if (isset($info['report access'])) {
      foreach ($info['report access'] as $permission) {
        if (user_access($permission)) {
          return TRUE;
        }
      }
    }
  }
  // If we end up here, then the current user is not permitted to report this
  // content.
  return FALSE;
}

/**
 * Implements hook_modules_installed().
 */
function mollom_modules_installed($modules) {
  drupal_static_reset('mollom_get_form_info');
}

/**
 * Implements hook_modules_uninstalled().
 */
function mollom_modules_uninstalled($modules) {
  db_delete('mollom_form')->condition('module', $modules)->execute();
}

/**
 * Implements hook_cron().
 */
function mollom_cron() {
  // Mollom session data auto-expires after 6 months.
  $expired = REQUEST_TIME - 86400 * 30 * 6;
  db_delete('mollom')
    ->condition('changed', $expired, '<')
    ->execute();
}

/**
 * Load a Mollom data record by contentId.
 *
 * @param $contentId
 *   The contentId to retrieve data for.
 */
function mollom_content_load($contentId) {
  $data = mollom_db_query_range('SELECT * FROM {mollom} WHERE content_id = :contentId', 0, 1, array(':contentId' => $contentId))->fetchObject();
  return _mollom_convert_db_names($data);
}

/**
 * Load a Mollom data record from the database.
 *
 * @param $entity
 *   The entity type to retrieve data for.
 * @param $id
 *   The entity id to retrieve data for.
 */
function mollom_data_load($entity, $id) {
  $data = mollom_db_query_range('SELECT * FROM {mollom} WHERE entity = :entity AND id = :id', 0, 1, array(':entity' => $entity, ':id' => $id))->fetchObject();
  return _mollom_convert_db_names($data);
}

/**
 * Loads the Mollom data records from the database for a specific entity type.
 *
 * @param $entity
 *   The entity type to retrieve data for.
 *
 * @return array
 *   The matching Mollom data as an array keyed by entity id.
 */
function mollom_entity_type_load($type) {
  $data = mollom_db_query('SELECT * FROM {mollom} WHERE entity = :entity', array(':entity' => $type))->fetchAllAssoc('id');
  return _mollom_convert_db_names($data);
}

/**
 * Executes database queries with natural letter casing.
 *
 * Drupal core enforces lowercase column names in PDO statements for no
 * particular reason.
 *
 * @see http://drupal.org/node/1171866
 */
function mollom_db_query($query, array $args = array(), array $options = array()) {
  if (empty($options['target'])) {
    $options['target'] = 'default';
  }

  $connection = Database::getConnection($options['target']);
  // Backup PDO::ATTR_CASE to restore it afterwards, sticks on the connection.
  $backup = $connection->getAttribute(PDO::ATTR_CASE);

  $connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
  $result = $connection->query($query, $args, $options);
  $connection->setAttribute(PDO::ATTR_CASE, $backup);

  return $result;
}

/**
 * Fetches a database record with natural letter casing.
 *
 * Drupal core enforces lowercase column names in PDO statements for no
 * particular reason.
 *
 * @see http://drupal.org/node/1171866
 */
function mollom_db_query_range($query, $from, $count, array $args = array(), array $options = array()) {
  if (empty($options['target'])) {
    $options['target'] = 'default';
  }

  $connection = Database::getConnection($options['target']);
  // Backup PDO::ATTR_CASE to restore it afterwards, sticks on the connection.
  $backup = $connection->getAttribute(PDO::ATTR_CASE);

  $connection->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
  $result = $connection->queryRange($query, $from, $count, $args, $options);
  $connection->setAttribute(PDO::ATTR_CASE, $backup);

  return $result;
}

/**
 * Updates stored Mollom session data to mark a bad post as moderated.
 *
 * @param $entity
 *   The entity type of the moderated post.
 * @param $id
 *   The entity id of the moderated post.
 */
function mollom_data_moderate($entity, $id) {
  $data = mollom_data_load($entity, $id);
  // Nothing to do, if no data exists.
  if (!$data) {
    return;
  }

  // Report the session to Mollom.
  _mollom_send_feedback($data, 'approve', 'moderate', 'mollom_data_moderate');

  // Mark the session data as moderated.
  $data->moderate = 0;
  mollom_data_save($data);
}

/**
 * Deletes a Mollom session data record from the database.
 *
 * @param $entity
 *   The entity type to delete data for.
 * @param $id
 *   The entity id to delete data for.
 */
function mollom_data_delete($entity, $id) {
  return mollom_data_delete_multiple($entity, array($id));
}

/**
 * Deletes multiple Mollom session data records from the database.
 *
 * @param $entity
 *   The entity type to delete data for.
 * @param $ids
 *   An array of entity ids to delete data for.
 */
function mollom_data_delete_multiple($entity, array $ids) {
  foreach ($ids as $id) {
    $data = mollom_data_load($entity, $id);
    if ($data) {
      module_invoke_all('mollom_data_delete', $data);
    }
  }
  return db_delete('mollom')->condition('entity', $entity)->condition('id', $ids)->execute();
}

/**
 * Helper function to add Mollom feedback options to confirmation forms.
 */
function mollom_data_delete_form_alter(&$form, &$form_state) {
  if (!isset($form['description']['#weight'])) {
    $form['description']['#weight'] = 90;
  }
  $form['mollom'] = array(
    '#tree' => TRUE,
    '#weight' => 80,
  );
  $form['mollom']['feedback'] = array(
    '#type' => 'radios',
    '#title' => t('Report as…'),
    '#options' => array(
      'spam' => t('Spam, unsolicited advertising'),
      'profanity' => t('Profane, obscene, violent'),
      'quality' => t('Low-quality'),
      'unwanted' => t('Unwanted, taunting, off-topic'),
      '' => t('Do not report'),
    ),
    '#default_value' => 'spam',
    '#description' => t('Sending feedback to <a href="@mollom-url">Mollom</a> improves the automated moderation of new submissions.', array('@mollom-url' => 'https://mollom.com')),
  );
}

/**
 * Send feedback to Mollom and delete Mollom data.
 *
 * @see mollom_form_alter()
 */
function mollom_data_delete_form_submit($form, &$form_state) {
  $forms = mollom_form_cache();
  $mollom_form = mollom_form_load($forms['delete'][$form_state['values']['form_id']]);
  $data = mollom_form_get_values($form_state, $mollom_form['enabled_fields'], $mollom_form['mapping']);

  $entity = $mollom_form['entity'];
  $id = $data['postId'];

  if (!empty($form_state['values']['mollom']['feedback'])) {
    if (mollom_data_report($entity, $id, $form_state['values']['mollom']['feedback'], 'moderate', 'mollom_data_delete_form_submit')) {
      drupal_set_message(t('The content was successfully reported as inappropriate.'));
    }
  }

  // Remove Mollom session data.
  mollom_data_delete($entity, $id);
}

/**
 * Sends feedback for a Mollom session data record.
 *
 * @param $entity
 *   The entity type to send feedback for.
 * @param $id
 *   The entity id to send feedback for.
 * @param $feedback
 *   The feedback reason for reporting content.
 * @param $type
 *   The type of feedback, one of 'moderate' or 'flag'.
 * @param $source
 *   An optional single word string identifier for the user interface source.
 *   This is tracked along with the feedback to provide a more complete picture
 *   of how feedback is used and submitted on the site.
 */
function mollom_data_report($entity, $id, $feedback, $type = 'moderate', $source = 'mollom_data_report') {
  return mollom_data_report_multiple($entity, array($id), $feedback, $type, $source);
}

/**
 * Sends feedback for multiple Mollom session data records.
 *
 * @param $entity
 *   The entity type to send feedback for.
 * @param $ids
 *   An array of entity ids to send feedback for.
 * @param $feedback
 *   The feedback reason for reporting content.
 * @param $type
 *   The type of feedback, one of 'moderate' or 'flag'.
 * @param $source
 *   An optional single word string identifier for the user interface source.
 *   This is tracked along with the feedback to provide a more complete picture
 *   of how feedback is used and submitted on the site.
 */
function mollom_data_report_multiple($entity, array $ids, $feedback, $type = 'moderate', $source = 'mollom_data_report_multiple') {
  $return = TRUE;
  foreach ($ids as $id) {
    // Load the Mollom session data.
    $data = mollom_data_load($entity, $id);
    // Send feedback, if we have session data.
    if (!empty($data->contentId) || !empty($data->captchaId)) {
      $result = _mollom_send_feedback($data, $feedback, $type, $source);
      $return = $return && $result;
    }
  }
  return $return;
}

/**
 * Saves a Mollom form configuration.
 */
function mollom_form_save(&$mollom_form) {
  $exists = db_query_range('SELECT 1 FROM {mollom_form} WHERE form_id = :form_id', 0, 1, array(':form_id' => $mollom_form['form_id']))->fetchField();
  $status = drupal_write_record('mollom_form', $mollom_form, ($exists ? 'form_id' : array()));

  // Allow modules to react on saved form configurations.
  if ($status === SAVED_NEW) {
    module_invoke_all('mollom_form_insert', $mollom_form);
  }
  else {
    module_invoke_all('mollom_form_update', $mollom_form);
  }

  // Flush cached Mollom forms and the Mollom form mapping cache.
  mollom_form_cache(TRUE);

  return $status;
}

/**
 * Deletes a Mollom form configuration.
 */
function mollom_form_delete($form_id) {
  $mollom_form = mollom_form_load($form_id);

  db_delete('mollom_form')
    ->condition('form_id', $form_id)
    ->execute();

  // Allow modules to react on saved form configurations.
  module_invoke_all('mollom_form_delete', $mollom_form);

  // Flush cached Mollom forms and the Mollom form mapping cache.
  mollom_form_cache(TRUE);
}


/**
 * Implements hook_theme().
 */
function mollom_theme() {
  $base_path = base_path() . drupal_get_path('module', 'mollom');
  return array(
    'mollom_admin_blacklist_form' => array(
      'render element' => 'form',
      'file' => 'mollom.admin.inc',
    ),
    'mollom_captcha_audio' => array(
      'variables' => array(
        'captcha_url' => NULL,
        'flash_fallback_player' => $base_path . '/mollom-captcha-player.swf',
      ),
      'template' => 'mollom.captcha.audio',
    ),
    'mollom_captcha_image' => array(
      'variables' => array(
        'captcha_url' => NULL,
        'audio_enabled' => TRUE,
      ),
      'template' => 'mollom.captcha.image',
    ),
  );
}

/**
 * Implements hook_library().
 */
function mollom_library() {
  $libraries['flag'] = array(
    'title' => 'Flag as Inappropriate',
    'version' => '1.0',
    'js' => array(
      drupal_get_path('module', 'mollom') . '/mollom.flag.js' => array(),
    ),
    'css' => array(
      drupal_get_path('module', 'mollom') . '/mollom.flag.position.css' => array(),
      drupal_get_path('module', 'mollom') . '/mollom.flag.css' => array(),
    ),
    'dependencies' => array(
      array('system', 'drupal.ajax'),
    )
  );
  return $libraries;
}

/**
 * Implements hook_entity_view().
 */
function mollom_entity_view($entity, $type, $view_mode, $langcode) {
  module_load_include('inc', 'mollom', 'mollom.flag');
  mollom_flag_entity_view($entity, $type, $view_mode, $langcode);
}

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_comment_form_alter(&$form, &$form_state, $form_id) {
  module_load_include('inc', 'mollom', 'mollom.flag');
  mollom_flag_comment_form_alter($form, $form_state, $form_id);
}





/**
 * Fetch the site's Mollom statistics from the API.
 *
 * @param $refresh
 *   A boolean if TRUE, will force the statistics to be re-fetched and stored
 *   in the cache.
 *
 * @return array
 *   An array of statistics.
 */
function mollom_get_statistics($refresh = FALSE) {
  $statistics = FALSE;
  $cache = cache_get('mollom:statistics');

  // Only fetch if $refresh is TRUE, the cache is empty, or the cache is expired.
  if ($refresh || !$cache || REQUEST_TIME >= $cache->expire) {
    $status = _mollom_status();
    if ($status['isVerified']) {
      $statistics = drupal_map_assoc(array(
        'total_days',
        'total_accepted',
        'total_rejected',
        'yesterday_accepted',
        'yesterday_rejected',
        'today_accepted',
        'today_rejected',
      ));

      foreach ($statistics as $statistic) {
        $result = mollom()->getStatistics(array('type' => $statistic));
        if ($result === Mollom::NETWORK_ERROR || $result === Mollom::AUTH_ERROR) {
          // If there was an error, stop fetching statistics and store FALSE
          // in the cache. This will help prevent from making unnecessary
          // requests to Mollom if the service is down or the server cannot
          // connect to the Mollom service.
          $statistics = FALSE;
          break;
        }
        else {
          $statistics[$statistic] = $result;
        }
      }
    }

    // Cache the statistics and set them to expire in one hour.
    cache_set('mollom:statistics', $statistics, 'cache', REQUEST_TIME + 3600);
  }
  else {
    $statistics = $cache->data;
  }

  return $statistics;
}

/**
 * Implements hook_field_extra_fields().
 *
 * Allow users to re-order Mollom form additions through Field UI.
 */
function mollom_field_extra_fields() {
  $extras = array();
  $forms = array_flip(db_query('SELECT form_id FROM {mollom_form}')->fetchCol());
  foreach (mollom_form_list() as $form_id => $info) {
    // @todo Technically, an 'entity' does not need to be a Entity/Field API
    //   kind of entity. Ideally of course, developers should use fieldable
    //   entities, but contributed/custom code may not. It is not clear whether
    //   registering extra fields for non-existing entities/bundles can break
    //   anything, so leaving it this way for now.
    if (isset($info['entity']) && isset($forms[$form_id])) {
      // If the entity type does not implement bundles, then entity_get_info()
      // assumes a single bundle named after the entity.
      $entity_type = $info['entity'];
      $bundle = (isset($info['bundle']) ? $info['bundle'] : $entity_type);

      $extras[$entity_type][$bundle]['form']['mollom'] = array(
        'label' => t('Mollom'),
        'description' => t('Mollom CAPTCHA or privacy policy link'),
        'weight' => 99,
      );
    }
  }
  return $extras;
}

/**
 * Implements hook_mail_alter().
 *
 * Adds a "report as inappropriate" link to e-mails sent after Mollom-protected
 * form submissions.
 *
 * @see mollom_mail_add_report_link()
 *
 * @todo With mollom_entity_insert(), $message['params'] might contain an array
 *   key that has a ::$mollom property holding the Mollom session data,
 *   potentially eliminating the need for $GLOBALS['mollom'].
 */
function mollom_mail_alter(&$message) {
  // Attaches the Mollom report link to any mails with IDs specified from the
  // submitted form's hook_mollom_form_info(). This should ensure that the
  // report link is added to mails sent by actual users and not any mails sent
  // by Drupal since they should never be reported as spam.
  if (!empty($GLOBALS['mollom']['mail ids']) && in_array($message['id'], $GLOBALS['mollom']['mail ids'])) {
    mollom_mail_add_report_link($message, $GLOBALS['mollom']);
  }
}

/**
 * Add the 'Report as inappropriate' link to an e-mail message.
 *
 * @param array $message
 *   The message to alter.
 * @param array $mollom
 *   The Mollom state for the mail; typically $form_state['mollom'], as set up
 *   by mollom_process_mollom().
 *
 * @see mollom_mail_alter()
 */
function mollom_mail_add_report_link(array &$message, array $mollom) {
  if (!empty($mollom['response']['content']['id']) || !empty($mollom['response']['captcha']['id'])) {
    // Check whether an entity was stored with the submission.
    $data = FALSE;
    if (!empty($mollom['response']['content']['id'])) {
      $data = mollom_content_load($mollom['response']['content']['id']);
    }
    elseif (!empty($mollom['response']['captcha']['id'])) {
      $db_data = mollom_db_query_range('SELECT * FROM {mollom} WHERE captcha_id = :captchaId', 0, 1, array(':captchaId' => $mollom['response']['captcha']['id']))->fetchObject();
      $data = _mollom_convert_db_names($db_data);
    }
    if (!$data) {
      // @todo Mollom session data should have been saved earlier already;
      //   eliminate this.
      $data = (object) $mollom['response'];
      if (!empty($mollom['response']['content']['id'])) {
        $data->entity = 'mollom_content';
        $data->id = $data->content['id'];
        $data->contentId = $data->content['id'];
      }
      else {
        $data->entity = 'mollom_captcha';
        $data->id = $data->captcha['id'];
        $data->captchaId = $data->captcha['id'];
      }
      $data->form_id = $mollom['form_id'];
      mollom_data_save($data);
    }
    // Determine report URI.
    $mollom_form = mollom_form_load($data->form_id);
    if (isset($mollom_form['report path'])) {
      $path = strtr($mollom_form['report path'], array(
        '%id' => $data->id,
      ));
    }
    else {
      $path = "mollom/report/{$data->entity}/{$data->id}";
    }
    $report_link = t('Report as inappropriate: @link', array(
      '@link' => url($path, array('absolute' => TRUE)),
    ));
    $message['body'][] = $report_link;
  }
}

/**
 * Implements hook_entity_insert().
 */
function mollom_entity_insert($entity, $type) {
  /*list($id) = entity_extract_ids($type, $entity);
  if (!empty($entity->mollom) && !empty($id)) {
    $entity->mollom['id'] = $id;
    $data = (object) $entity->mollom;
    mollom_data_save($data);
  }*/
}

/**
 * Implements hook_entity_update().
 */
function mollom_entity_update($entity, $type) {
  // A user account's status transitions from 0 to 1 upon first login; do not
  // mark the account as moderated in that case.
  if ($type == 'user' && $entity->uid == $GLOBALS['user']->uid) {
    return;
  }
  // If an existing entity is published and we have session data stored for it,
  // mark the data as moderated.
  $update = FALSE;
  // If the entity update function provides the original entity, only mark the
  // data as moderated when the entity's status transitioned to published.
  if (isset($entity->original->status)) {
    if (empty($entity->original->status) && !empty($entity->status)) {
      $update = TRUE;
    }
  }
  // If there is no original entity to compare against, check for the current
  // status only.
  elseif (!empty($entity->status)) {
    $update = TRUE;
  }
  if ($update) {
    //list($id) = entity_extract_ids($type, $entity);
    //mollom_data_moderate($type, $id);
  }
}

/**
 * Implements hook_entity_delete().
 */
function mollom_entity_delete($entity, $type) {
  /*list($id) = entity_extract_ids($type, $entity);
  mollom_data_delete($type, $id);*/
}

/**
 * @name mollom_moderation Mollom Moderation integration.
 * @{
 */

/**
 * Implements hook_mollom_data_insert().
 */
function mollom_mollom_data_insert($data) {
  // Only content can be updated.
  if (empty($data->contentId)) {
    return;
  }

  // Indicate that this content session is complete.
  $params['id'] = $data->contentId;
  $params['finalized'] = 1;

  // Mark the content as stored for moderation-enabled content.
  $mollom_form = mollom_form_load($data->form_id);
  if (!empty($mollom_form['moderation'])) {
    $params['stored'] = 1;
  }

  // Get additional information for submissions that result in a locally stored
  // entity.
  if ($data->entity != 'mollom_content' && $data->entity != 'mollom_captcha') {
    // Add the URL of the posted content itself.
    $entity_info = entity_get_info();
    if (isset($entity_info[$data->entity])) {
      $entity = entity_load($data->entity, array($data->id));
      $entity = (isset($entity[$data->id]) ? $entity[$data->id] : FALSE);
    }
    if (!empty($entity)) {
      $options = entity_uri($data->entity, $entity);
      $options['absolute'] = TRUE;
      $params['url'] = url($options['path'], $options);

      // Add the title and URL of the parent content/context of the post, if any.
      // @todo Figure out how to do this in a generic way.
      if ($data->entity == 'comment') {
        $node = node_load($entity->nid);
        $options = entity_uri('node', $node);
        $options['absolute'] = TRUE;
        $params['contextUrl'] = url($options['path'], $options);
        $params['contextTitle'] = entity_label('node', $node);
      }
      // Associate the new user ID for newly registered user accounts.
      elseif ($data->entity == 'user') {
        $params['authorId'] = $data->id;
      }
    }
  }

  $result = mollom()->checkContent($params);
}

/**
 * Implements hook_mollom_data_delete().
 */
function mollom_mollom_data_delete($data) {
  // Only content can be deleted.
  if (empty($data->contentId)) {
    return;
  }
  // Exclude data for form submissions not resulting in a locally stored entity.
  // These usually map to mails and similar things, but not in content that can
  // be moderated.
  if ($data->entity == 'mollom_content' || $data->entity == 'mollom_captcha') {
    return;
  }
  // Skip forms for which Mollom moderation integration is not enabled.
  $mollom_form = mollom_form_load($data->form_id);
  if (empty($mollom_form['moderation'])) {
    return;
  }

  // Mark the content as discarded (not stored).
  $params['id'] = $data->contentId;
  $params['stored'] = 0;

  $result = mollom()->checkContent($params);
}

/**
 * Menu access callback; Validates an inbound Mollom Moderation request.
 *
 * @param object $data
 *   The Mollom data record associated with the content to moderate.
 * @param string $action
 *   The moderation action to perform; e.g., 'spam', 'delete', 'approve'.
 *
 * @return bool
 *   Whether the moderation request is valid and authorized.
 *
 * @see mollom_moderate()
 */
function mollom_moderate_access($data, $action) {
  static $access;

  // Drupal invokes _menu_translate() once more when rendering local tasks for
  // the page, which breaks the OAuth nonce validation.
  // @see http://drupal.org/node/1373072
  if (isset($access)) {
    return $access;
  }
  $access = TRUE;
  // Check global opt-in configuration setting.
  $mollom_form = mollom_form_load($data->form_id);
  if (empty($mollom_form['moderation'])) {
    $access = FALSE;
  }
  // Check required request parameters.
  if (empty($data) || empty($action)) {
    $access = FALSE;
  }
  // Validate authentication protocol parameters.
  if (!mollom_moderate_validate_oauth()) {
    $access = FALSE;
  }
  return $access;
}

/**
 * Returns whether the OAuth request signature is valid.
 */
function mollom_moderate_validate_oauth() {
  // For inbound moderation requests, only the production API keys are valid.
  // The testing mode API keys cannot be trusted. Therefore, this validation
  // is based on the the stored variables only.
  $publicKey = variable_get('mollom_public_key', '');
  $privateKey = variable_get('mollom_private_key', '');
  if ($publicKey === '' || $privateKey === '') {
    mollom_log(array(
      'message' => 'Missing module configuration',
    ), WATCHDOG_WARNING);
    return FALSE;
  }

  $data = MollomDrupal::getServerParameters();
  $header = MollomDrupal::getServerAuthentication();

  // Validate protocol parameters.
  if (!isset($header['oauth_consumer_key'], $header['oauth_nonce'], $header['oauth_timestamp'], $header['oauth_signature_method'], $header['oauth_signature'])) {
    mollom_log(array(
      'message' => 'Missing protocol parameters',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header,
    ), WATCHDOG_WARNING);
    return FALSE;
  }

  $sent_signature = $header['oauth_signature'];
  unset($header['oauth_signature']);

  $allowed_timeframe = 900;

  // Validate consumer key.
  if ($header['oauth_consumer_key'] !== $publicKey) {
    mollom_log(array(
      'message' => 'Invalid public/consumer key',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header,
      'My public key:' => $publicKey,
    ), WATCHDOG_WARNING);
    return FALSE;
  }

  // Validate timestamp.
  if ($header['oauth_timestamp'] <= REQUEST_TIME - $allowed_timeframe) {
    $diff = $header['oauth_timestamp'] - REQUEST_TIME;
    $diff_sign = ($diff < 0 ? '-' : '+');
    if ($diff < 0) {
      $diff *= -1;
    }
    mollom_log(array(
      'message' => 'Outdated authentication timestamp',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header,
      'Time difference:' => $diff_sign . format_interval($diff),
    ), WATCHDOG_WARNING);
    return FALSE;
  }

  // Validate nonce.
  if (empty($header['oauth_nonce'])) {
    mollom_log(array(
      'message' => 'Missing authentication nonce',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header,
    ), WATCHDOG_WARNING);
    return FALSE;
  }
  if ($cached = cache_get('mollom_moderation_nonces', 'cache')) {
    $nonces = $cached->data;
  }
  else {
    $nonces = array();
  }
  if (isset($nonces[$header['oauth_nonce']])) {
    mollom_log(array(
      'message' => 'Replay attack',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header,
    ), WATCHDOG_WARNING);
    return FALSE;
  }
  foreach ($nonces as $nonce => $created) {
    if ($created < REQUEST_TIME - $allowed_timeframe) {
      unset($nonces[$nonce]);
    }
  }
  $nonces[$header['oauth_nonce']] = REQUEST_TIME;
  cache_set('mollom_moderation_nonces', $nonces, 'cache');

  // Validate signature.
  $base_string = implode('&', array(
    $_SERVER['REQUEST_METHOD'],
    Mollom::rawurlencode($GLOBALS['base_url'] . '/' . $_GET['q']),
    Mollom::rawurlencode(Mollom::httpBuildQuery($data + $header)),
  ));
  $key = Mollom::rawurlencode($privateKey) . '&' . '';
  $signature = rawurlencode(base64_encode(hash_hmac('sha1', $base_string, $key, TRUE)));

  $valid = ($signature === $sent_signature);
  if (!$valid) {
    mollom_log(array(
      'message' => 'Invalid authentication signature',
      'Request:' => $_SERVER['REQUEST_METHOD'] . ' ' . $_GET['q'],
      'Request headers:' => $header + array('oauth_signature' => $sent_signature),
      'Base string:' => $base_string,
      'Expected signature:' => $signature,
      //'Expected key:' => $key,
    ), WATCHDOG_WARNING);
  }
  return $valid;
}

/**
 * Menu callback; Performs an inbound Mollom Moderation request action.
 *
 * @param object $data
 *   The Mollom data record associated with the content to moderate.
 * @param string $action
 *   The moderation action to perform; e.g., 'spam', 'delete', 'approve'.
 *
 * @return void
 *   The request is terminated.
 *
 * @see mollom_moderate_access()
 */
function mollom_moderate($data, $action) {
  $result = FALSE;
  $messages = array();

  // Verify that action is supported or respond with a 404.
  if (!in_array($action, array('approve', 'spam', 'delete'))) {
    return MENU_NOT_FOUND;
  }

  // Retrieve 'delete form' id from registered information.
  $form_info = mollom_form_load($data->form_id);

  if ($form_info) {
    if (isset($form_info['entity delete callback'])) {
      $delete_callback = $form_info['entity delete callback'];
      $messages[] = "May delete $form_info[entity] entity via {$delete_callback}().";
    }
    elseif (isset($form_info['entity delete multiple callback'])) {
      $delete_multiple_callback = $form_info['entity delete multiple callback'];
      $messages[] = "May delete $form_info[entity] entity via {$delete_multiple_callback}().";
    }
    elseif (function_exists($data->entity . '_delete')) {
      $delete_callback = $data->entity . '_delete';
      $messages[] = "May delete $form_info[entity] entity via {$delete_callback}().";
    }
    elseif (isset($form_info['delete form'])) {
      $messages[] = "May delete $form_info[entity] entity via {$form_info['delete form']} form.";
    }

    // Programmatically invoke publish action.
    if ($action == 'approve') {
      $messages[] = "Attempt to load $form_info[entity] entity via entity_load().";
      // @todo Abstract this. Possibly requires Entity module as dependency.
      $entity = entity_load($data->entity, array($data->id));
      if (isset($entity[$data->id])) {
        $entity = $entity[$data->id];
        $entity->status = 1;
        $function = $data->entity . '_save';
        $messages[] = "Attempt to save $form_info[entity] entity via {$data->entity}_save().";
        if (function_exists($function)) {
          $function($entity);
          $messages[] = "Updated status of $form_info[entity] entity via {$data->entity}_save().";
          $result = TRUE;
        }
      }
    }
    // Invoke entity delete callback.
    elseif (isset($delete_callback)) {
      $messages[] = "Attempt to delete $form_info[entity] entity via $delete_callback({$data->id}).";
      if (function_exists($delete_callback)) {
        $delete_callback($data->id);
        $messages[] = "Deleted $form_info[entity] entity via $delete_callback({$data->id}).";

        // Entity delete callbacks do not return success or failure, so we can
        // only assume success.
        $result = TRUE;
      }
    }
    // Invoke entity delete multiple callback.
    // @todo Remove 'entity delete multiple' callback support later.
    elseif (isset($delete_multiple_callback)) {
      $messages[] = "Attempt to delete $form_info[entity] entity via $delete_multiple_callback(array({$data->id})).";
      if (function_exists($delete_multiple_callback)) {
        $delete_multiple_callback(array($data->id));
        $messages[] = "Deleted $form_info[entity] entity via $delete_multiple_callback(array({$data->id})).";

        // Entity delete callbacks do not return success or failure, so we can
        // only assume success.
        $result = TRUE;
      }
    }
    // Programmatically invoke delete confirmation form.
    elseif (isset($form_info['delete form'])) {
      $messages[] = "Attempt to delete $form_info[entity] entity {$data->id} via {$form_info['delete form']} form.";
      if (isset($form_info['delete form file'])) {
        $file = $form_info['delete form file'] + array(
          'type' => 'inc',
          'module' => $form_info['module'],
          'name' => NULL,
        );
        module_load_include($file['type'], $file['module'], $file['name']);
        $messages[] = "Loaded {$file['name']}.{$file['type']} in {$file['module']} module.";
      }
      // Programmatic form submissions are not able to automatically use the
      // primary form submit button/action, so we need to resemble
      // drupal_form_submit().
      $form_id = $form_info['delete form'];
      $form_state = form_state_defaults();
      // We assume that all delete confirmation forms take the fully loaded
      // entity as (only) argument.
      $messages[] = "Attempt to load $form_info[entity] entity via entity_load().";
      $entities = entity_load($data->entity, array($data->id));
      $form_state['build_info']['args'][] = $entities[$data->id];
      $form = drupal_retrieve_form($form_id, $form_state);

      $form_state['values'] = array();
      $form_state['values']['mollom']['feedback'] = '';
      // Take over the primary submit button of confirm_form().
      $form_state['values']['op'] = $form['actions']['submit']['#value'];

      $form_state['input'] = $form_state['values'];
      $form_state['programmed'] = TRUE;
      // Programmed forms are always submitted.
      $form_state['submitted'] = TRUE;

      // Reset form validation.
      $form_state['must_validate'] = TRUE;
      form_clear_error();

      drupal_prepare_form($form_id, $form, $form_state);
      drupal_process_form($form_id, $form, $form_state);

      $result = $form_state['executed'];
    }
  }

  $messages = implode("\n", $messages);

  // Double-check for error messages, since entity delete callbacks typically do
  // not return any status.
  $drupal_messages = drupal_get_messages();

  if ($result && !isset($drupal_messages['error'])) {
    mollom_log(array(
      'message' => '%action moderation success for @entity @id',
      'arguments' => array(
        '%action' => $action,
        '@entity' => $data->entity,
        '@id' => $data->id,
      ),
//      'Actions:' => $messages,
    ));
  }
  else {
    // 400 is not really appropriate, but comes closest.
    drupal_add_http_header('Status', '400 Unable to process moderation request');

    $errors = array();
    if (isset($drupal_messages['error'])) {
      $errors['Error messages:'] = implode("\n", $drupal_messages['error']);
    }

    mollom_log(array(
      'message' => '%action moderation failure for @entity @id',
      'arguments' => array(
        '%action' => $action,
        '@entity' => $data->entity,
        '@id' => $data->id,
      ),
      'Actions:' => $messages,
    ) + $errors + array(
      'Mollom data:' => (array) $data,
      'Mollom form:' => $form_info,
    ), WATCHDOG_ERROR);
  }

  // Report back result as success status.
  echo (int) $result;
}

/**
 * @} End of "name mollom_moderation".
 */

/**
 * @name mollom_node Node module integration for Mollom.
 * @{
 */


/**
 * Mollom form moderation callback for nodes.
 */
function node_mollom_form_moderation(&$form, &$form_state) {
  $form_state['values']['status'] = 0;
}

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_node_admin_content_alter(&$form, &$form_state) {
  // @see node_admin_content()
  if (isset($form_state['values']['operation']) && $form_state['values']['operation'] == 'delete') {
    mollom_data_delete_form_alter($form, $form_state);
    // Report before deletion.
    array_unshift($form['#submit'], 'mollom_form_node_multiple_delete_confirm_submit');
  }
  else {
    module_load_include('inc', 'mollom', 'mollom.flag');
    _mollom_table_add_flag_counts('node', $form['admin']['nodes']['#header'], $form['admin']['nodes']['#options']);
  }
}

/**
 * Form submit handler for node_multiple_delete_confirm().
 */
function mollom_form_node_multiple_delete_confirm_submit($form, &$form_state) {
  $nids = array_keys($form_state['values']['nodes']);
  if (!empty($form_state['values']['mollom']['feedback'])) {
    if (mollom_data_report_multiple('node', $nids, $form_state['values']['mollom']['feedback'])) {
      drupal_set_message(t('The posts were successfully reported as inappropriate.'));
    }
  }
  mollom_data_delete_multiple('node', $nids);
}

/**
 * Entity report access callback for nodes.
 * This enables the flag as inappropriate feature for nodes.
 *
 * @param $entity
 *   Optional entity object to check access to a specific entity.
 */
function node_mollom_entity_report_access($entity = NULL) {
  // All nodes can be reported as long as the user has access to view.
  if (!empty($entity) && isset($entity->nid)) {
    return node_access('view', $entity->nid);
  }
  else {
    // Generally turned on when this function is enabled as a callback.
    return TRUE;
  }
}

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_comment_multiple_delete_confirm_alter(&$form, &$form_state) {
  mollom_data_delete_form_alter($form, $form_state);
  // Report before deletion.
  array_unshift($form['#submit'], 'mollom_form_comment_multiple_delete_confirm_submit');
}

/**
 * Form submit handler for node_multiple_delete_confirm().
 */
function mollom_form_comment_multiple_delete_confirm_submit($form, &$form_state) {
  $cids = array_keys($form_state['values']['comments']);
  if (!empty($form_state['values']['mollom']['feedback'])) {
    if (mollom_data_report_multiple('comment', $cids, $form_state['values']['mollom']['feedback'])) {
      drupal_set_message(t('The posts were successfully reported as inappropriate.'));
    }
  }
  mollom_data_delete_multiple('comment', $cids);
}

/**
 * @} End of "name mollom_comment".
 */

/**
 * @name mollom_user User module integration for Mollom.
 * @{
 */


/**
 * Mollom form moderation callback for user accounts.
 */
function user_mollom_form_moderation(&$form, &$form_state) {
  $form_state['values']['status'] = 0;
}

/**
 * Implements hook_form_FORMID_alter().
 */
function mollom_form_user_multiple_cancel_confirm_alter(&$form, &$form_state) {
  mollom_data_delete_form_alter($form, $form_state);
  // Report before deletion.
  array_unshift($form['#submit'], 'mollom_form_user_multiple_cancel_confirm_submit');
}

/**
 * Form submit handler for node_multiple_delete_confirm().
 */
function mollom_form_user_multiple_cancel_confirm_submit($form, &$form_state) {
  $uids = array_keys($form_state['values']['accounts']);
  if (!empty($form_state['values']['mollom']['feedback'])) {
    if (mollom_data_report_multiple('user', $uids, $form_state['values']['mollom']['feedback'])) {
      drupal_set_message(t('The users were successfully reported.'));
    }
  }
  mollom_data_delete_multiple('user', $uids);
}

/**
 * @} End of "name mollom_user".
 */


/**
 * @name mollom_action Actions module integration for Mollom.
 * @{
 */

/**
 * Implements hook_action_info().
 */
function mollom_action_info() {
  return array(
    // Unpublish comment action.
    'mollom_action_unpublish_comment' => array(
      'label' => t('Report comment to Mollom as spam and unpublish'),
      'type' => 'comment',
      'configurable' => FALSE,
      'triggers' => array(
        'comment_insert',
        'comment_update',
      ),
      'aggregate' => TRUE,
    ),
    // Unpublish node action.
    'mollom_action_unpublish_node' => array(
      'label' => t('Report node to Mollom as spam and unpublish'),
      'type' => 'node',
      'configurable' => FALSE,
      'triggers' => array(
        'node_insert',
        'node_update',
      ),
      'aggregate' => TRUE,
    ),
  );
}

/**
 * Action callback to report a comment to mollom and unpublish.
 */
function mollom_action_unpublish_comment($comments, $context = array()) {
  _mollom_action_unpublish('comment', $comments);
}

/**
 * Action callback to report a node to mollom and unpublish.
 */
function mollom_action_unpublish_node($nodes, $context = array()) {
  _mollom_action_unpublish('node', $nodes);
}

/**
 * Unpublish content and report to Mollom as spam.
 *
 * @param $entity_type
 *   The type of entity; one of "comment" or "node".
 * @param $entities
 *   An array of entities to perform the action upon.
 */
function _mollom_action_unpublish($entity_type, $entities) {
  // Make sure this is a supported entity type.
  if (!in_array($entity_type, array('node', 'comment'))) {
    watchdog('Mollom', 'Called unpublish action for an unsupported entity type: @type', array('@type' => $entity_type), WATCHDOG_ERROR);
    return;
  }

  // Determine the entities for which moderation is allowed.
  list($allowed, $nids, $cids) = _mollom_actions_access_callbacks($entity_type, $entities);

  // Send feedback to Mollom.
  $ids = $entity_type === 'comment' ? $cids : $nids;
  mollom_data_report_multiple($entity_type, $ids, 'spam', 'moderate', "mollom_action_unpublish_{$entity_type}");

  if ($entity_type === 'comment') {
    // Unpublish the comment.
    db_update("comment")
      ->fields(array("status" => COMMENT_NOT_PUBLISHED))
      ->condition("cid", $cids)
      ->execute();

    foreach ($nids as $nid) {
      _comment_update_node_statistics($nid);
    }
  }
  else if ($entity_type === 'node') {
    // Unpublish the node.
    db_update("node")
      ->fields(array("status" => NODE_NOT_PUBLISHED))
      ->condition("nid", $nids)
      ->execute();
  }
}

/**
 * Gets all callbacks and checks permissions for entities.
 *
 * @param $entity_type
 *   The type of entity to check.
 * @param $entities
 *   An array of entities to check.
 *
 * @return array
 *   An indexed array of allowed entities
 *   - 0 An array of allowed entities objects
 *   - 1 An array of node ids
 *   - 2 An array of comment ids (if entity_type is comment).
 */
function _mollom_actions_access_callbacks($entity_type, $entities) {
  $cids = array();
  $nids = array();

  // Retrieve any relevant callback for comments
  $report_access_callbacks = array();
  $access_permissions = array();
  $entity_access_callbacks = array();

  $allowed = array();
  foreach (mollom_form_list() as $form_id => $info) {
    if (!isset($info['entity']) || $info['entity'] != $entity_type) {
      continue;
    }
    // If there is a 'report access callback' add it to the list.
    if (isset($info['report access callback'])
      && function_exists($info['report access callback'])
      && !in_array($info['report access callback'], $report_access_callbacks)) {
      $report_access_callbacks[] = $info['report access callback'];
    }
    // Otherwise add any access permissions.
    else if (isset($info['report access']) && !in_array($info['report access'], $access_permissions)) {
      $access_permissions[] = $info['report access'];
    }
    // Check for entity report access callbacks.
    if (isset($info['entity report access callback'])
      && function_exists($info['entity report access callback'])
      && !in_array($info['entity report access callback'], $entity_access_callbacks)) {
      $entity_access_callbacks[] = $info['entity report access callback'];
    }
  }

  // Check access for this comment.
  foreach ($entities as $entity) {
    list($entity_id, $rev_id, $bundle) = entity_extract_ids($entity_type, $entity);
    if ($entity_type === 'comment') {
      $cids[$entity_id] = $entity_id;
      $nids[$entity->nid] = $entity->nid;
    }
    else {
      $nids[$entity_id] = $entity_id;
    }

    // Check reporting callbacks.
    foreach($report_access_callbacks as $callback) {
      if (!$callback($entity_type, $entity_id)) {
        break;
      }
    }

    // Check reporting user permissions.
    foreach($access_permissions as $permission) {
      if (!user_access($permission)) {
        break;
      }
    }

    // Check entity reporting callbacks.
    foreach($report_access_callbacks as $callback) {
      if (!$callback($entity)) {
        break;
      }
    }

    // If still here, then user has access to report this entity.
    $allowed[] = $entity;
  }
  return array($allowed, $nids, $cids);
}

/**
 * @} End of "name mollom_action".
 */