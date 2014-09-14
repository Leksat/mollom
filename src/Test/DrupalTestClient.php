<?php

namespace Drupal\mollom\Test;

use Drupal\Core\Config\ConfigFactory;
use Drupal\mollom\API\DrupalClient;
use GuzzleHttp\ClientInterface;
use Mollom\Exception\MollomException;

/**
 * Drupal Mollom client implementation using testing API servers.
 */
class DrupalTestClient extends DrupalClient {
  /**
   * Overrides Mollom::$server.
   */
  public $server = 'dev.mollom.com';

  /**
   * Flag indicating whether to verify and automatically create testing API keys upon class instantiation.
   *
   * @var bool
   */
  public $createKeys;

  /**
   * Overrides Mollom::__construct().
   *
   * This class accounts for multiple scenarios:
   * - Straight low-level requests against the testing API from a custom script,
   *   caring for API keys on its own.
   * - Whenever the testing mode is enabled (either through the module's
   *   settings page or by changing the mollom_testing_mode system variable),
   *   the client requires valid testing API keys to perform any calls. Testing
   *   API keys are different to production API keys, need to be created first,
   *   and may vanish at any time (whenever the testing API server is
   *   redeployed). Since they are different, the class stores them in different
   *   system variables. Since they can vanish at any time, the class verifies
   *   the keys upon every instantiation, and automatically creates new testing
   *   API keys if necessary.
   * - Some automated unit tests attempt to verify that authentication errors
   *   are handled correctly by the class' error handling. The automatic
   *   creation and recovery of testing API keys would break those assertions,
   *   so said tests can disable the behavior by preemptively setting
   *   $createKeys or the 'mollom_testing_create_keys' system variable to FALSE,
   *   and manually create testing API keys (once).
   *
   * Constructor.
   * @param ConfigFactory $config_factory
   * @param ClientInterface $http_client
   *
   * @see Mollom::__construct().
   */
  public function __construct(ConfigFactory $config_factory, ClientInterface $http_client) {
    $this->config = $config_factory->get('mollom.settings');

    // Some tests are verifying the production behavior of e.g. setting up API
    // keys, in which testing mode is NOT enabled and the test creates fake
    // "production" API keys on the local fake server on its own. This special
    // override must only be possible when executing tests.
    // @todo Add global test_info as condition?
    $testing_mode = $this->config->get('mollom_testing_mode', 0);
    $module_exists = \Drupal::moduleHandler()->moduleExists('mollom_test_server');
    if ($module_exists && !$testing_mode) {
      // Disable authentication error auto-recovery.
      $this->createKeys = FALSE;
    }
    else {
      // Do not destroy production variables when testing mode is enabled.
      $this->configuration_map['publicKey'] = 'mollom_test_public_key';
      $this->configuration_map['privateKey'] = 'mollom_test_private_key';
    }

    // Load and set publicKey and privateKey configuration values.
    parent::__construct($config_factory, $http_client);

    // Unless pre-set, determine whether API keys should be auto-created.
    if (!isset($this->createKeys)) {
      $this->createKeys = (bool) \Drupal::config('mollom.settings')->get('mollom_testing_create_keys', TRUE);
    }

    // Testing can require additional time.
    $this->requestTimeout = \Drupal::config('mollom.settings')->get('mollom_connection_timeout', 3) + 10;
  }

  /**
   * Overrides Mollom::handleRequest().
   *
   * Automatically tries to generate new API keys in case of a 401 or 404 error.
   * Intentionally reacts on 401 or 404 errors only, since any other error code
   * can mean that either the Testing API is down or that the client site is not
   * able to perform outgoing HTTP requests in general.
   */
  protected function handleRequest($method, $server, $path, $data, $expected = array()) {
    try {
      $response = parent::handleRequest($method, $server, $path, $data, $expected);
    }
    catch (MollomException $e) {
      $is_auth_error = $e->getCode() == self::AUTH_ERROR || ($e->getCode() == 404 && strpos($path, 'site') === 0);
      $current_public_key = $this->publicKey;
      if ($this->createKeys && $is_auth_error && $this->createKeys()) {
        $this->saveKeys();
        // Avoid to needlessly hit the previous/invalid public key again.
        // Mollom::handleRequest() will sign the new request correctly.
        // If it was empty, Mollom::handleRequest() returned an AUTH_ERROR
        // without issuing a request.
        if ($path == 'site/') {
          $path = 'site/' . $this->publicKey;
        }
        elseif (!empty($current_public_key)) {
          $path = str_replace($current_public_key, $this->publicKey, $path);
        }
        $response = parent::handleRequest($method, $server, $path, $data, $expected);
      }
      else {
        throw $e;
      }
    }
    return $response;
  }

  /**
   * Creates new testing API keys.
   *
   * @todo Move site properties into $data argument (Drupal-specific values),
   *   rename to createTestingSite(), and move into Mollom class?
   */
  public function createKeys() {
    // Do not attempt to create API keys repeatedly.
    $this->createKeys = FALSE;

    // Without any API keys, the client does not even attempt to perform a
    // request. Set dummy API keys to overcome that sanity check.
    $this->publicKey = 'public';
    $this->privateKey = 'private';

    // Skip authorization for creating testing API keys.
    $oAuthStrategy = $this->oAuthStrategy;
    $this->oAuthStrategy = '';
    $result = $this->createSite(array(
      'url' => $GLOBALS['base_url'],
      //'email' => variable_get('site_mail', 'mollom-drupal-test@example.com'),
      // @todo Drupal 8 for site_mail
      'email' => 'mollom-drupal-test@example.com',
    ));
    $this->oAuthStrategy = $oAuthStrategy;

    // Set class properties.
    if (is_array($result) && !empty($result['publicKey']) && !empty($result['privateKey'])) {
      $this->publicKey = $result['publicKey'];
      $this->privateKey = $result['privateKey'];
      return TRUE;
    }
    else {
      $this->publicKey = $this->privateKey = '';
      return FALSE;
    }
  }

  /**
   * Saves API keys to local configuration store.
   */
  public function saveKeys() {
    $this->saveConfiguration('publicKey', $this->publicKey);
    $this->saveConfiguration('privateKey', $this->privateKey);
  }

  /**
   * Deletes API keys from local configuration store.
   */
  public function deleteKeys() {
    $this->deleteConfiguration('publicKey');
    $this->deleteConfiguration('privateKey');
  }
}
