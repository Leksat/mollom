<?php

namespace Drupal\mollom\API;

/**
 * Drupal Mollom client implementation using local dummy/fake REST server.
 */
class DrupalTestLocalClient extends DrupalTestClient {

  /**
   * Overrides MollomDrupalTest::__construct().
   */
  function __construct() {
    // Replace server/endpoint with our local fake server.
    list(, $server) = explode('://', $GLOBALS['base_url'], 2);
    $this->server = $server . '/mollom-test/rest';

    parent::__construct();
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

/**
 * Drupal Mollom client implementation using an invalid server.
 */
class MollomDrupalTestInvalid extends MollomDrupalTest {

  /**
   * Overrides MollomDrupalTest::$createKeys.
   *
   * Do not attempt to verify API keys against invalid server.
   */
  public $createKeys = FALSE;

  private $currentAttempt = 0;

  private $originalServer;

  /**
   * Overrides MollomDrupalTest::__construct().
   */
  function __construct() {
    $this->originalServer = $this->server;
    $this->server = 'fake-host';
    parent::__construct();
  }

  /**
   * Overrides Mollom::query().
   */
  public function query($method, $path, array $data = array(), array $expected = array()) {
    $this->currentAttempt = 0;
    return parent::query($method, $path, $data, $expected);
  }

  /**
   * Overrides Mollom::handleRequest().
   *
   * Mollom::$server is replaced with an invalid server, so all requests will
   * result in a network error. However, if the 'mollom_testing_server_failover'
   * variable is set to TRUE, then the last request attempt will succeed.
   */
  protected function handleRequest($method, $server, $path, $data, $expected = array()) {
    $this->currentAttempt++;

    if (variable_get('mollom_testing_server_failover', FALSE) && $this->currentAttempt == $this->requestMaxAttempts) {
      // Prior to PHP 5.3, there is no late static binding, so there is no way
      // to access the original value of MollomDrupalTest::$server.
      $server = strtr($server, array($this->server => $this->originalServer));
    }
    return parent::handleRequest($method, $server, $path, $data, $expected);
  }
} 
