<?php

namespace Drupal\flot_examples\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Display a graph demonstrating timezone adjustments.
 */
class AxesTimeZones extends ControllerBase {

  /**
   * AxesTimeZones.
   */
  public function content() {
    $d = [
      [mktime(14, 0, 0, 2, 12, 2011) * 1000, 28],
      [mktime(15, 0, 0, 2, 12, 2011) * 1000, 27],
      [mktime(16, 0, 0, 2, 12, 2011) * 1000, 25],
      [mktime(17, 0, 0, 2, 12, 2011) * 1000, 19],
      [mktime(18, 0, 0, 2, 12, 2011) * 1000, 16],
      [mktime(19, 0, 0, 2, 12, 2011) * 1000, 14],
      [mktime(20, 0, 0, 2, 12, 2011) * 1000, 11],
      [mktime(21, 0, 0, 2, 12, 2011) * 1000, 9],
      [mktime(22, 0, 0, 2, 12, 2011) * 1000, 7.5],
      [mktime(23, 0, 0, 2, 12, 2011) * 1000, 6],
      [mktime(0, 0, 0, 2, 13, 2011) * 1000, 5],
      [mktime(1, 0, 0, 2, 13, 2011) * 1000, 6],
      [mktime(2, 0, 0, 2, 13, 2011) * 1000, 7.5],
      [mktime(3, 0, 0, 2, 13, 2011) * 1000, 9],
      [mktime(4, 0, 0, 2, 13, 2011) * 1000, 11],
      [mktime(5, 0, 0, 2, 13, 2011) * 1000, 14],
      [mktime(6, 0, 0, 2, 13, 2011) * 1000, 16],
      [mktime(7, 0, 0, 2, 13, 2011) * 1000, 19],
      [mktime(8, 0, 0, 2, 13, 2011) * 1000, 25],
      [mktime(9, 0, 0, 2, 13, 2011) * 1000, 27],
      [mktime(10, 0, 0, 2, 13, 2011) * 1000, 28],
      [mktime(11, 0, 0, 2, 13, 2011) * 1000, 29],
      [mktime(12, 0, 0, 2, 13, 2011) * 1000, 29.5],
      [mktime(13, 0, 0, 2, 13, 2011) * 1000, 29],
      [mktime(14, 0, 0, 2, 13, 2011) * 1000, 28],
      [mktime(15, 0, 0, 2, 13, 2011) * 1000, 27],
      [mktime(16, 0, 0, 2, 13, 2011) * 1000, 25],
      [mktime(17, 0, 0, 2, 13, 2011) * 1000, 19],
      [mktime(18, 0, 0, 2, 13, 2011) * 1000, 16],
      [mktime(19, 0, 0, 2, 13, 2011) * 1000, 14],
      [mktime(20, 0, 0, 2, 13, 2011) * 1000, 11],
      [mktime(21, 0, 0, 2, 13, 2011) * 1000, 9],
      [mktime(22, 0, 0, 2, 13, 2011) * 1000, 7.5],
      [mktime(23, 0, 0, 2, 13, 2011) * 1000, 6],
    ];
    $options_1 = ['xaxis' => ['mode' => "time"]];

    $options_2 = [
      'xaxis' => [
        'mode' => "time",
        'timezone' => 'browser',
      ],
    ];

    $options_3 = [
      'xaxis' => [
        'mode' => "time",
        'timezone' => 'America/Chicago',
      ],
    ];

    $output['UTC'] = [
      '#type' => 'flot',
      '#theme' => 'flot_examples',
      '#options' => $options_1,
      '#data' => [$d],
      '#id' => 'placeholderUTC',
      '#title' => 'UTC',
    ];
    $output['Browser'] = [
      '#type' => 'flot',
      '#theme' => 'flot_examples',
      '#options' => $options_2,
      '#data' => [$d],
      '#id' => 'placeholderLocal',
      '#title' => 'Browser',
    ];
    $output['Chicago'] = [
      '#type' => 'flot',
      '#theme' => 'flot_examples',
      '#options' => $options_3,
      '#data' => [$d],
      '#id' => 'placeholderChicago',
      '#title' => 'Chicago',
    ];
    return $output;
  }

}
