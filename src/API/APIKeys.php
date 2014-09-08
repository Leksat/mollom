<?php
/**
 * Created by PhpStorm.
 * User: lisa.backer
 * Date: 5/20/14
 * Time: 3:28 PM
 */

namespace Drupal\mollom\API;

use Drupal\Core\Config\Config;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\mollom\API\DrupalClientFactory;
use Drupal\mollom\Utility\Logger;


class APIKeys {

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
  static public function getStatus($force = FALSE, $update = FALSE) {
    $config = \Drupal::config('mollom.settings');
    $cache = \Drupal::cache();
    $static_cache = &drupal_static(__FUNCTION__, array());
    $testing_mode = $config->get('testing_mode');
    $status = &$static_cache[$testing_mode];

    if (!$force && isset($status)) {
      return $status;
    }
    // Check the cached status.
    $cid = 'mollom_status:' . $testing_mode;
    $expire_valid = 86400; // once per day
    $expire_invalid = 3600; // once per hour

    if (!$force && $cached = $cache->get($cid)) {
      $status = $cached->data;
      return $status;
    }

    // Re-check configuration status.
    $mollom = DrupalClientFactory::getClient();
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
      elseif ($response === Mollom::AUTH_ERROR) {
        $status['response'] = $response;
        Logger::addMessage(array(
          'message' => 'Invalid API keys.',
        ), WATCHDOG_ERROR);
      }
      elseif ($response === Mollom::REQUEST_ERROR) {
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
    $cache->set($cid, $status, REQUEST_TIME + ($status === TRUE ? $expire_valid : $expire_invalid));
    return $status;
  }

} 
