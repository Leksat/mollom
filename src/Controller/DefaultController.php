<?php /**
 * @file
 * Contains \Drupal\mollom\Controller\DefaultController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the mollom module.
 */
class DefaultController extends ControllerBase {

/**
 * Menu access callback; Determine access to report to Mollom.
 *
 * The special $entity type "session" may be used for mails and messages, which
 * originate from form submissions protected by Mollom, and can be reported by
 * anyone; $id is expected to be a Mollom session id instead of an entity id
 * then.
 *
 * @param $entity
 *   The entity type of the data to report.
 * @param $id
 *   The entity id of the data to report.
 *
 * @todo Revamp this based on new {mollom}.form_id info.
 */
function reportAccess($entity, $id) {
  // The special entity 'session' means that $id is a Mollom session_id, which
  // can always be reported by everyone.
  if ($entity == 'session') {
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
        if (\Drupal::currentUser()->hasPermission($permission)) {
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
 * Access callback; check if the module is configured.
 *
 * This function does not actually check whether Mollom keys are valid for the
 * site, but just if the keys have been entered.
 *
 * @param $permission
 *   An optional permission string to check with \Drupal::currentUser()->hasPermission().
 *
 * @return
 *   TRUE if the module has been configured and \Drupal::currentUser()->hasPermission() has been checked,
 *   FALSE otherwise.
 */
function access($permission = FALSE) {
  return \Drupal::config('mollom.settings')->get('mollom_public_key') && \Drupal::config('mollom.settings')->get('mollom_private_key') && (!$permission || \Drupal::currentUser()->hasPermission($permission));
}

/**
 * AJAX callback to retrieve a CAPTCHA.
 *
 * @param $type
 *   The new CAPTCHA type to retrieve, e.g. 'image' or 'audio'.
 * @param $form_build_id
 *   The internal form build id of the form to update the CAPTCHA for.
 * @param $mollom_session_id
 *   The last known Mollom session id contained in the form.
 *
 * @return
 *   A JSON array containing:
 *   - content: The HTML markup for the new CAPTCHA.
 *   - session_id: The Mollom session id for the new CAPTCHA.
 *
 * @todo Add error handling.
 */
function captchaJs($type, $form_build_id, $mollom_session_id) {
  $captcha = mollom_get_captcha($type, array('session_id' => $mollom_session_id));

  // Update cached session id in the cached $form_state.
  // We rely on native form caching of Form API to store our Mollom session
  // data. When retrieving a new CAPTCHA through this JavaScript callback, the
  // cached $form_state is not updated and still contains the Mollom session
  // data that was known when rendering the form. Since above XML-RPC requests
  // may return a new Mollom session id for the new CAPTCHA, the user would not
  // be able to successfully complete the CAPTCHA, because we would try to
  // validate the user's response in combination with the old/previous session
  // id. Therefore, we need to update the session id in the cached $form_state.
  // @todo Replace the entire CAPTCHA switch/refresh with new AJAX framework
  //   functionality.
  if (!empty($captcha['response']['session_id'])) {
    if ($cache = cache_get('form_state_' . $form_build_id, 'cache_form')) {
      $form_state = $cache->data;
      $form_state['mollom']['response']['session_id'] = $captcha['response']['session_id'];
      cache_set('form_state_' . $form_build_id, $form_state, 'cache_form', REQUEST_TIME + 21600);
      // After successfully updating the cache, replace the original session id.
      $mollom_session_id = $captcha['response']['session_id'];
    }
  }

  // Return new content and new session_id via JSON.
  $data = array(
    'content' => $captcha['markup'],
    'session_id' => $mollom_session_id,
  );
  drupal_json_output($data);
  drupal_exit();
}

}
