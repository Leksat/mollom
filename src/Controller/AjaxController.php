<?php /**
 * @file
 * Contains \Drupal\mollom\Controller\DefaultController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the mollom module.
 */
class AjaxController extends ControllerBase {


  /**
   * AJAX callback to retrieve a CAPTCHA.
   *
   * @param $type
   *   The new CAPTCHA type to retrieve, e.g. 'image' or 'audio'.
   * @param $form_build_id
   *   The internal form build id of the form to update the CAPTCHA for.
   * @param $contentId
   *   (optional) The associated content ID in the form.
   *
   * @return
   *   A JSON array containing:
   *   - content: The HTML markup for the new CAPTCHA.
   *   - captchaId: The ID for the new CAPTCHA.
   *
   * @todo Add error handling.
   */
  public static function captchaJs($type, $form_build_id, $contentId = NULL) {
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

    // Load $form_state from cache or create a dummy state.
    $cid = 'form_state_' . $form_build_id;
    if ($cache = cache_get($cid, 'cache_form')) {
      $form_state = $cache->data;
    }
    else {
      $form_state['mollom'] = array();
      if (isset($contentId)) {
        $form_state['values']['mollom']['contentId'] = $contentId;
      }
    }
    $form_state['mollom']['captcha_type'] = $type;
    $captcha = mollom_get_captcha($form_state);

    if (!empty($form_state['mollom']['response']['captcha']['id'])) {
      // Update the CAPTCHA ID in the cached $form_state, since it might have
      // changed.
      // @todo Replace the entire CAPTCHA switch/refresh with new AJAX framework
      //   functionality.
      if ($cache) {
        cache_set($cid, $form_state, 'cache_form', REQUEST_TIME + 21600);
      }
      // Return new content and CAPTCHA ID via JSON.
      $data = array(
        'content' => $captcha,
        'captchaId' => $form_state['mollom']['response']['captcha']['id'],
      );
      drupal_json_output($data);
    }
    drupal_exit();
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
  public static function flagAccess($type, $entity) {
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

  /**
   * Callback handler for public users to report content as inappropriate.
   * This is step one of the two-step process.  The user can now indicate the
   * reason for the report as one of spam, quality, profanity, or unwelcome.
   *
   * @param $type
   *   The request type submitted, one of "ajax" or "nojs".
   * @param $entity
   *   The type of entity that is being reported.
   * @param $id
   *   The entity identifier that is being reported.
   * @param $source
   *   The optional internal source to be submitted along with feedback.
   */
  public static function flag($type, $entity, $id, $source = NULL) {
    $detail = FALSE;
    if ($type === 'nojs' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
      $detail = TRUE;
    }
    $form = drupal_get_form('mollom_flag_reason_form', $entity, $id, $detail, $source);

    // If not submitted via Ajax post, then return a plain Drupal form page.
    if ($detail) {
      return $form;
    }

    $dialog = mollom_flag_dialog_check();
    if (isset($dialog['display form callback'])) {
      $function = $dialog['display form callback'];
      return $function($form, t('Report'));
    }

    // Deliver via custom Mollom dialog.
    $commands = array();
    $formHtml = '<div class="mollom-flag-container" role="dialog" aria-label="' . t('Report') . '">' . render($form) . '</div>';
    $commands[] = ajax_command_prepend(".mollom-flag-content-$entity:has(#mollom_$entity$id)",$formHtml);
    $page = array('#type' => 'ajax', '#commands' => $commands);
    ajax_deliver($page);
  }

}
