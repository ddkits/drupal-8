<?php

namespace Drupal\timezone_js\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves up the timezone file.
 */
class TimezoneJS extends ControllerBase {

  /**
   * Function content.
   */
  public function file($filename) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/libraries/timezone_js/tz/';
    $response = new BinaryFileResponse($path . $filename);
    $response->headers->set('Content-Type', 'text/plain');
    return $response;
  }

}
