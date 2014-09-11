<?php
/**
 * @file container Drupal\mollom\Controller\BlacklistListController.
 */

namespace Drupal\mollom\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\mollom\Storage\BlacklistStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responsible for listing the current site's blacklist entries.
 */
class BlacklistListController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  protected $url;
  protected $link;

  /**
   * Class constructor.
   *
   * @param TranslationInterface $translation_manager
   */
  public function __construct(TranslationInterface $translation_manager, UrlGeneratorInterface $url_generator, LinkGeneratorInterface $link_generator) {
    $this->stringTranslation = $translation_manager;
    $this->url = $url_generator;
    $this->link = $link_generator;
  }

  /**
   * Implements ContainerInjectionInterface::create().
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
      $container->get('url_generator'),
      $container->get('link_generator')
    );
  }

  function content() {
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
          $entry['matches'],
          $entry['value'],
          $this->link->generate($this->t('Edit'), 'mollom.blacklist.edit'),
          $this->link->generate($this->t('Delete'), 'mollom.blacklist.delete'),
        ),
      );
    }
    $build['table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no entries in the blacklist.', array(
          '@add-url' => $this->url->generateFromRoute('mollom.blacklist.add'),
        )),
      '#attributes' => array( 'id' => 'mollom-blacklist-list'),
    );

    return $build;
  }
}
