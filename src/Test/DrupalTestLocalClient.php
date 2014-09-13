<?php

namespace Drupal\mollom\Test;

use Drupal\Core\Config\ConfigFactory;
use Guzzle\Http\ClientInterface;

/**
 * Drupal Mollom client implementation using local dummy/fake REST server.
 */
class DrupalTestLocalClient extends DrupalTestClient {

  /**
   * Overrides MollomDrupalTest::__construct().
   */
  public function __construct(ConfigFactory $config_factory, ClientInterface $http_client) {
    // Replace server/endpoint with our local fake server.
    list(, $server) = explode('://', $GLOBALS['base_url'], 2);
    $this->server = $server . '/mollom-test/rest';

    parent::__construct($config_factory, $http_client);
  }

  /**
   * Overrides MollomDrupal::saveKeys().
   */
  public function saveKeys() {
    parent::saveKeys();

    // Ensure that the site exists on the local fake server. Not required for
    // remote REST testing API, because our testing API keys persist there.
    // @see mollom_test_server_rest_site()
    $bin = 'mollom_test_server_site';
    $sites = variable_get($bin, array());
    if (!isset($sites[$this->publicKey])) {
      // Apply default values.
      $sites[$this->publicKey] = array(
        'publicKey' => $this->publicKey,
        'privateKey' => $this->privateKey,
        'url' => '',
        'email' => '',
      );
      variable_set($bin, $sites);
    }
  }

  /**
   * Overrides MollomDrupal::request().
   *
   * Passes-through SimpleTest assertion HTTP headers from child-child-site and
   * triggers errors to make them appear in parent site (where tests are ran).
   *
   * @todo Remove when in core.
   * @see http://drupal.org/node/875342
   */
  protected function request($method, $server, $path, $query = NULL, array $headers = array()) {
    $response = parent::request($method, $server, $path, $query, $headers);
    $keys = preg_grep('@^x-drupal-assertion-@', array_keys($response->headers));
    foreach ($keys as $key) {
      $header = $response->headers[$key];
      $header = unserialize(urldecode($header));
      $message = strtr('%type: !message in %function (line %line of %file).', array(
        '%type' => $header[1],
        '!message' => $header[0],
        '%function' => $header[2]['function'],
        '%line' => $header[2]['line'],
        '%file' => $header[2]['file'],
      ));
      trigger_error($message, E_USER_ERROR);
    }
    return $response;
  }
}
