<?php

/**
 * @file
 * Contains \Drupal\mollom\Element\Mollom.
 */

namespace Drupal\mollom\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\mollom\Entity\FormInterface;

/**
 * Provides a form element for storage of internal information.
 *
 * @FormElement("mollom")
 */
class Mollom extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#process' => array(
        array($class, 'processMollom'),
      ),
      '#tree' => TRUE,
      '#pre_render' => array(
        array($class, 'preRenderMollom'),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    //dpm($element);
    //dpm($input);
    return $input;
  }


  /**
   * #process callback for #type 'mollom'.
   *
   * Mollom supports two fundamentally different protection modes:
   * - For text analysis, the state of a post is essentially tracked by the Mollom
   *   API/backend:
   *   - Every form submission attempt (re-)sends the post data to Mollom, and the
   *     API ensures to return the correct spamClassification each time.
   *   - The client-side logic fully relies on the returned spamClassification
   *     value to trigger the corresponding actions and does not track the state
   *     of the form submission attempt locally.
   *   - For example, when Mollom is "unsure" and the user solved the CAPTCHA,
   *     then subsequent Content API responses will return "ham" instead of
   *     "unsure", so the user is not asked to solve more than one CAPTCHA.
   * - For CAPTCHA-only, the solution state of a CAPTCHA has to be tracked locally:
   *   - Unlike text analysis, a CAPTCHA can only be solved or not. Additionally,
   *     a CAPTCHA cannot be solved more than once. The Mollom API only returns
   *     (once) whether a CAPTCHA has been solved correctly. A previous state
   *     cannot be queried from Mollom.
   *   - State-tracking would not be necessary, if there could not be other form
   *     validation errors preventing the form from submitting directly, as well as
   *     "Preview" buttons that may rebuild the entire form from scratch (if there
   *     are no validation errors).
   *   - To track state, the Form API cache is enabled, which allows to store and
   *     retrieve the entire $form_state of a previous submission (attempt).
   *   - Furthermore, page caching is force-disabled, so as to ensure that cached
   *     form data is not re-used by different users/visitors.
   *   - In combination with the Form API cache, this is essentially equal to
   *     force-starting a session for all users that try to submit a CAPTCHA-only
   *     protected form. However, a session would persist across other pages.
   *
   * @see mollom_form_alter()
   * @see mollom_element_info()
   * @see mollom_pre_render_mollom()
   */
  public static function processMollom(array $element, FormStateInterface $form_state, array $form) {
    // Setup initial Mollom session and form information.
    $mollom = array(
      // Only TRUE if the form is protected by text analysis.
      'require_analysis' => $element['#mollom_form']->mode == FormInterface::MOLLOM_MODE_ANALYSIS,
      // Becomes TRUE whenever a CAPTCHA needs to be solved.
      'require_captcha' => $element['#mollom_form']->mode == FormInterface::MOLLOM_MODE_CAPTCHA,
      // Becomes TRUE when the CAPTCHA has been solved.
      // Only applies to CAPTCHA-only protected forms. Not necessarily TRUE for
      // text analysis, even if a CAPTCHA has been solved previously.
      'passed_captcha' => FALSE,
      // The type of CAPTCHA to show; 'image' or 'audio'.
      'captcha_type' => 'image',
      // Becomes TRUE if the form is protected by text analysis and the submitted
      // entity should be unpublished.
      'require_moderation' => FALSE,
      // Internally used bag for last Mollom API responses.
      'response' => array(
      ),
    );

    $mollom_form_array = get_object_vars($element['#mollom_form']);
    $mollom += $mollom_form_array;

    $form_state->setValue('mollom', $mollom);

    // By default, bad form submissions are discarded, unless the form was
    // configured to moderate bad posts. 'discard' may only be FALSE, if there is
    // a valid 'moderation callback'. Otherwise, it must be TRUE.
    if (empty($mollom['moderation callback']) || !function_exists($mollom['moderation callback'])) {
      $mollom['discard'] = TRUE;
    }

    // Add the JavaScript.
    $element['#attached']['js'][] = drupal_get_path('module', 'mollom') . '/mollom.js';

    // Add the Mollom session data elements.
    // These elements resemble the {mollom} database schema. The form validation
    // handlers will pollute them with values returned by Mollom. For entity
    // forms, the submitted values will appear in a $entity->mollom property,
    // which in turn represents the Mollom session data record to be stored.
    $element['entity'] = array(
      '#type' => 'value',
      '#value' => isset($mollom['entity']) ? $mollom['entity'] : 'mollom_content',
    );
    $element['id'] = array(
      '#type' => 'value',
      '#value' => NULL,
    );
    $element['contentId'] = array(
      '#type' => 'hidden',
      // There is no default value; Form API will always use the value that was
      // submitted last (including rebuild scenarios).
      '#attributes' => array('class' => 'mollom-content-id'),
    );
    $element['captchaId'] = array(
      '#type' => 'hidden',
      '#attributes' => array('class' => 'mollom-captcha-id'),
    );
    $element['form_id'] = array(
      '#type' => 'value',
      '#value' => $mollom['form_id'],
    );
    $element['moderate'] = array(
      '#type' => 'value',
      '#value' => 0,
    );
    $data_spec = array(
      '#type' => 'value',
      '#value' => NULL,
    );
    $element['spamScore'] = $data_spec;
    $element['spamClassification'] = $data_spec;
    $element['qualityScore'] = $data_spec;
    $element['profanityScore'] = $data_spec;
    $element['languages'] = $data_spec;
    $element['reason'] = $data_spec;
    $element['solved'] = $data_spec;

    // Add the CAPTCHA element.
    // - Cannot be #required, since that would cause _form_validate() to output a
    //   validation error in situations in which the CAPTCHA is not required.
    // - #access can also not start with FALSE, since the form structure may be
    //   cached, and Form API ignores all user input for inaccessible elements.
    // Since this element needs to be hidden by the #pre_render callback, but that
    // callback does not have access to $form_state, the 'passed_captcha' state is
    // assigned as Boolean #solved = TRUE element property when solved correctly.
    $element['captcha'] = array(
      '#type' => 'textfield',
      '#title' => t('Verification'),
      '#size' => 10,
      '#default_value' => '',
    );

    // Disable browser autocompletion, unless testing mode is enabled, in which
    // case autocompletion for 'correct' and 'incorrect' is handy.
    /*if (!variable_get('mollom_testing_mode', 0)) {
      $element['captcha']['#attributes']['autocomplete'] = 'off';
    }*/
    // For CAPTCHA-only protected forms:
    /*if (!$form_state['mollom']['require_analysis'] && $form_state['mollom']['require_captcha']) {
      // Retrieve and show an initial CAPTCHA.
      if (empty($form_state['process_input'])) {
        // Enable Form API caching, in order to track the state of the CAPTCHA.
        $form_state['cache'] = TRUE;
        // mollom_form_add_captcha() adds the CAPTCHA and disables page caching.
        mollom_form_add_captcha($element, $form_state);
      }
      // If the CAPTCHA was solved in a previous submission already, resemble
      // mollom_validate_captcha(). This case is only reached in case the form
      // 1) is not cached, 2) fully validated, 3) was submitted, and 4) is getting
      // rebuilt; e.g., "Preview" on comment and node forms.
      if ($form_state['mollom']['passed_captcha']) {
        $element['captcha']['#solved'] = TRUE;
      }
    }*/

    // Add a spambot trap. Purposively use 'homepage' as field name.
    // This form input element is only supposed to be visible for robots. It has
    // - no label, since some screen-readers do not notice that the label is
    //   attached to an input that is hidden.
    // - no 'title' attribute, since some JavaScript libraries that are trying to
    //   mimic HTML5 placeholders are injecting the 'title' into the input's value
    //   and fail to clean up and remove the placeholder value upon form submission,
    //   causing false-positive spam classifications.
    $element['homepage'] = array(
      '#type' => 'textfield',
      // Wrap the entire honeypot form element markup into a hidden container, so
      // robots cannot simply check for a style attribute, but instead have to
      // implement advanced DOM processing to figure out whether they are dealing
      // with a honeypot field.
      '#prefix' => '<div style="display: none;">',
      '#suffix' => '</div>',
      '#default_value' => '',
      '#attributes' => array(
        // Disable browser autocompletion.
        'autocomplete' => 'off',
      ),
    );

    // Add the form behavior analysis web tracking beacon field holder if enabled.
    /*if (variable_get('mollom_fba_enabled', 0) && $form_state['mollom']['require_analysis']) {
      $element['fba'] = array(
        '#type' => 'hidden',
      );
    }*/

    // Make Mollom form and session information available to entirely different
    // functions.
    // @see mollom_mail_alter()
    /*$GLOBALS['mollom'] = &$form_state['mollom'];*/
    //dpm($element);

    return $element;
  }

  /**
   * #pre_render callback for #type 'mollom'.
   *
   * - Hides the CAPTCHA if it is not required or the solution was correct.
   * - Marks the CAPTCHA as required.
   */
  public static function mollom_pre_render_mollom($element) {
    // If there is no CAPTCHA ID, then there is no CAPTCHA that can be displayed.
    // If a CAPTCHA was solved, then the widget makes no sense either.
    if (empty($element['captchaId']['#value']) || !empty($element['captcha']['#solved'])) {
      $element['captcha']['#access'] = FALSE;
    }
    else {
      // The form element cannot be marked as #required, since _form_validate()
      // would throw an element validation error on an empty value otherwise,
      // before the form-level validation handler is executed.
      // #access cannot default to FALSE, since the $form may be cached, and
      // Form API ignores user input for all elements that are not accessible.
      $element['captcha']['#required'] = TRUE;
    }

    // UX: Empty the CAPTCHA field value, as the user has to re-enter a new one.
    $element['captcha']['#value'] = '';

    // DX: Debugging helpers.
//  $element['#suffix'] = 'contentId: ' . $element['contentId']['#value'] . '<br>';
//  $element['#suffix'] .= 'captchaId: ' . $element['captchaId']['#value'] . '<br>';

    return $element;
  }
}
