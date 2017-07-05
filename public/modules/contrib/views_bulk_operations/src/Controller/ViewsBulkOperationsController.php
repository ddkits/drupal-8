<?php

namespace Drupal\views_bulk_operations\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;

/**
 * Defines VBO controller class.
 */
class ViewsBulkOperationsController extends ControllerBase implements ContainerInjectionInterface {

  protected $tempStoreFactory;

  /**
   * Constructs a new controller object.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory) {
    $this->tempStoreFactory = $tempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore')
    );
  }

  /**
   * Batch builder function.
   */
  public function execute($view_id, $display_id) {
    $tempstore_name = 'views_bulk_operations_' . $view_id . '_' . $display_id;

    $tempstore = $this->tempStoreFactory->get($tempstore_name);
    $view_data = $tempstore->get($this->currentUser()->id());

    $batch = ViewsBulkOperationsBatch::getBatch($view_data);

    $tempstore->delete($this->currentUser()->id());

    batch_set($batch);
    return batch_process($view_data['redirect_uri']['destination']);
  }

}
