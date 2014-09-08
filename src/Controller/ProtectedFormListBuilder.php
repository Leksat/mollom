<?php
/**
 * @file
 * Contains Drupal\mollom\Controller\ProtectedFormListBuilder
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of protected form entities.
 */
class ProtectedFormListBuilder extends ConfigEntityListBuilder {

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   */
  public function buildHeader() {
    $header['form'] = $this->t('Form');
    $header['protected_mode'] = $this->t('Protected mode');
    return $header + parent::buildHeader();
  }

  /**
   * Builds a row for an entity in the entity listing.
   *
   * @param EntityInterface $entity
   *   The entity for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the entity.
   */
  public function buildRow(EntityInterface $entity) {
    $row['form'] = $entity->form_id;
    $header['protected_mode'] = $this->getProtectedMode();
    return $row + parent::buildRow($entity);
  }
}
