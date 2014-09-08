<?php
/**
 * @file contains Drupal\mollom\Entity\ProtectableForms.
 */

namespace Drupal\mollom\Entity;

/**
 * Handles retrieval of information about forms that are configured to be
 * protected by Mollom (due to being registered via hooks).
 */
class ProtectableForms {

  /**
   * Returns a list of protectable forms registered via hook_mollom_form_list().
   *
   * @return array
   *   An array of form information keyed by the form id.
   *
   * @see hook_mollom_form_list().
   */
  static public function getFormList() {
    $module_handler = \Drupal::moduleHandler();
    foreach ($module_handler->getImplementations('mollom_form_list') as $module) {
      $module_forms = $module_handler->invoke($module, 'mollom_form_list');
      foreach ($module_forms as $form_id => $info) {
        $form_list[$form_id] = $info;
        $form_list[$form_id] += array(
          'form_id' => $form_id,
          'module' => $module,
        );
      }
    }

    // Allow modules to alter the form list.
    $module_handler->alter('mollom_form_list', $form_list);

    return $form_list;
  }

  /**
   * Returns a list of form options as an array suitable for administrative
   * options usage.
   *
   * @return array
   *   An array of forms keyed by id and with the display name as the value.
   */
  static public function getAdminFormOptions() {
    // Get a list of all available forms.
    $form_list = self::getFormList();

    // Remove any forms that are already configured.
    $storage = \Drupal::entityManager()->getStorage('mollom_form');
    $protected_forms = $storage->loadMultiple();
    foreach ($protected_forms as $id => $entity) {
      unset($form_list[$entity->mollom_form_id]);
    }

    if (empty($form_list)) {
      return array();
    }

    // Load module information
    $module_info = system_get_info('module');

    // Transform form information into an associative array suitable for usage
    // within administrative forms.
    $translation = \Drupal::translation();
    $options = array();
    foreach ($form_list as $form_id => $info) {
      // system_get_info() only supports enabled modules. Default to the
      // module's machine name in case it is disabled.
      $module = $info['module'];
      if (!isset($module_info[$module])) {
        $module_info[$module]['name'] = $module;
      }
      $options[$form_id] = $translation->translate('!module: !form-title', array(
        '!form-title' => $info['title'],
        '!module' => $translation->translate($module_info[$module]['name']),
      ));
    }

    // Sort form options by title.
    asort($options);

    return $options;
  }

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
  static public function getFormInfo($form_id, $module, $form_list = NULL) {
    $module_handler = \Drupal::moduleHandler();

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
      $form_list = $module_handler->invoke($module, 'mollom_form_list');
    }
    // If it is not listed, then the form has vanished.
    if (!isset($form_list[$form_id])) {
      return $form_info;
    }
    $module_form_info = $module_handler->invoke($module, 'mollom_form_info', $form_id);
    // If no form info exists, then the form has vanished.
    if (!isset($module_form_info)) {
      return $form_info;
    }
    unset($form_info['orphan']);

    // Any information in hook_mollom_form_info() overrides the list info.
    $form_info = array_merge($form_info, $form_list[$form_id]);
    $form_info = array_merge($form_info, $module_form_info);

    // Allow modules to alter the default form information.
    $module_handler->alter('mollom_form_info', $form_info, $form_id);

    return $form_info;
  }
} 
