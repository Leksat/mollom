<?php /**
 * @file
 * Contains \Drupal\mollom\EventSubscriber\InitSubscriber.
 */

namespace Drupal\mollom\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InitSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [ KernelEvents::REQUEST => ['onEvent'] ];
  }

/**
 * Implements hook_init().
 */
function onEvent() {
  // On all Mollom administration pages, check the module configuration and
  // display the corresponding requirements error, if invalid.
  if (empty($_POST) && strpos($_GET['q'], 'admin/config/content/mollom') === 0 && \Drupal::currentUser()->hasPermission('administer mollom')) {
    // Re-check the status on the settings form only.
    $status = _mollom_status($_GET['q'] == 'admin/config/content/mollom/settings');
    if ($status !== TRUE) {
      // Fetch and display requirements error message, without re-checking.
      module_load_install('mollom');
      $requirements = mollom_requirements('runtime', FALSE);
      if (isset($requirements['mollom']['description'])) {
        drupal_set_message($requirements['mollom']['description'], 'error');
      }
    }
  }
}

}
