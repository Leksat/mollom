<?php

/**
 * @file
 * Contains \Drupal\mollom\Form\FormController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mollom\Utility\Logger;
use Drupal\mollom\Utility\Mollom;

/**
 * Controller with functions that are useful in the context of Mollom
 */
class FormController extends ControllerBase {
  /**
   * Returns information about a form registered via hook_mollom_form_info().
   *
   * @param $form_id
   *   The form id to return information for.
   * @param $module
   *   The module name $form_id belongs to.
   * @param array $form_list
   *   (optional) The return value of hook_mollom_form_list() of $module, if
   *   already kown. Primarily used by mollom_form_load().
   */
  public static function mollom_form_info($form_id, $module, $form_list = NULL) {
    // Default properties.
    $form_info = array(
      // Base properties.
      'form_id' => $form_id,
      'title' => $form_id,
      'module' => $module,
      'entity' => NULL,
      'bundle' => NULL,
      // Configuration properties.
      'mode' => NULL,
      'checks' => array(),
      'enabled_fields' => array(),
      'strictness' => 'normal',
      'unsure' => 'captcha',
      'discard' => 1,
      'moderation' => 0,
      // Meta information.
      'bypass access' => array(),
      'elements' => array(),
      'mapping' => array(),
      'mail ids' => array(),
      'orphan' => TRUE,
    );

    // Fetch the basic form information from hook_mollom_form_list() first.
    // This makes the integrating module (needlessly) rebuild all of its available
    // forms, but the base properties are absolutely required here, so we can
    // apply the default properties below.
    if (!isset($form_list)) {
      $form_list = \Drupal::moduleHandler()->invoke($module, 'mollom_form_list');
    }
    // If it is not listed, then the form has vanished.
    if (!isset($form_list[$form_id])) {
      return $form_info;
    }
    $module_form_info = \Drupal::moduleHandler()->invoke($module, 'mollom_form_info', array($form_id));
    // If no form info exists, then the form has vanished.
    if (!isset($module_form_info)) {
      return $form_info;
    }
    unset($form_info['orphan']);

    // Any information in hook_mollom_form_info() overrides the list info.
    $form_info = array_merge($form_info, $form_list[$form_id]);
    $form_info = array_merge($form_info, $module_form_info);

    // Allow modules to alter the default form information.
    \Drupal::moduleHandler()->alter('mollom_form_info', $form_info, $form_id);

    return $form_info;
  }

  /**
   * Creates a bare Mollom form configuration.
   *
   * @param $form_id
   *   The form ID to create the Mollom form configuration for.
   */
  public static function mollom_form_new($form_id) {
    $mollom_form = array();
    $form_list = self::mollom_form_list();
    if (isset($form_list[$form_id])) {
      $mollom_form += $form_list[$form_id];
    }
    $mollom_form += self::mollom_form_info($form_id, $form_list[$form_id]['module'], $form_list);

    // Enable all fields for textual analysis by default.
    $mollom_form['checks'] = array('spam');
    $mollom_form['enabled_fields'] = array_keys($mollom_form['elements']);

    return $mollom_form;
  }

  /**
   * Given an array of values and an array of fields, extract data for use.
   *
   * This function generates the data to send for validation to Mollom by walking
   * through the submitted form values and
   * - copying element values as specified via 'mapping' in hook_mollom_form_info()
   *   into the dedicated data properties
   * - collecting and concatenating all fields that have been selected for textual
   *   analysis into the 'post_body' property
   *
   * The processing accounts for the following possibilities:
   * - A field was selected for textual analysis, but there is no submitted form
   *   value. The value should have been appended to the 'post_body' property, but
   *   will be skipped.
   * - A field is contained in the 'mapping' and there is a submitted form value.
   *   The value will not be appended to the 'post_body', but instead be assigned
   *   to the specified data property.
   * - All fields specified in 'mapping', for which there is a submitted value,
   *   but which were NOT selected for textual analysis, are assigned to the
   *   specified data property. This is usually the case for form elements that
   *   hold system user information.
   *
   * @param $form_state
   *   An associative array containing
   *   - values: The submitted form values.
   *   - buttons: A list of button form elements. See form_state_values_clean().
   * @param $fields
   *   A list of strings representing form elements to extract. Nested fields are
   *   in the form of 'parent][child'.
   * @param $mapping
   *   An associative array of form elements to map to Mollom's dedicated data
   *   properties. See hook_mollom_form_info() for details.
   *
   * @see hook_mollom_form_info()
   */
  public static function mollom_form_get_values(&$form_state, $fields, $mapping) {
    global $user;

    // @todo Unless mollom_form_submit() directly attempts to retrieve 'postId'
    //   from $form_state['values'], the resulting content properties of this
    //   function cannot be cached.

    // Remove all button values from $form_state['values'].
    $form_state_copy = $form_state;
    form_state_values_clean($form_state_copy);
    $form_values = $form_state_copy['values'];

    // All elements specified in $mapping must be excluded from $fields, as they
    // are used for dedicated $data properties instead. To reduce the parsing code
    // size, we are turning a given $mapping of f.e.
    //   array('post_title' => 'title_form_element')
    // into
    //   array('title_form_element' => 'post_title')
    // and we reset $mapping afterwards.
    // When iterating over the $fields, this allows us to quickly test whether the
    // current field should be excluded, and if it should, we directly get the
    // mapped property name to rebuild $mapping with the field values.
    $exclude_fields = array();
    if (!empty($mapping)) {
      $exclude_fields = array_flip($mapping);
    }
    $mapping = array();

    // Process all fields that have been selected for text analysis.
    $post_body = array();
    foreach ($fields as $field) {
      // Nested elements use a key of 'parent][child', so we need to recurse.
      $parents = explode('][', $field);
      $value = $form_values;
      foreach ($parents as $key) {
        $value = isset($value[$key]) ? $value[$key] : NULL;
      }
      // If this field was contained in $mapping and should be excluded, add it to
      // $mapping with the actual form element value, and continue to the next
      // field. Also unset this field from $exclude_fields, so we can process the
      // remaining mappings below.
      if (isset($exclude_fields[$field])) {
        $mapping[$exclude_fields[$field]] = $value;
        unset($exclude_fields[$field]);
        continue;
      }
      // Only add form element values that are not empty.
      if (isset($value)) {
        // UTF-8 validation happens later.
        if (is_string($value) && strlen($value)) {
          $post_body[$field] = $value;
        }
        // Recurse into nested values (e.g. multiple value fields).
        elseif (is_array($value) && !empty($value)) {
          // Ensure we have a flat, indexed array to implode(); form values of
          // field_attach_form() use several subkeys.
          $value = Mollom::_mollom_flatten_form_values($value);
          $post_body = array_merge($post_body, $value);
        }
      }
    }
    $post_body = implode("\n", $post_body);

    // Try to assign any further form values by processing the remaining mappings,
    // which have been turned into $exclude_fields above. All fields that were
    // already used for 'post_body' no longer exist in $exclude_fields.
    foreach ($exclude_fields as $field => $property) {
      // Nested elements use a key of 'parent][child', so we need to recurse.
      $parents = explode('][', $field);
      $value = $form_values;
      foreach ($parents as $key) {
        $value = isset($value[$key]) ? $value[$key] : NULL;
      }
      if (isset($value)) {
        if (is_array($value)) {
          $value = Mollom::_mollom_flatten_form_values($value);
          $value = implode(' ', $value);
        }
        $mapping[$property] = $value;
      }
    }

    // Mollom's XML-RPC methods only accept data properties that are defined. We
    // also do not want to send more than we have to, so we need to build an
    // exact data structure.
    $data = array();
    // Post id; not sent to Mollom.
    // @see mollom_form_submit()
    if (!empty($mapping['post_id'])) {
      $data['postId'] = $mapping['post_id'];
    }
    // Post title.
    if (!empty($mapping['post_title'])) {
      $data['postTitle'] = $mapping['post_title'];
    }
    // Post body.
    if (!empty($post_body)) {
      $data['postBody'] = $post_body;
    }

    // Author ID.
    // If a non-anonymous user ID was mapped via form values, use that.
    if (!empty($mapping['author_id'])) {
      $data['authorId'] = $mapping['author_id'];
    }
    // Otherwise, the currently logged-in user is the author.
    elseif (!empty($user->uid)) {
      $data['authorId'] = $user->uid;
    }

    // Load the user account of the author, if any, for the following author*
    // property assignments.
    $account = FALSE;
    if (isset($data['authorId'])) {
      $account = user_load($data['authorId']);
    }

    // Author creation date.
    if (!empty($account->created)) {
      $data['authorCreated'] = $account->created;
    }

    // Author name.
    // A form value mapping always has precedence.
    if (!empty($mapping['author_name'])) {
      $data['authorName'] = $mapping['author_name'];
    }
    // In case a post of a registered user is edited and a form value mapping
    // exists for author_id, but no form value mapping exists for author_name,
    // use the name of the user account associated with author_id.
    // $account may be the same as the currently logged-in $user at this point.
    elseif (!empty($account->name)) {
      $data['authorName'] = $account->name;
    }

    // Author e-mail.
    if (!empty($mapping['author_mail'])) {
      $data['authorMail'] = $mapping['author_mail'];
    }
    elseif (!empty($account->mail)) {
      $data['authorMail'] = $account->mail;
    }

    // Author homepage.
    if (!empty($mapping['author_url'])) {
      $data['authorUrl'] = $mapping['author_url'];
    }

    // Author OpenID.
    if (!empty($mapping['author_openid'])) {
      $data['authorOpenid'] = $mapping['author_openid'];
    }
    elseif (!empty($account) && ($openid = Mollom::_mollom_get_openid($account))) {
      $data['authorOpenid'] = $openid;
    }

    // Author IP.
    $data['authorIp'] = ip_address();

    // Honeypot.
    // For the Mollom backend, it only matters whether 'honeypot' is non-empty.
    // The submitted value is only taken over to allow site administrators to
    // see the actual honeypot value in watchdog log entries.
    if (isset($form_values['mollom']['homepage']) && $form_values['mollom']['homepage'] !== '') {
      $data['honeypot'] = $form_values['mollom']['homepage'];
    }

    // Ensure that all $data values contain valid UTF-8. Invalid UTF-8 would be
    // sanitized into an empty string, so the Mollom backend would not receive
    // any value.
    $invalid_utf8 = FALSE;
    $invalid_xml = FALSE;
    // Include the CAPTCHA solution user input in the UTF-8 validation.
    $solution = isset($form_values['mollom']['captcha']) ? array('solution' => $form_values['mollom']['captcha']) : array();
    foreach ($data + $solution as $key => $value) {
      // Check for invalid UTF-8 byte sequences first.
      if (!drupal_validate_utf8($value)) {
        $invalid_utf8 = TRUE;
        // Replace the bogus string, since $data will be logged as
        // check_plain(var_export($data)), and check_plain() would empty the
        // entire exported variable string otherwise.
        $data[$key] = '- Invalid UTF-8 -';
      }
      // Since values are transmitted over XML-RPC and not merely output as
      // (X)HTML, they have to be valid XML characters.
      // @see http://www.w3.org/TR/2000/REC-xml-20001006#charsets
      // @see http://drupal.org/node/882298
      elseif (preg_match('@[^\x9\xA\xD\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]@u', $value)) {
        $invalid_xml = TRUE;
      }
    }
    if ($invalid_utf8 || $invalid_xml) {
      //form_set_error('', t('Your submission contains invalid characters and will not be accepted.'));
      Logger::addMessage(array(
        'message' => 'Invalid !type in form values',
        'arguments' => array('!type' => $invalid_utf8 ? 'UTF-8' : 'XML characters'),
        'Data:' => $data,
      ));
      $data = FALSE;
    }

    return $data;
  }

  /**
   * Returns a list of protectable forms registered via hook_mollom_form_info().
   */
  public static function mollom_form_list() {
    $form_list = array();
    foreach (\Drupal::moduleHandler()->getImplementations('mollom_form_list') as $module) {
      $function = $module . '_mollom_form_list';
      $module_forms = $function();
      foreach ($module_forms as $form_id => $info) {
        $form_list[$form_id] = $info;
        $form_list[$form_id] += array(
          'form_id' => $form_id,
          'module' => $module,
        );
      }
    }

    // Allow modules to alter the form list.
    \Drupal::moduleHandler()->alter('mollom_form_list', $form_list);

    return $form_list;
  }

  /**
   * Returns a cached mapping of protected and delete confirmation form ids.
   *
   * @param $reset
   *   (optional) Boolean whether to reset the static cache, flush the database
   *   cache, and return nothing (TRUE). Defaults to FALSE.
   *
   * @return
   *   An associative array containing:
   *   - protected: An associative array whose keys are protected form IDs and
   *     whose values are the corresponding module names the form belongs to.
   *   - delete: An associative array whose keys are 'delete form' ids and whose
   *     values are protected form ids; e.g.
   *     @code
   *     array(
   *       'node_delete_confirm' => 'article_node_form',
   *     )
   *     @endcode
   *     A single delete confirmation form id can map to multiple registered
   *     $form_ids, but only the first is taken into account. As in above example,
   *     we assume that all 'TYPE_node_form' definitions belong to the same entity
   *     and therefore have an identical 'post_id' mapping.
   */
  public static function mollom_form_cache($reset = FALSE) {
    $forms = &drupal_static(__FUNCTION__);

    if ($reset) {
      // This catches both 'mollom:form_cache' as well as mollom_form_load()'s
      // 'mollom:form:*' entries.
      //cache_clear_all('mollom:form', 'cache', TRUE);
      unset($forms);
      return;
    }

    if (isset($forms)) {
      return $forms;
    }

    if ($cache = cache_get('mollom:form_cache')) {
      $forms = $cache->data;
      return $forms;
    }

    $forms['protected'] = db_query("SELECT form_id, module FROM {mollom_form}")->fetchAllKeyed();

    // Build a list of delete confirmation forms of entities integrating with
    // Mollom, so we are able to alter the delete confirmation form to display
    // our feedback options.
    $forms['delete'] = array();
    foreach (self::mollom_form_list() as $form_id => $info) {
      if (!isset($info['delete form']) || !isset($info['entity'])) {
        continue;
      }
      // We expect that the same delete confirmation form uses the same form
      // element mapping, so multiple 'delete form' definitions are only processed
      // once. Additionally, we only care for protected forms.
      if (!isset($forms['delete'][$info['delete form']]) && isset($forms['protected'][$form_id])) {
        // A delete confirmation form integration requires a 'post_id' mapping.
        $form_info = mollom_form_info($form_id, $info['module']);
        if (isset($form_info['mapping']['post_id'])) {
          $forms['delete'][$info['delete form']] = $form_id;
        }
      }
    }
    cache_set('mollom:form_cache', $forms);

    return $forms;
  }

  /**
   * Ajax callback for retrieving a form behavior analysis image.
   *
   * Outputs the JSON encoded tracking information received from Mollom.  This
   * will include keys of:
   *   - tracking_url: the URL to the tracking image
   *   - tracking_id: an ID to track for the image
   */
  public static function mollom_fba_js() {
    // Deny GET requests to make automated security audit tools not complain
    // about a JSON Hijacking possibility.
    // @see http://capec.mitre.org/data/definitions/111.html
    // @see http://haacked.com/archive/2009/06/24/json-hijacking.aspx
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header($_SERVER['SERVER_PROTOCOL'] . ' 405 Method Not Allowed');
      // A HTTP 405 response MUST specify allowed methods.
      header('Allow: POST');
      drupal_exit();
    }

    $mollom = mollom();
    $tracking = $mollom->getTrackingImage();
    drupal_json_output($tracking);
  }

  /**
   * Send feedback to Mollom.
   *
   * @param $data
   *   A Mollom data record containing one or both of:
   *   - contentId: The content ID to send feedback for.
   *   - captchaId: The CAPTCHA ID to send feedback for.
   * @param $reason
   *   The feedback to send, one of 'spam', 'profanity', 'quality', 'unwanted',
   *   'approve'.
   * @param $type
   *   The type of feedback, one of 'moderate' or 'flag'.
   * @param $source
   *   An optional single word string identifier for the user interface source.
   *   This is tracked along with the feedback to provide a more complete picture
   *   of how feedback is used and submitted on the site.
   */
  public static function _mollom_send_feedback($data, $reason = 'spam', $type = 'moderate', $source = NULL) {
    global $user;
    $params = array();
    if (!empty($data->captchaId)) {
      $params['captchaId'] = $data->captchaId;
      $resource = 'CAPTCHA';
      $id = $data->captchaId;
    }
    // In case we also have a contentId, also pass that, and override $resource
    // and $id for the log message.
    if (!empty($data->contentId)) {
      $params['contentId'] = $data->contentId;
      $resource = 'content';
      $id = $data->contentId;
    }
    if (!isset($id)) {
      return FALSE;
    }
    $params += array(
      'reason' => $reason,
      'type' => $type,
      'authorIp' => ip_address(),
    );
    if (!empty($source)) {
      $params['source'] = $source;
    }
    if ($user->uid > 0) {
      $params['authorId'] = $user->uid;
      // Passing the user rather than account object because only the uid property
      // is used by _mollom_get_openid.
      $authorOpenId = _mollom_get_openid($user);
      if (!empty($authorOpenId)) {
        $params['authorOpenId'] = $authorOpenId;
      }
    }

    $result = mollom()->sendFeedback($params);
    mollom_log(array(
      'message' => 'Reported %feedback for @resource %id from %source - %type.',
      'arguments' => array(
        '%type' => $type,
        '%feedback' => $reason,
        '@resource' => $resource,
        '%id' => $id,
        '%source' => $source,
      ),
    ));
    return $result;
  }

  /**
   * Helper function to add field form element mappings for fieldable entities.
   *
   * May be used by hook_mollom_form_info() implementations to automatically
   * populate the 'elements' definition with attached text fields on the entity
   * type's bundle.
   *
   * @param array $form_info
   *   The basic information about the registered form. Taken by reference.
   * @param string $entity_type
   *   The entity type; e.g., 'node'.
   * @param string $bundle
   *   The entity bundle name; e.g., 'article'.
   *
   * @return void
   *   $form_info is taken by reference and enhanced with any attached field
   *   mappings; e.g.:
   *   @code
   *     $form_info['elements']['field_name][und][0][value'] = 'Field label';
   *   @endcode
   */
  public static function mollom_form_info_add_fields(&$form_info, $entity_type, $bundle) {
    if (!$entity_info = \Drupal::entityManager()->getDefinition($entity_type)) {
      return;
    }
    $form_info['mapping']['post_id'] = $entity_info->getKeys()['id'];

    if ($entity_info instanceof FieldableEntityInterface) {
      $field_instances = \Drupal::entityManager()->getFieldDefinitions($entity_type, $bundle);

      // Add form element mappings for any text fields attached to the bundle.
      foreach ($field_instances as $field_name => $field) {
        if (in_array($field->getType(), array('string', 'email', 'uri', 'string_long', 'text_with_summary'))) {
          $form_info['elements'][$field_name] = \Drupal\Component\Utility\String::checkPlain(t($field->getLabel()));
        }
      }
    }
  }
}
