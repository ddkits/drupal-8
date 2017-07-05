<?php

namespace Drupal\views_bulk_operations;

/**
 * Defines module Batch API methods.
 */
class ViewsBulkOperationsBatch {

  /**
   * Translation function wrapper.
   */
  public static function t($string, array $args = [], array $options = []) {
    return \Drupal::translation()->translate($string, $args, $options);
  }

  /**
   * Set message function wrapper.
   */
  public static function message($message = NULL, $type = 'status', $repeat = TRUE) {
    drupal_set_message($message, $type, $repeat);
  }

  /**
   * Batch operation callback.
   */
  public static function operation($list, $data, &$context) {
    // Initialize batch.
    if (empty($context['sandbox'])) {
      $context['sandbox']['processed'] = 0;
      $context['results'] = [];
    }

    // Get entities to process.
    $actionProcessor = \Drupal::service('views_bulk_operations.processor');
    $actionProcessor->initialize($data);

    // Do the processing.
    if ($count = $actionProcessor->populateQueue($list, $data, $context)) {
      $batch_results = $actionProcessor->process();
      if (!empty($batch_results)) {
        // Convert translatable markup to strings in order to allow
        // correct operation of array_count_values function.
        foreach ($batch_results as $result) {
          $context['results'][] = (string) $result;
        }
      }
      $context['sandbox']['processed'] += $count;
      $context['finished'] = $context['sandbox']['processed'] / $context['sandbox']['total'];
      $context['message'] = static::t('Processed @count of @total entities.', [
        '@count' => $context['sandbox']['processed'],
        '@total' => $context['sandbox']['total'],
      ]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function finished($success, $results, $operations) {
    if ($success) {
      $operations = array_count_values($results);
      $details = [];
      foreach ($operations as $op => $count) {
        $details[] = $op . ' (' . $count . ')';
      }
      $message = static::t('Action processing results: @operations.', [
        '@operations' => implode(', ', $details),
      ]);
      static::message($message);
    }
    else {
      $message = static::t('Finished with an error.');
      static::message($message, 'error');
    }
  }

  /**
   * Batch builder function.
   */
  public static function getBatch($view_data) {
    $results = $view_data['list'];
    unset($view_data['list']);

    return [
      'title' => static::t('Performing @operation on selected entities.', ['@operation' => $view_data['action_label']]),
      'operations' => [
        [
          ['\Drupal\views_bulk_operations\ViewsBulkOperationsBatch', 'operation'],
          [
            $results,
            $view_data,
          ],
        ],
      ],
      'finished' => ['\Drupal\views_bulk_operations\ViewsBulkOperationsBatch', 'finished'],
    ];
  }

}
