<?php
/**
 * @file container Drupal\mollom\Controller\BlacklistListController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\mollom\Storage\BlacklistStorage;
use Drupal\mollom\Utility\Mollom;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responsible for listing the current site's blacklist entries.
 */
class BlacklistListController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  protected $link;

  /**
   * Class constructor.
   *
   * @param TranslationInterface $translation_manager
   */
  public function __construct(TranslationInterface $translation_manager, LinkGeneratorInterface $link_generator) {
    $this->stringTranslation = $translation_manager;
    $this->link = $link_generator;
  }

  /**
   * Implements ContainerInjectionInterface::create().
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('link_generator')
    );
  }

  function content() {
    Mollom::getAdminAPIKeyStatus();

    $items = BlacklistStorage::getList();
    $rows = array();

    // Edit/delete.
    $header = array(
      'type' => $this->t('List'),
      'context' => $this->t('Context'),
      'matches' => $this->t('Matches'),
      'value' => $this->t('Value'),
      'edit' => $this->t('Operations'),
      'delete' => '',
    );
    foreach ($items as $entry) {
      $rows[] = array(
        'data' => array(
          $entry['reason'],
          $entry['context'],
          $entry['match'],
          $entry['value'],
          $this->link->generate($this->t('Edit'), Url::fromRoute('mollom.blacklist.edit', array('entry_id' => $entry['id']))),
          $this->link->generate($this->t('Delete'), Url::fromRoute('mollom.blacklist.delete', array('entry_id' => $entry['id']))),
        ),
      );
    }
    $build['table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no entries in the blacklist.'),
      '#attributes' => array( 'id' => 'mollom-blacklist-list'),
    );

    return $build;
  }
}
