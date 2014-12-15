<?php

/**
 * @file
 * Contains \Drupal\mollom\Form\FormController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\mollom\Utility\Logger;
use Drupal\mollom\Utility\Mollom;
use Drupal\Core\Form\FormState;

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
  public static function mollom_form_get_values(FormState $form_state, $fields, $mapping) {
    global $user;

    // @todo Unless mollom_form_submit() directly attempts to retrieve 'postId'
    //   from $form_state['values'], the resulting content properties of this
    //   function cannot be cached.

    // Remove all button values from $form_state['values'].
    $form_values = $form_state->getValues();
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
        $value = Mollom::_mollom_flatten_form_values($value);
        if (is_array($value)) {
          $value = implode(' ', $value);
        }
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
    $data['authorIp'] = \Drupal::request()->getClientIp();

    // Honeypot.
    // For the Mollom backend, it only matters whether 'honeypot' is non-empty.
    // The submitted value is only taken over to allow site administrators to
    // see the actual honeypot value in watchdog log entries.
    if (isset($form_values->mollom['homepage']) && $form_values->mollom['homepage'] !== '') {
      $data['honeypot'] = $form_values->mollom['homepage'];
    }

    // Ensure that all $data values contain valid UTF-8. Invalid UTF-8 would be
    // sanitized into an empty string, so the Mollom backend would not receive
    // any value.
    $invalid_utf8 = FALSE;
    $invalid_xml = FALSE;
    // Include the CAPTCHA solution user input in the UTF-8 validation.
    $solution = isset($form_values->mollom['captcha']) ? array('solution' => $form_values->mollom['captcha']) : array();
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
      $form_state->setErrorByName('', t('Your submission contains invalid characters and will not be accepted.'));

      $message = String::format('Invalid !type in form values: %teaser', array('!type' => $invalid_utf8 ? 'UTF-8' : 'XML characters'));
      \Drupal::logger('mollom')->notice($message);
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

    /*if ($reset) {
      // This catches both 'mollom:form_cache' as well as mollom_form_load()'s
      // 'mollom:form:*' entries.
      //cache_clear_all('mollom:form', 'cache', TRUE);
      unset($forms);
      return true;
    }

    if (isset($forms)) {
      return $forms;
    }*/

    //if ($cache = cache_get('mollom:form_cache')) {
    //  $forms = $cache->data;
    //  return $forms;
    //}

    // Get all forms that are protected
    $forms = array();
    $form_ids = array_keys(\Drupal\mollom\Entity\Form::loadMultiple());
    $forms['protected'] = array_combine($form_ids, $form_ids);

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
        //$form_info = mollom_form_info($form_id, $info['module']);
        if (isset($form_info['mapping']['post_id'])) {
          $forms['delete'][$info['delete form']] = $form_id;
        }
      }
    }
    //cache_set('mollom:form_cache', $forms);

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

    $mollom = \Drupal::service('mollom.client');
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
      $authorOpenId = Mollom::_mollom_get_openid($user);
      if (!empty($authorOpenId)) {
        $params['authorOpenId'] = $authorOpenId;
      }
    }

    /** @var \Drupal\mollom\API\DrupalClient $mollom */
    $mollom = \Drupal::service('mollom.client');
    $result =  $mollom->sendFeedback($params);
    Logger::addMessage(array(
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

  /**
   * Form validation handler for Mollom's CAPTCHA form element.
   *
   * Validates whether a CAPTCHA was solved correctly. A form may contain a
   * CAPTCHA, if it was configured to be protected by a CAPTCHA only, or when the
   * text analysis result is "unsure".
   */
  public static function mollom_validate_captcha(&$form, FormState $form_state) {
    /** @var \Drupal\mollom\Entity\Form $mollom_form */
    $mollom_form = $form_state->getValue('mollom_form');

    if ($mollom_form->mode == \Drupal\mollom\Entity\FormInterface::MOLLOM_MODE_ANALYSIS) {
      // For text analysis, only validate the CAPTCHA if there is an ID. If the ID
      // is maliciously removed from the form values, text analysis will punish
      // the author's reputation and present a new CAPTCHA to solve.
      if (empty($form_state->getValue('mollom')['captchaId'])) {
        return FALSE;
      }
    }
    else {
      // Otherwise, this form is protected with a CAPTCHA only, unless disabled by
      // another module.
      if (!$form_state->get('mollom')['require_captcha']) {
        return FALSE;
      }
      // If there is no CAPTCHA ID yet, retrieve one and throw an error.
      if (empty($form_state->getValue('mollom')['captchaId'])) {
        if (FormController::mollom_form_add_captcha($form, $form_state)) {
          $form_state->setErrorByName('captcha', t('To complete this form, please complete the word verification below.'));
        }
        return FALSE;
      }
    }

    // Inform text analysis validation that a CAPTCHA was validated, so the
    // appropriate error message can be output.
    $form_state->set(array('temporary' => array('mollom' => 'had_captcha')), TRUE);

    // $form_state['mollom']['passed_captcha'] may only ever be set by this
    // validation handler and must not be changed elsewhere.
    // This only becomes TRUE for CAPTCHA-only protected forms, for which the
    // CAPTCHA state is locally tracked in $form_state. For text analysis, the
    // primary 'require_captcha' condition will not be TRUE unless needed in the
    // first place.
    if ($form_state->get('mollom')['passed_captcha']) {
      $form['mollom']['captcha']['#access'] = FALSE;
      $form['mollom']['captcha']['#solved'] = TRUE;
      return FALSE;
    }

    // Check the CAPTCHA result.
    // Next to the Mollom session id and captcha result, the Mollom back-end also
    // takes into account the author's IP and local user id (if registered). Any
    // other values are ignored.
    $all_data = self::mollom_form_get_values($form_state, $mollom_form->enabled_fields, $mollom_form->mapping);
    // Cancel processing upon invalid UTF-8 data.
    if ($all_data === FALSE) {
      return FALSE;
    }
    $data = array(
      'id' => $form_state->getValue('mollom')['captchaId'],
      'solution' => $form_state->getValue('mollom')['captcha'],
      'authorIp' => $all_data['authorIp'],
    );
    if (isset($all_data['authorId'])) {
      $data['authorId'] = $all_data['authorId'];
    }
    if (isset($all_data['authorCreated'])) {
      $data['authorCreated'] = $all_data['authorCreated'];
    }
    if (isset($all_data['honeypot'])) {
      $data['honeypot'] = $all_data['honeypot'];
    }
    /** @var \Drupal\mollom\API\DrupalClient $mollom */
    $mollom = \Drupal::service('mollom.client');
    $result = $mollom->checkCaptcha($data);
    // Use all available data properties for log messages below.
    $data += $all_data;

    // Handle the result, unless it is FALSE (bogus CAPTCHA ID input).
    if ($result !== FALSE) {
      // Trigger global fallback behavior if there is a unexpected result.
      if (!is_array($result) || !isset($result['id'])) {
        return Mollom::_mollom_fallback();
      }

      // Store the response for #submit handlers.
      $form_state->set(array('mollom' => array('response' => 'captcha')), $result);
      // Set form values accordingly. Do not overwrite the entity ID.
      // @todo Rename 'id' to 'entity_id'.
      $result['captchaId'] = $result['id'];
      unset($result['id']);
      $form_state->setValue('mollom', array_merge($form_state->getValue('mollom'), $result));

      // Ensure the latest CAPTCHA ID is output as value.
      // form_set_value() is effectless, as this is not a element-level but a
      // form-level validation handler.
      $form['mollom']['captchaId']['#value'] = $result['captchaId'];
    }

    if (!empty($result['solved'])) {
      // For text analysis, remove the CAPTCHA ID from the output if it was
      // solved, so this validation handler does not run again.
      $require_analysis = $form_state->getValue(array('mollom', 'require_analysis'));
      if ($require_analysis) {
        $form['mollom_data']['captchaId']['#value'] = '';
      }
      $form_state->setValue(array('mollom_data', 'passed_captcha'), TRUE);
      $form['mollom_data']['captcha']['#access'] = FALSE;
      $form['mollom_data']['captcha']['#solved'] = TRUE;

      Logger::addMessage(array(
        'message' => 'Correct CAPTCHA',
      ), RfcLogLevel::INFO);
    }
    else {
      // Text analysis will re-check the content and may trigger a CAPTCHA on its
      // own again (not guaranteed).
      $require_analysis = $form_state->getValue(array('mollom_data', 'require_analysis'));
      if (!$require_analysis) {
        $form_state->setErrorByName('mollom][captcha', t('The word verification was not completed correctly. Please complete this new word verification and try again.') . ' ' . _mollom_format_message_falsepositive($form_state, $data));
        //mollom_form_add_captcha($form['mollom'], $form_state);
        // @todo Fix mollom_form_add_captcha here
        drupal_set_message("I would set a captcha here if I were you...", 'error');
      }

      Logger::addMessage(array(
        'message' => 'Incorrect CAPTCHA',
      ));
    }
  }

  /**
   * Form validation handler to perform textual analysis on submitted form values.
   */
  public static function mollom_validate_analysis(&$form, FormState $form_state) {
    /** @var \Drupal\mollom\Entity\Form $mollom_form */
    $mollom_form = $form_state->getValues();

    if (!$mollom_form->mode == \Drupal\mollom\Entity\FormInterface::MOLLOM_MODE_ANALYSIS) {
      return false;
    }

    // Perform textual analysis.
    // @todo Add Mapping, see Drupal 7 for example
    $all_data = self::mollom_form_get_values($form_state, $mollom_form->enabled_fields, $mollom_form->mapping);
    // @ todo : not all fields are available here yet
    // Cancel processing upon invalid UTF-8 data.
    if ($all_data === FALSE) {
      return false;
    }
    $data = $all_data;
    // Remove postId property; only used by mollom_form_submit().
    if (isset($data['postId'])) {
      unset($data['postId']);
    }
    $contentId = $form_state->getValue(array('mollom_data', 'contentId', ''));
    if (!empty($contentId)) {
      $data['id'] = $contentId;
    }
    if (is_array($mollom_form->checks)) {
      $checks_to_add = array();
      foreach ($mollom_form->checks as $check_id => $check) {
        if ($check_id == $check) {
          $checks_to_add[] = $check;
        }
      }
      if (is_array($checks_to_add)) {
        $data['checks'] = $checks_to_add;
      }
    }
    $data['strictness'] = $mollom_form->strictness;
    if (isset($mollom_form->type)) {
      $data['type'] = $mollom_form->type;
    }
    if (in_array('spam', $data['checks']) && $mollom_form->unsure == 'binary') {
      $data['unsure'] = 0;
    }
    // Only pass the tracking id if this is the first textual evaluation.
    if (isset($mollom_form->fba) && empty($data['id'])) {
      if (empty($mollom_form->fba)) {
        $data['trackingImageId'] = -1;
      }
      else {
        $data['trackingImageId'] = $mollom_form->fba;
      }
    }

    /** @var \Drupal\mollom\API\DrupalClient $mollom */
    $mollom = \Drupal::service('mollom.client');
    //dpm($data);
    $result = $mollom->checkContent($data);
    //dpm($result);

    // Use all available data properties for log messages below.
    $data += $all_data;

    // Trigger global fallback behavior if there is a unexpected result.
    if (!is_array($result) || !isset($result['id'])) {
      return Mollom::_mollom_fallback();
    }

    // Set form values accordingly. Do not overwrite the entity ID.
    // @todo Rename 'id' to 'entity_id'.
    $result['contentId'] = $result['id'];
    unset($result['id']);

    // Store the response returned by Mollom.
    $form_state->setValue(array('mollom_data', 'response', 'content'), $result);

    // Ensure the latest content ID is output as value.
    // form_set_value() is effectless, as this is not a element-level but a
    // form-level validation handler.
    $form['mollom']['contentId']['#value'] = $result['contentId'];

    // Prepare watchdog message teaser text.
    $teaser = '--';
    if (isset($data['postTitle'])) {
      $teaser = Unicode::truncate(strip_tags($data['postTitle']), 40);
    }
    elseif (isset($data['postBody'])) {
      $teaser = Unicode::truncate(strip_tags($data['postBody']), 40);
    }

    // Handle the profanity check result.
    if (isset($result['profanityScore']) && $result['profanityScore'] >= 0.5) {
      if ($form_state->getValue(array('mollom_data', 'discard'), FALSE)) {
        $form_state->setErrorByName('mollom', t('Your submission has triggered the profanity filter and will not be accepted until the inappropriate language is removed.'));
      }
      else {
        $form_state->set(array('mollom' => 'require_moderation'), TRUE);
      }
      Logger::addMessage(array(
        'message' => 'Profanity: %teaser',
        'arguments' => array('%teaser' => $teaser),
      ));
    }

    // Handle the spam check result.
    // The Mollom API takes over state tracking for each content ID/session. The
    // spamClassification will usually turn into 'ham' after solving a CAPTCHA.
    // It may also change to 'spam', if the user replaced the values with very
    // spammy content. In any case, we always do what we are told to do.
    // Note: The returned spamScore may diverge from the spamClassification.
    $form_state->set(array('mollom' => 'require_captcha'), FALSE);
    $form['mollom']['captcha']['#access'] = FALSE;

    if (isset($result['spamClassification'])) {
      switch ($result['spamClassification']) {
        case 'ham':
          $message = String::format('Ham: %teaser', array('%teaser' => $teaser));
          \Drupal::logger('mollom')->notice($message);
          break;

        case 'spam':
          if ($mollom_form->discard) {
            $form_state->setErrorByName('mollom', t('Your submission has triggered the spam filter and will not be accepted.') . ' ' . Mollom::_mollom_format_message_falsepositive($form_state, $data));
          }
          else {
            $form_state->setValue(array('mollom_data', 'require_moderation'), TRUE);
          }
          $message = String::format('Spam: %teaser', array('%teaser' => $teaser));
          \Drupal::logger('mollom')->notice($message);
          break;

        case 'unsure':
          //dpm('I would add a Captcha!!!!');
          if ($mollom_form->unsure == 'moderate') {
            $form_state->setValue(array('mollom_data', 'require_moderation'), TRUE);
          }
          else {
            $form_state->setValue(array('mollom_data', 'passed_captcha'), TRUE);
            $form_state->setValue(array('mollom_data', 'require_captcha'), TRUE);
            // @todo Fix the captcha stuff
            // Retrieve a new CAPTCHA and throw an error.
            if (FormController::mollom_form_add_captcha($form, $form_state)) {
              $form['mollom']['captcha']['#access'] = TRUE;

              $had_captcha = $form_state->getValue(array('temporary', 'mollom', 'had_captcha'), FALSE);
              if (!empty($had_captcha)) {
                $form_state->setErrorByName('captcha', t('The word verification was not completed correctly. Please complete this new word verification and try again.') . ' ' . _mollom_format_message_falsepositive($form_state, $data));
              }
              else {
                $form_state->setErrorByName('captcha', t('To complete this form, please complete the word verification below.'));
              }
            }
          }
          $message = String::format('Unsure: %teaser', array('%teaser' => $teaser));
          \Drupal::logger('mollom')->notice($message);
          break;
        case 'unknown':
        default:
          // If we end up here, Mollom responded with a unknown spamClassification.
          // Normally, this should not happen, but if it does, log it. As there
          // could be multiple reasons for this, it is not safe to trigger the
          // fallback mode.
        $message = String::format('Unknown: %teaser', array('%teaser' => $teaser));
        \Drupal::logger('mollom')->notice($message);
          break;
      }
    }
    // Prevent the CAPTCHA element from being rendered, in case the form will be
    // rebuilt after submission (e.g., comment preview).
    // Unless text analysis was unsure, no CAPTCHA ID is required. But a previous
    // submission attempt might have been unsure. If this submit will pass
    // validation, then the rebuilt form will have no indication that it passed
    // analysis and will be auto-populated with values from $form_state['input'].
    $require_captcha = $form_state->getValue(array('mollom_form', 'require_captcha'), FALSE);
    if (!$require_captcha) {
      $form_state->setValue(array('mollom_data', 'captchaId'), '');
    }
  }

  /**
   * Form validation handler to perform post-validation tasks.
   */
  public static function mollom_validate_post(&$form, FormState $form_state) {
    // Retain a post instead of discarding it. If 'discard' is FALSE, then the
    // 'moderation callback' is responsible for altering $form_state in a way that
    // the post ends up in a moderation queue. Most callbacks will only want to
    // set or change a value in $form_state.
    $require_moderation = $form_state->getValue(array('mollom_form', 'require_moderation'), FALSE);
    if ($require_moderation) {
      $form_state->setValue(array('mollom_data', 'moderate'), 1);

      $function = $form_state->getValue(array('mollom_form', 'moderation callback'));
      if (!empty($function) && is_callable($function)) {
        $function($form, $form_state);
      }
    }
  }

  /**
   * Form submit handler to flush Mollom session and form information from cache.
   *
   * @todo Check whether this is still needed with mollom_entity_insert(). For
   *   entity forms, this approach never really worked, since:
   *   - The primary submit handler fails to set the new ID of a newly stored
   *     entity in the submitted form values (which has been standardized in core,
   *     but is not enforced anywhere), so the postId cannot be extracted from
   *     submitted form values.
   *   - This submit handler is invoked too early, before the primary submit
   *     handler processed and saved the entity, so the postId cannot be extracted
   *     from submitted form values.
   *   - This submit handler is invoked too late; the primary submit handler might
   *     send out e-mails directly after saving the entity (e.g.,
   *     user_register_form_submit()), so mollom_mail_alter() is invoked before
   *     Mollom session data has been saved.
   */
  public static function mollom_form_submit($form, FormState $form_state) {
    // Some modules are implementing multi-step forms without separate form
    // submit handlers. In case we reach here and the form will be rebuilt, we
    // need to defer our submit handling until final submission.
    $is_rebuilding = $form_state->isRebuilding();
    if ($is_rebuilding) {
      return;
    }

    /** @var \Drupal\mollom\Entity\Form $mollom_form */
    $mollom_form = $form_state->getValue('mollom_form');

    // If an 'entity' and a 'post_id' mapping was provided via
    // hook_mollom_form_info(), try to automatically store Mollom session data.
    if (!empty($mollom_form) && isset($mollom_form->mapping['post_id'])) {
      // For new entities, the entity's form submit handler will have added the
      // new entity id value into $form_state['values'], so we need to rebuild the
      // data mapping. We do not care for the actual fields, only for the value of
      // the mapped postId.
      // @todo Directly extract 'postId' from submitted form values.
      $values = self::mollom_form_get_values($form_state, $mollom_form->enabled_fields, $mollom_form->mapping);
      // We only consider non-empty and non-zero values as valid entity ids.
      if (!empty($values['postId'])) {
        // Save the Mollom session data.
        $data = $form_state->getValue('mollom_data');
        $data['id'] = $values['postId'];
        // Set the moderation flag for forms accepting bad posts.
        $data['moderate'] = $form_state->getValue(array('mollom_data', 'require_moderation'));
        $stored_data = Mollom::mollom_data_save($data);
        $form_state->setValue(array('mollom_data', 'data'), $stored_data);
      }
    }
  }

  /**
   * Adds a Mollom CAPTCHA to a Mollom-protected form.
   *
   * @param $element
   *   The form element structure contained in $form['mollom']. Passed by
   *   reference.
   * @param $form_state
   *   The current state of the form. Passed by reference.
   *
   * @return bool
   *   TRUE if a CAPTCHA was added, FALSE if not.
   */
  public function mollom_form_add_captcha(&$form, FormStateInterface $form_state) {
    // Prevent the page cache from storing a form containing a CAPTCHA element.
    \Drupal::service('page_cache_kill_switch')->trigger();


    $captcha = FormController::mollom_get_captcha($form_state);
    //dpm($captcha);
    // If we get a response, add the image CAPTCHA to the form element.
    if (!empty($captcha)) {
      $form['captcha']['#access'] = TRUE;
      $form['captcha']['#field_prefix'] = $captcha;
      $form['captcha']['#attributes'] = array('title' => t('Enter the characters from the verification above.'));
      // @todo Fix the captcha script addition
      //FormController::mollom_attach_captcha_script($element['captcha']);

      // Ensure that the latest CAPTCHA ID is output as value.
      $form['captchaId']['#value'] = $form_state->getValue(array('mollom_data', 'response', 'captcha', 'id'));
      $form_state->setValue(array('mollom_data', 'captchaId'), $form_state->getValue(array('mollom_data', 'response', 'captcha', 'id')));
      return TRUE;
    }
    // Otherwise, we have a communication or configuration error.
    else {
      $element['captcha']['#access'] = FALSE;
      // Trigger fallback mode.
      Mollom::_mollom_fallback();
      return FALSE;
    }
  }

  /**
   * Attach SWFObject script to render element when available.
   *
   * @param $element
   *   A render element to attach the script to.
   *
   * @return bool
   *   True if the library can be found, false otherwise.
   */
  public function mollom_attach_captcha_script(&$element = NULL) {
    $libraries = &drupal_static(__FUNCTION__);
    if (empty($libraries['swfobject'])) {
      $lib = array(
        'found' => FALSE,
      );

      // Try to load via libraries module if enabled.
      if (module_exists('libraries') && function_exists('libraries_detect ')) {
        if (($library = libraries_detect('swfobject')) && !empty($library['installed'])) {
          $lib = array(
            'found' => TRUE,
            'libraries' => TRUE,
          );
        }
      }
      if (!$lib['found']) {
        // Check for SWFObject in standard library locations.
        $profile = drupal_get_path('profile', drupal_get_profile());
        $config = conf_path();
        $search = array(
          'libraries',
          "$profile/libraries",
          "sites/all/libraries",
          "$config/libraries",
        );
        foreach ($search as $dir) {
          if (is_dir($dir) && (
              file_exists("$dir/swfobject.js") || file_exists("$dir/swfobject/swfobject.js")
            )) {
            $lib = array(
              'found' => TRUE,
              'libraries' => FALSE,
              'path' => file_exists("$dir/swfobject.js") ? "$dir/swfobject.js" : "$dir/swfobject/swfobject.js",
            );
            break;
          }
        }
      }
      $libraries['swfobject'] = $lib;
    }
    if ($libraries['swfobject']['found']) {
      if (isset($element)) {
        if ($libraries['swfobject']['libraries']) {
          $element['#attached']['libraries_load'][] = array('swfobject');
        }
        else {
          $element['#attached'] = array(
            'js' => array($libraries['swfobject']['path']),
          );
        }
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the HTML markup for a Mollom CAPTCHA.
   *
   * @param $form_state
   *   The current state of a form.
   *
   * @return string
   *   The markup of the CAPTCHA HTML.
   */
  public static function mollom_get_captcha(FormStateInterface $form_state) {
    // @todo Re-use existing CAPTCHA URL when the Mollom server response for
    //   verifying a CAPTCHA solution returns the existing URL.
    $mollom_form = $form_state->getValue('mollom_form');
    $data = array(
      'type' => $form_state['mollom']['captcha_type'],
      'ssl' => (int) (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'),
    );
    if (!empty($form_state['values']['mollom']['contentId'])) {
      $data['contentId'] = $form_state['values']['mollom']['contentId'];
    }
    /** @var \Drupal\mollom\API\DrupalClient $mollom */
    $mollom = \Drupal::service('mollom.client');
    //$result = $mollom->createCaptcha($data);

    /*// Add a log message to prevent the request log from appearing without a
    // message on CAPTCHA-only protected forms.
    \Drupal::logger('mollom')->notice('Retrieved new CAPTCHA');

    if (is_array($result) && isset($result['url'])) {
      $url = $result['url'];
      $form_state['mollom']['response']['captcha'] = $result;
    }
    else {
      return '';
    }
    // Theme CAPTCHA output and return.
    $audio_enabled = \Drupal::config('mollom.settings')->get('mollom_audio_captcha_enabled', 1);
    $renderer = \Drupal::service('renderer');
    if ($audio_enabled && $form_state['mollom']['captcha_type'] == 'audio') {
      $audio_captcha = array(
        '#type' => 'mollom_captcha_audio',
        '#captcha_url' => $url,
      );
      return $renderer->render($audio_captcha);
    }
    else {
      $image_captcha = array(
        '#type' => 'mollom_captcha_image',
        '#captcha_url' => $url,
        '#audio_enabled' => $audio_enabled,
      );
      return $renderer->render($image_captcha);
    }*/
  }
}
