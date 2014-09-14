<?php

namespace Drupal\mollom\Utility;

use Drupal\mollom\API\DrupalClient;

class Mollom {

  /**
   * Recursive helper function to flatten nested form values.
   *
   * Takes a potentially nested array and returns all non-empty string values in
   * nested keys as new indexed array.
   */
  public static function _mollom_flatten_form_values($values) {
    $flat_values = array();
    foreach ($values as $value) {
      if (is_array($value)) {
        // Only text fields are supported at this point; their values are in the
        // 'summary' (optional) and 'value' keys.
        if (isset($value['value'])) {
          if (isset($value['summary']) && $value['summary'] !== '') {
            $flat_values[] = $value['summary'];
          }
          if ($value['value'] !== '') {
            $flat_values[] = $value['value'];
          }
        }
        elseif (!empty($value)) {
          $flat_values = array_merge($flat_values, self::_mollom_flatten_form_values($value));
        }
      }
      elseif (is_string($value) && strlen($value)) {
        $flat_values[] = $value;
      }
    }
    return $flat_values;
  }

  /**
   * Helper function to return OpenID identifiers associated with a given user account.
   */
  public static function _mollom_get_openid($account) {
    if (isset($account->uid)) {
      $ids = db_query('SELECT authname FROM {authmap} WHERE module = :module AND uid = :uid', array(':module' => 'openid', ':uid' => $account->uid))->fetchCol();

      if (!empty($ids)) {
        return implode($ids, ' ');
      }
    }
  }

  /**
   * Helper function to determine protected forms for an entity.
   *
   * @param $type
   *   The type of entity to check.
   * @param $bundle
   *   An array of bundle names to check.
   *
   * @return array
   *   An array of protected bundles for this entity type.
   */
  public static function _mollom_get_entity_forms_protected($type, $bundles = array()) {
    // Find out if this entity bundle is protected.
    $protected = &drupal_static(__FUNCTION__,array());
    if (empty($bundles)) {
      $info = entity_get_info($type);
      $bundles = array_keys($info['bundles']);
    }
    $protected_bundles = array();
    foreach ($bundles as $bundle) {
      if (!isset($protected[$type][$bundle])) {
        $protected[$type][$bundle] = db_query_range('SELECT 1 FROM {mollom_form} WHERE entity = :entity AND bundle = :bundle', 0, 1, array(
          ':entity' => $type,
          ':bundle' => isset($bundle) ? $bundle : $type,
        ))->fetchField();
      }
      if (!empty($protected[$type][$bundle])) {
        $protected_bundles[] = $bundle;
      }
    }
    return $protected_bundles;
  }

  /**
   * Returns the (last known) status of the configured Mollom API keys.
   *
   * @param bool $force
   *   (optional) Boolean whether to ignore the cached state and re-check.
   *   Defaults to FALSE.
   * @param bool $update
   *   (optional) Whether to update Mollom with locally stored configuration.
   *   Defaults to FALSE.
   *
   * @return array
   *   An associative array describing the current status of the module:
   *   - isConfigured: Boolean whether Mollom API keys have been configured.
   *   - isVerified: Boolean whether Mollom API keys have been verified.
   *   - response: The response error code of the API verification request.
   *   - ...: The full site resource, as returned by the Mollom API.
   *
   * @see mollom_init()
   * @see mollom_admin_settings()
   * @see mollom_requirements()
   */
  public static function _mollom_status($force = FALSE, $update = FALSE) {
    $static_cache = &drupal_static(__FUNCTION__, array());
    $testing_mode = (int) \Drupal::config('mollom.settings')->get('mollom_testing_mode', 0);
    $status = &$static_cache[$testing_mode];

    if (!$force && isset($status)) {
      return $status;
    }
    // Check the cached status.
    $cid = 'mollom_status:' . $testing_mode;
    $expire_valid = 86400; // once per day
    $expire_invalid = 3600; // once per hour

    /*if (!$force && $cache = cache_get($cid, 'cache')) {
      if ($cache->expire > REQUEST_TIME) {
        $status = $cache->data;
        return $status;
      }
    }*/

    // Re-check configuration status.
    /** @var \Drupal\mollom\API\DrupalClient $mollom */
    $mollom = \Drupal::service('mollom.client');
    $status = array(
      'isConfigured' => FALSE,
      'isVerified' => FALSE,
      'isTesting' => (bool) $testing_mode,
      'response' => NULL,
      'publicKey' => $mollom->loadConfiguration('publicKey'),
      'privateKey' => $mollom->loadConfiguration('privateKey'),
      'expectedLanguages' => $mollom->loadConfiguration('expectedLanguages'),
    );
    $status['isConfigured'] = (!empty($status['publicKey']) && !empty($status['privateKey']));
    $status['expectedLanguages'] = is_array($status['expectedLanguages']) ? array_values($status['expectedLanguages']) : array();

    if ($testing_mode || $status['isConfigured']) {
      $old_status = $status;
      $data = array();
      if ($update) {
        // Ensure to use the most current API keys (might have been changed).
        $mollom->publicKey = $status['publicKey'];
        $mollom->privateKey = $status['privateKey'];

        $data += array(
          'expectedLanguages' => $status['expectedLanguages'],
        );
      }
      $data += $mollom->getClientInformation();
      $response = $mollom->updateSite($data);

      if (is_array($response) && $mollom->lastResponseCode === TRUE) {
        $status = array_merge($status,$response);
        $status['isVerified'] = TRUE;
        Logger::addMessage(array(
          'message' => 'API keys are valid.',
        ), WATCHDOG_INFO);

        // Unless we just updated, update local configuration with remote.
        if (!$update) {
          $languages_expected = is_array($status['expectedLanguages']) ? array_values($status['expectedLanguages']) : array();
          if ($old_status['expectedLanguages'] != $status['expectedLanguages']) {
            $mollom->saveConfiguration('expectedLanguages', $status['expectedLanguages']);
          }
        }
      }
      elseif ($response === $mollom::AUTH_ERROR) {
        $status['response'] = $response;
        Logger::addMessage(array(
          'message' => 'Invalid API keys.',
        ), WATCHDOG_ERROR);
      }
      elseif ($response === $mollom::REQUEST_ERROR) {
        $status['response'] = $response;
        Logger::addMessage(array(
          'message' => 'Invalid client configuration.',
        ), WATCHDOG_ERROR);
      }
      else {
        $status['response'] = $response;
        // A NETWORK_ERROR and other possible responses may be caused by the
        // client-side environment, but also by Mollom service downtimes. Try to
        // recover as soon as possible.
        $expire_invalid = 60 * 5;
        Logger::addMessage(array(
          'message' => 'API keys could not be verified.',
        ), WATCHDOG_ERROR);
      }
    }
    //cache_set($cid, $status, 'cache', REQUEST_TIME + ($status === TRUE ? $expire_valid : $expire_invalid));
    return $status;
  }

  /**
   * Outputs a warning message about enabled testing mode (once).
   */
  public static function _mollom_testing_mode_warning() {
    // drupal_set_message() starts a session and disables page caching, which
    // breaks cache-related tests. Thus, tests set the verbose variable to TRUE.
    $warned = &drupal_static(__FUNCTION__, \Drupal::config('mollom.settings')->get('mollom_testing_mode_omit_warning', NULL));
    if (isset($warned)) {
      return;
    }
    $warned = TRUE;

    if (\Drupal::config('mollom.settings')->get('mollom_testing_mode', 0) && empty($_POST)) {
      $admin_message = '';
      if (user_access('administer mollom') && $_GET['q'] != 'admin/config/content/mollom/settings') {
        $admin_message = t('Visit the <a href="@settings-url">Mollom settings page</a> to disable it.', array(
          '@settings-url' => url('admin/config/content/mollom/settings'),
        ));
      }
      $message = t('Mollom testing mode is still enabled. !admin-message', array(
        '!admin-message' => $admin_message,
      ));
      drupal_set_message($message, 'warning');
    }
  }

  /**
   * Helper function to log and optionally output an error message when Mollom servers are unavailable.
   */
  public static function _mollom_fallback() {
    $fallback = \Drupal::config('mollom.settings')->get('mollom_fallback', MOLLOM_FALLBACK_BLOCK);
    if ($fallback == MOLLOM_FALLBACK_BLOCK) {
      form_set_error('mollom', t("The spam filter installed on this site is currently unavailable. Per site policy, we are unable to accept new submissions until that problem is resolved. Please try resubmitting the form in a couple of minutes."));
    }
    return true;
  }

  /**
   * Formats a message for end-users to report false-positives.
   *
   * @param array $form_state
   *   The current state of the form.
   * @param array $data
   *   The latest Mollom session data pertaining to the form submission attempt.
   *
   * @return string
   *   A message string containing a specially crafted link to Mollom's
   *   false-positive report form, supplying these parameters:
   *   - public_key: The public API key of this site.
   *   - url: The current, absolute URL of the form.
   *   At least one or both of:
   *   - contentId: The content ID of the Mollom session.
   *   - captchaId: The CAPTCHA ID of the Mollom session.
   *   If available, to speed up and simplify the false-positive report form:
   *   - authorName: The author name, if supplied.
   *   - authorMail: The author's e-mail address, if supplied.
   */
  public static function _mollom_format_message_falsepositive($form_state, $data) {
    $mollom = mollom();
    $report_url = 'https://mollom.com/false-positive';
    $params = array(
      'public_key' => $mollom->loadConfiguration('publicKey'),
    );
    $params += array_intersect_key($form_state['values']['mollom'], array_flip(array('contentId', 'captchaId')));
    $params += array_intersect_key($data, array_flip(array('authorName', 'authorMail')));
    $params['url'] = $GLOBALS['base_root'] . request_uri();
    $report_url .= '?' . drupal_http_build_query($params);
    return t('If you feel this is in error, please <a href="@report-url" class="mollom-target">report that you are blocked</a>.', array(
      '@report-url' => $report_url,
    ));
  }

  /**
   * Helper function to determine if "report to Mollom" is available
   * and activated for a specific entity type.
   *
   * @param $type
   *   The entity type to check.
   * @param $bundles
   *   An array of entity bundles to check.
   */
  public static function mollom_flag_entity_type_access($type, $bundles = array()) {
    // Check to see if flag as inappropriate is enabled for this entity type.
    $allowed = \Drupal::config('mollom.settings')->get('mollom_fai_entity_types', array('comment' => 1));
    if (empty($allowed[$type])) {
      return;
    }

    // Check to see if there are protected forms for this content type.
    $protected = _mollom_get_entity_forms_protected($type, $bundles);
    if (empty($protected)) {
      return FALSE;
    }

    // Make sure that flag as inappropriate is active for this type.
    // If any form for this entity type is reportable, then show data.
    $forms = mollom_form_list();
    foreach ($forms as $info) {
      if (!isset($info['entity']) || $info['entity'] != $type) {
        continue;
      }
      if (isset($info['entity report access callback'])) {
        $function = $info['entity report access callback'];
        if ($function()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Helper function to determine if "report to mollom" is available for the
   * current entity.
   *
   * @param $type
   *   The type of the entity to check.
   * @param $entity
   *   The entity to check.  This can be either the entity object or an id.
   * @return bool
   *   True if reporting is available and false if not available.
   */
  public static function _mollom_flag_access($type, $entity) {
    // make sure that Mollom is active and user is able to report.
    if (!_mollom_access('report to mollom')) {
      return FALSE;
    }
    // Find out if this entity bundle is protected.
    if (!is_object($entity)) {
      $entities = entity_load($type, array($entity));
      $entity = $entities[$entity];
    }
    list($id, $rid, $bundle) = entity_extract_ids($type, $entity);
    return mollom_flag_entity_type_access($type, array($bundle));
  }

}