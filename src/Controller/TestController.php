<?php
namespace Drupal\mollom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mollom\Entity\ProtectableForms;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TestController extends ControllerBase {

  /**
   * Test method
   */
  public function test() {
    $forms = ProtectableForms::getFormList();
    $build['forms'] = array(
      '#type' => 'table',
      '#header' => array(t('Form ID'), t('Title'), t('Entity'), t('Bundle'), t('Module')),
      '#empty' => t('There are no forms configured to be protected by Mollom.'),
    );
    foreach($forms as $form_id->$form) {
      $build['forms'][$form_id]['id'] = array(
        '#markup' => $form_id,
      );
      $build['forms'][$form_id]['title'] = array(
        '#markup' => $form['title'],
      );
      $build['forms'][$form_id]['entity'] = array(
        '#markup' => $form['entity'],
      );
      $build['forms'][$form_id]['bundle'] = array(
        '#markup' => $form['bundle'],
      );
    };
    return $build;
  }
}
