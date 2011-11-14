<?php

/**
 * @file
 * Mollom client class for Drupal.
 */

/**
 * Drupal Mollom client implementation.
 */
class MollomDrupal extends Mollom {
  /**
   * Mapping of configuration names to Drupal variables.
   *
   * @see Mollom::loadConfiguration()
   */
  private $configuration_map = array(
    'publicKey' => 'mollom_public_key',
    'privateKey' => 'mollom_private_key',
    'servers' => 'mollom_servers',
  );

  /**
   * Implements Mollom::loadConfiguration().
   */
  public function loadConfiguration($name) {
    $name = $this->configuration_map[$name];
    return variable_get($name, NULL);
  }

  /**
   * Implements Mollom::saveConfiguration().
   */
  public function saveConfiguration($name, $value) {
    $name = $this->configuration_map[$name];
    return variable_set($name, $value);
  }

  /**
   * Implements Mollom::deleteConfiguration().
   */
  public function deleteConfiguration($name) {
    $name = $this->configuration_map[$name];
    return variable_del($name);
  }

  /**
   * Implements Mollom::getClientInformation().
   */
  public function getClientInformation() {
    if ($cache = cache_get('mollom_version')) {
      return $cache->data;
    }

    // Retrieve Drupal distribution and installation profile information.
    include_once './includes/install.inc';
    // Prior to D7, there is no distribution name recorded anywhere, so we have to
    // use "Drupal".
    $profile_info['distribution_name'] = 'Drupal';
    $profile_info['version'] = VERSION;

    // Retrieve Mollom module information.
    $mollom_info = db_result(db_query("SELECT info FROM {system} WHERE type = 'module' AND name = 'mollom'"));
    $mollom_info = unserialize($mollom_info);
    if (empty($mollom_info['version'])) {
      // Manually build a module version string for repository checkouts.
      $mollom_info['version'] = DRUPAL_CORE_COMPATIBILITY . '-1.x-dev';
    }

    $data = array(
      'platformName' => $profile_info['distribution_name'],
      'platformVersion' => $profile_info['version'],
      'clientName' => $mollom_info['name'],
      'clientVersion' => $mollom_info['version'],
    );
    cache_set('mollom_version', $data);

    return $data;
  }

  /**
   * Overrides Mollom::writeLog().
   */
  function writeLog() {
    $messages = array();
    foreach ($this->log as $i => $entry) {
      $entry += array('arguments' => array());
      $message = array(
        $entry['message'] => $entry['arguments'],
      );
      if (isset($entry['request'])) {
        $message['Request: @request<pre>@parameters</pre>'] = array(
          '@request' => $entry['request'],
          '@parameters' => !empty($entry['data']) ? $entry['data'] : '',
        );
      }
      if (isset($entry['headers'])) {
        $message['Request headers:<pre>@headers</pre>'] = array(
          '@headers' => $entry['headers'],
        );
      }
      if (isset($entry['response'])) {
        $message['Response:<pre>@response</pre>'] = array(
          '@response' => $entry['response'],
        );
      }
      $messages[] = $message;

      // Translate log messages for debugging without watchdog.
      // @todo Move into mollom.unit.inc implementation.
      /*
      $output = array();
      foreach ($message as $text => $args) {
        foreach ($args as &$arg) {
          if (is_array($arg)) {
            $arg = var_export($arg, TRUE);
          }
        }
        $output[] = strtr($text, $args);
      }
      $this->log[$i]['message'] = implode("\n", $output);
      unset($this->log[$i]['arguments']);
      drupal_set_message(implode('<br />', $output)); //, $this->log[$i]['severity']);
      */
    }
    _mollom_watchdog_multiple($messages, $this->lastResponseCode === TRUE ? WATCHDOG_DEBUG : WATCHDOG_ERROR);

    // After writing log messages, empty the log.
    $this->purgeLog();
  }

  /**
   * Overrides Mollom::refreshServers().
   */
  protected function refreshServers() {
    $servers = parent::refreshServers();

    // Allow other modules to alter the server list. Internal use only.
    drupal_alter('mollom_server_list', $servers);

    return $servers;
  }

  /**
   * Implements Mollom::request().
   */
  protected function request($method, $server, $path, $query = NULL, array $headers = array()) {
    $request = array(
      'method' => $method,
      'headers' => $headers,
      'timeout' => 5.0,
    );
    if (isset($query)) {
      if ($method == 'GET') {
        $path .= '?' . $query;
      }
      elseif ($method == 'POST') {
        $request['data'] = $query;
      }
    }

    $dhr = drupal_http_request($server . '/' . $path, $request['headers'], $method, isset($request['data']) ? $request['data'] : NULL);
    // @todo Core: Ensure that $dhr->code is an integer.
    $dhr->code = (int) $dhr->code;
    // @todo Core: Any other code than 200 is interpreted as error.
    if ($dhr->code >= 200 && $dhr->code < 300) {
      unset($dhr->error);
    }
    // @todo Core: data property is not assigned if there is no response body.
    if (!isset($dhr->data)) {
      $dhr->data = NULL;
    }
    // @todo Core: Timeout produces a bogus non-negative status code.
    // @see http://drupal.org/node/1246376
    if ($dhr->code === 1) {
      $dhr->code = -1;
    }
    // Convert response headers to lowercase.
    if (!empty($dhr->headers)) {
      foreach ($dhr->headers as $name => $value) {
        unset($dhr->headers[$name]);
        $dhr->headers[drupal_strtolower($name)] = $value;
      }
    }

    $response = (object) array(
      'code' => $dhr->code,
      'message' => isset($dhr->error) ? $dhr->error : NULL,
      'headers' => isset($dhr->headers) ? $dhr->headers : array(),
      'body' => $dhr->data,
    );
    return $response;
  }
}

/**
 * Drupal Mollom client implementation using production testing servers.
 */
class MollomDrupalTest extends MollomDrupal {
  /**
   * Overrides Mollom::$serversInit.
   */
  public $serversInit = array('http://dev.mollom.com');
}

/**
 * Drupal Mollom client implementation using local dummy/fake REST server.
 */
class MollomDrupalTestLocal extends MollomDrupal {
  /**
   * Overrides Mollom::__construct().
   */
  function __construct() {
    parent::__construct();
    // Replace initial server list with local fake server.
    $this->serversInit = array($GLOBALS['base_url'] . '/mollom-test/rest');
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

