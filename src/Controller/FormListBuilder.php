<?php
/**
 * @file
 * Contains Drupal\mollom\Controller\FormListBuilder.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mollom\Utility\Mollom;

/**
 * Provides a listing of mollom_form entities.
 *
 * @package Drupal\mollom\Controller
 *
 * @ingroup mollom
 */
class FormListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    Mollom::getAdminAPIKeyStatus();

    $header['label'] = $this->t('Form');
    $header['machine_name'] = $this->t('Machine Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $this->getLabel($entity);
    $row['machine_name'] = $entity->id();

    return $row + parent::buildRow($entity);
  }

}
