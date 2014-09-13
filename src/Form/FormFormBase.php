<?php

/**
 * @file
 * Contains Drupal\mollom\Form\FormFormBase.
 */

namespace Drupal\mollom\Form;

use Drupal\Core\Routing\RequestHelper;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mollom\Entity\FormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Drupal\mollom\Controller\FormController;

/**
 * Class FormFormBase.
 *
 * Typically, we need to build the same form for both adding a new entity,
 * and editing an existing entity. Instead of duplicating our form code,
 * we create a base class. Drupal never routes to this class directly,
 * but instead through the child classes of RobotAddForm and RobotEditForm.
 *
 * @package Drupal\mollom\Form
 *
 * @ingroup mollom
 */
class FormFormBase extends EntityForm {

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQueryFactory;

  /**
   * Construct the FormFormBase.
   *
   * For simple entity forms, there's no need for a constructor. Our mollom form form
   * base, however, requires an entity query factory to be injected into it
   * from the container. We later use this query factory to build an entity
   * query for the exists() method.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   An entity query factory for the robot entity type.
   */
  public function __construct(QueryFactory $query_factory) {
    $this->entityQueryFactory = $query_factory;
  }

  /**
   * Factory method for FormFormBase.
   *
   * When Drupal builds this class it does not call the constructor directly.
   * Instead, it relies on this method to build the new object. Why? The class
   * constructor may take multiple arguments that are unknown to Drupal. The
   * create() method always takes one parameter -- the container. The purpose
   * of the create() method is twofold: It provides a standard way for Drupal
   * to construct the object, meanwhile it provides you a place to get needed
   * constructor parameters from the container.
   *
   * In this case, we ask the container for an entity query factory. We then
   * pass the factory to our class as a constructor parameter.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.query'));
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::form().
   *
   * Builds the entity add/edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An associative array containing the robot add/edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get anything we need form the base class.
    $form = parent::buildForm($form, $form_state);

    // Drupal provides the entity to us as a class variable. If this is an
    // existing entity, it will be populated with existing values as class
    // variables. If this is a new entity, it will be a new object with the
    // class of our entity. Drupal knows which class to call from the
    // annotation on our Robot class.
    /** @var \Drupal\mollom\Entity\Form $mollom_form */
    $mollom_form = $this->entity;

    // Build the form.
    $form['label'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mollom Form'),
      '#maxlength' => 255,
      '#options' => $this->mollom_admin_form_options(),
      '#default_value' => $mollom_form->label(),
      '#required' => TRUE,
      '#ajax' => array(
        'trigger_as' => array('name' => 'formfields_configure'),
        'callback' => array(get_class($this), 'buildAjaxFormFieldsConfigForm'),
        'wrapper' => 'mollom-formfields-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $mollom_form->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exists'),
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".',
      ),
      '#disabled' => !$mollom_form->isNew(),
    );

    $t_args_modes = array(
      '!option' => $this->t('Text analysis'),
      '!recommended' => $this->t('recommended'),
    );

    $modes = array(
      FormInterface::MOLLOM_MODE_ANALYSIS => $this->t('!option <em>(!recommended)</em>', $t_args_modes),
      FormInterface::MOLLOM_MODE_CAPTCHA => t('CAPTCHA only'),
    );

    $form['mode'] = array(
      '#type' => 'radios',
      '#title' => t('Protection mode'),
      '#options' => $modes,
      '#default_value' => isset($mollom_form->mode) ? $mollom_form->mode : key($modes),
    );
    $form['mode'][FormInterface::MOLLOM_MODE_ANALYSIS] = array(
      '#description' => t('Mollom will analyze the post and will only show a CAPTCHA when it is unsure.'),
    );
    $form['mode'][FormInterface::MOLLOM_MODE_CAPTCHA] = array(
      '#description' => t('A CAPTCHA will be shown for every post. Only choose this if there are too few text fields to analyze.'),
    );
    $form['mode'][FormInterface::MOLLOM_MODE_CAPTCHA]['#description'] .= '<br />' . t('Note: Page caching is disabled on all pages containing a CAPTCHA-only protected form.');

    $all_permissions = array();
    foreach (\Drupal::moduleHandler()->getImplementations('permission') as $module) {
      if ($module_permissions = \Drupal::moduleHandler()->invoke($module, 'permission')) {
        foreach ($module_permissions as &$info) {
          $info += array('module' => $module);
        }
        $all_permissions += $module_permissions;
      }
    }

    // Prepend Mollom's global permission to the list.
    //array_unshift($mollom_form['bypass access'], 'bypass mollom protection');

    $permissions = array();
    if (isset($form['bypass access'])) {
      foreach ($form['bypass access'] as $permission) {
        // @todo D7: Array keys are used as CSS class for the link list item,
        //   but are not sanitized: http://drupal.org/node/98696
        $permissions[drupal_html_class($permission)] = array(
          'title' => $all_permissions[$permission]['title'],
          'href' => 'admin/people/permissions',
          'fragment' => 'module-' . $all_permissions[$permission]['module'],
          'html' => TRUE,
        );
      }
    }
    // Theme is available as an element type (may have additional processing in rendering).
    $links = array(
      '#type' => 'links',
      '#links' => $permissions,
      '#attributes' => array(),
    );
    $links_rendered = drupal_render($links);

    $form['mode']['#description'] = t('The protection is omitted for users having any of the permissions: !permission-list', array(
      '!permission-list' => $links_rendered,
    ));

    // If not re-configuring an existing protection, make it the default.
    if (!isset($form['mode'])) {
      $form['mode']['#default_value'] = FormInterface::MOLLOM_MODE_ANALYSIS;
    }

    // Textual analysis filters.
    $form['checks'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Text analysis checks'),
      '#options' => array(
        'spam' => t('Spam'),
        'profanity' => t('Profanity'),
      ),
      '#default_value' => isset($mollom_form->checks) ? $mollom_form->checks : array('spam'),
      '#states' => array(
        'visible' => array(
          '[name="mode"]' => array('value' => (string) FormInterface::MOLLOM_MODE_ANALYSIS),
        ),
      ),
    );


    $form['formfields_config'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'mollom-formfields-config-form',
      ),
      '#tree' => TRUE,
    );

    $form['formfields_configure_button'] = array(
      '#type' => 'submit',
      '#name' => 'formfields_configure',
      '#value' => $this->t('Refresh Fields'),
      '#limit_validation_errors' => array(array('formfields')),
      '#submit' => array(array(get_class($this), 'submitAjaxFormFieldsConfigForm')),
      '#ajax' => array(
        'callback' => array(get_class($this), 'buildAjaxFormFieldsConfigForm'),
        'wrapper' => 'mollom-formfields-config-form',
      ),
      '#attributes' => array('class' => array('js-hide')),
    );

    $this->buildFormFieldsConfigForm($form, $form_state, $mollom_form);

    $form['strictness'] = array(
      '#type' => 'radios',
      '#title' => t('Text analysis strictness'),
      '#options' => array(
        'normal' => t('!option <em>(!recommended)</em>', array(
          '!option' => t('Normal'),
          '!recommended' => $this->t('recommended'),
        )),
        'strict' => t('Strict: Posts are more likely classified as spam'),
        'relaxed' => t('Relaxed: Posts are more likely classified as ham'),
      ),
      '#default_value' => isset($mollom_form->strictness) ? $mollom_form->strictness : 'normal',
      '#states' => array(
        'visible' => array(
          '[name="mode"]' => array('value' => (string) FormInterface::MOLLOM_MODE_ANALYSIS),
        ),
      ),
    );

    $form['unsure'] = array(
      '#type' => 'radios',
      '#title' => t('When text analysis is unsure'),
      '#default_value' => isset($mollom_form->unsure) ? $mollom_form->unsure : 'captcha',
      '#options' => array(
        'captcha' => t('!option <em>(!recommended)</em>', array(
          '!option' => t('Show a CAPTCHA'),
          '!recommended' => $this->t('recommended'),
        )),
        'moderate' => t('Retain the post for manual moderation'),
        'binary' => t('Accept the post'),
      ),
      '#required' => $mollom_form->mode == FormInterface::MOLLOM_MODE_ANALYSIS,
      // Only possible for forms protected via text analysis.
      '#states' => array(
        'visible' => array(
          '[name="mode"]' => array('value' => (string) FormInterface::MOLLOM_MODE_ANALYSIS),
          '[name="checks[spam]"]' => array('checked' => TRUE),
        ),
      ),
    );
    // Only possible for forms supporting moderation of unpublished posts.
    //$form['unsure']['moderate']['#access'] = !empty($mollom_form['moderation callback']);

    $form['discard'] = array(
      '#type' => 'radios',
      '#title' => t('When text analysis identifies spam'),
      '#default_value' => isset($mollom_form->discard) ? $mollom_form->discard : 1,
      '#options' => array(
        1 => t('!option <em>(!recommended)</em>', array(
          '!option' => t('Discard the post'),
          '!recommended' => $this->t('recommended'),
        )),
        0 => t('Retain the post for manual moderation'),
      ),
      '#required' => $mollom_form->mode == FormInterface::MOLLOM_MODE_ANALYSIS,
      // Only possible for forms supporting moderation of unpublished posts.
      //'#access' => !empty($mollom_form['moderation callback']),
      // Only possible for forms protected via text analysis.
      '#states' => array(
        'visible' => array(
          '[name="mode"]' => array('value' => (string) FormInterface::MOLLOM_MODE_ANALYSIS),
          '[name="checks[spam]"]' => array('checked' => TRUE),
        ),
      ),
    );

    $clean_urls = FALSE;
    // CMP integration requires Clean URLs to be enabled, in order for the
    // local moderation callback endpoints to work.
    if ($clean_urls === NULL) {
      // Assume clean URLs unless the request tells us otherwise.
      $clean_urls = TRUE;
      try {
        $request = \Drupal::request();
        $clean_urls = RequestHelper::isCleanUrl($request);
      }
      catch (ServiceNotFoundException $e) {
      }
    }
    $form['moderation'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow content to be moderated from the <a href="@moderation-url">@moderation-product</a>', array(
        '@moderation-url' => 'https://mollom.com/moderation',
        '@moderation-product' => 'Mollom Content Moderation Platform',
      )),
      '#default_value' => (string) (int) ($mollom_form->moderation && $clean_urls),
      '#disabled' => !$clean_urls,
      // Only possible for forms which result in a locally stored entity.
      //'#access' => !empty($mollom_form['entity']),
      // Only possible for forms protected via text analysis.
      '#states' => array(
        'visible' => array(
          ':input[name="mollom[mode]"]' => array('value' => (string) FormInterface::MOLLOM_MODE_ANALYSIS),
        ),
      ),
      '#description' => t('Provides a unified moderation interface supporting multiple sites, moderation teams, and detailed analytics.'),
    );
    if (!$clean_urls) {
      $form['moderation']['#description'] .= ' ' . t('Requires <a href="@clean-urls">Clean URLs</a> to be enabled.', array(
          '@clean-urls' => url('admin/config/search/clean-urls'),
        ));
    }
    else {
      $form['moderation']['#description'] .= ' ' . t('Note: All content that was created while this option was disabled cannot be moderated from the @moderation-product; only new content will appear.', array(
          '@moderation-product' => 'Mollom Content Moderation Platform',
        ));
    }


    // Return the form.
    return $form;
  }

  /**
   * Builds the configuration forms for all selected datasources.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index begin created or edited.
   */
  public function buildFormFieldsConfigForm(array &$form, FormStateInterface $form_state, \Drupal\mollom\Entity\Form $mollom_form) {
    // Get the fields for the selected form
    $mollom_form_identifier = $mollom_form->id();
    if (empty($mollom_form_identifier)) {
      $complete_form = $form_state->getCompleteForm();
      $mollom_form_identifier = $complete_form['label']['#value'];
    }

    if (empty($mollom_form_identifier)) {
      return TRUE;
    }

    $form_mollom = FormController::mollom_form_new($mollom_form_identifier);

    // Profanity check requires text to analyze; unlike the spam check, there
    // is no fallback in case there is no text.
    $form['checks']['profanity']['#access'] = !empty($form_mollom['elements']);

    // Form elements defined by hook_mollom_form_info() use the
    // 'parent][child' syntax, which Form API also uses internally for
    // form_set_error(), and which allows us to recurse into nested fields
    // during processing of submitted form values. However, since we are using
    // those keys also as internal values to configure the fields to use for
    // textual analysis, we need to encode them. Otherwise, a nested field key
    // would result in the following checkbox attribute:
    //   '#name' => 'mollom[enabled_fields][parent][child]'
    // This would lead to a form validation error, because it is a valid key.
    // By encoding them, we prevent this from happening:
    //   '#name' => 'mollom[enabled_fields][parent%5D%5Bchild]'
    $elements = array();
    foreach ($form_mollom['elements'] as $key => $value) {
      $elements[rawurlencode($key)] = $value;
    }
    $enabled_fields = array();
    foreach ($form_mollom['enabled_fields'] as $value) {
      $enabled_fields[] = rawurlencode($value);
    }
    $form['formfields_config']['enabled_fields'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Text fields to analyze'),
      '#options' => $elements,
      '#default_value' => $enabled_fields,
      '#description' => t('Only enable fields that accept text (not numbers). Omit fields that contain sensitive data (e.g., credit card numbers) or computed/auto-generated values, as well as author information fields (e.g., name, e-mail).'),
      '#access' => !empty($form_mollom['elements']),
      '#states' => array(
        'visible' => array(
          '[name="mode"]' => array('value' => (string) MOLLOM_MODE_ANALYSIS),
        ),
      ),
    );

  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected datasources.
   */
  public static function submitAjaxFormFieldsConfigForm($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Handles changes to the selected datasources.
   */
  public static function buildAjaxFormFieldsConfigForm(array $form, FormStateInterface $form_state) {
    return $form['formfields_config'];
  }

  /**
   * Checks for an existing mollom form.
   *
   * @param string|int $entity_id
   *   The entity ID.
   * @param array $element
   *   The form element.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return bool
   *   TRUE if this format already exists, FALSE otherwise.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    // Use the query factory to build a new robot entity query.
    $query = $this->entityQueryFactory->get('mollom_form');

    // Query the entity ID to see if its in use.
    $result = $query->condition('id', $element['#field_prefix'] . $entity_id)
      ->execute();

    // We don't need to return the ID, only if it exists or not.
    return (bool) $result;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * To set the submit button text, we need to override actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actins from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::validate().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function validate(array $form, FormStateInterface $form_state) {
    parent::validate($form, $form_state);

    // Add code here to validate your config entity's form elements.
    // Nothing to do here.
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::save().
   *
   * Saves the entity. This is called after submit() has built the entity from
   * the form values. Do not override submit() as save() is the preferred
   * method for entity form controllers.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Get the entity from the class variable. We don't need to do this, but
    // it often makes the code easier to read.
    /** @var \Drupal\mollom\Entity\Form $mollom_form */
    $mollom_form = $this->entity;

    // Drupal already populated the form values in the entity object. Each
    // form field was saved as a public variable in the entity class. PHP
    // allows Drupal to do this even if the method is not defined ahead of
    // time.
    $status = $mollom_form->save();

    // Grab the URL of the new entity. We'll use it in the message.
    $uri = $mollom_form->url();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing entity...
      drupal_set_message($this->t('Mollom Form %label has been updated.', array('%label' => $mollom_form->label())));
      watchdog('contact', 'Mollom Form %label has been updated.', array('%label' => $mollom_form->label()), WATCHDOG_NOTICE, l($this->t('Edit'), $uri . '/edit'));
    }
    else {
      // If we created a new entity...
      drupal_set_message($this->t('Mollom form %label has been added.', array('%label' => $mollom_form->label())));
      watchdog('contact', 'Mollom form %label has been added.', array('%label' => $mollom_form->label()), WATCHDOG_NOTICE, l($this->t('Edit'), $uri . '/edit'));
    }

    // Redirect the user to the following path after the save action.
    $form_state->setRedirect('mollom.form.list');
  }


  /**
   * Return registered forms as an array suitable for a 'checkboxes' form element #options property.
   */
  protected function mollom_admin_form_options() {
    // Retrieve all registered forms.
    $form_list = FormController::mollom_form_list();

    // Remove already configured form ids.
    $result = $this->entity->loadMultiple();
    foreach ($result as $form_id) {
      unset($form_list[$form_id->id()]);
    }
    // If all registered forms are configured already, output a message, and
    // redirect the user back to overview.
    if (empty($form_list)) {
      drupal_set_message(t('All available forms are protected already.'));
      //drupal_goto('admin/config/content/mollom');
    }

    // Load module information.
    $module_info = system_get_info('module');

    // Transform form information into an associative array suitable for #options.
    $options = array();
    foreach ($form_list as $form_id => $info) {
      // system_get_info() only supports enabled modules. Default to the module's
      // machine name in case it is disabled.
      $module = $info['module'];
      if (!isset($module_info[$module])) {
        $module_info[$module]['name'] = $module;
      }
      $options[$form_id] = t('!module: !form-title', array(
        '!form-title' => $info['title'],
        '!module' => t($module_info[$module]['name']),
      ));
    }
    // Sort form options by title.
    asort($options);

    return $options;
  }

}