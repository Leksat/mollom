<?php

/**
 * @file
 * Contains \Drupal\mollom\Form\Statistics.
 */

namespace Drupal\mollom\Controller;

use Drupal\Component\Utility\String;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Template\Attribute;
use Drupal\mollom\Utility\Mollom;

/**
 * Defines a page that shows statistics.
 */
class StatisticsController extends ControllerBase {
  public function content() {

    Mollom::getAdminAPIKeyStatus();

    $config = $this->config('mollom.settings');

    $embed_attributes = array(
      'src' => 'https://mollom.com/statistics.swf?key=' . String::checkPlain($config->get('keys.public', '')),
      'quality' => 'high',
      'width' => '100%',
      'height' => '430',
      'name' => 'Mollom',
      'align' => 'middle',
      'play' => 'true',
      'loop' => 'false',
      'allowScriptAccess' => 'sameDomain',
      'type' => 'application/x-shockwave-flash',
      'pluginspage' => 'http://www.adobe.com/go/getflashplayer',
      'wmode' => 'transparent'
    );
    return array(
      '#type' => 'item',
      '#markup' => '<embed' . new Attribute($embed_attributes) . '></embed>',
    );
  }
}
