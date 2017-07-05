<?php

namespace Drupal\views_bulk_operations\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Action\ActionManager;
use Drupal\views_bulk_operations\ViewsBulkOperationsBatch;
use Drupal\views\Views;
use Drupal\Core\Url;

/**
 * Action configuration form.
 */
class ConfirmAction extends FormBase {

  /**
   * Constructor.
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, ActionManager $actionManager) {
    $this->tempStoreFactory = $tempStoreFactory;
    $this->actionManager = $actionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('plugin.manager.views_bulk_operations_action')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return __CLASS__;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view_id = NULL, $display_id = NULL) {
    $tempstore_name = 'views_bulk_operations_' . $view_id . '_' . $display_id;
    $tempstore = $this->tempStoreFactory->get($tempstore_name);
    $view_data = $tempstore->get($this->currentUser()->id());
    $view_data['tempstore_name'] = $tempstore_name;

    // TODO: display an error msg, redirect back.
    if (!isset($view_data['action_id'])) {
      return;
    }

    $form_state->setStorage($view_data);

    $definition = $this->actionManager->getDefinition($view_data['action_id']);

    // Get count of entities to be processed.
    if (!empty($view_data['list'])) {
      $count = count($view_data['list']);
    }
    else {
      $view = Views::getView($view_data['view_id']);
      $view->setDisplay($view_data['display_id']);
      if (!empty($view_data['arguments'])) {
        $view->setArguments($view_data['arguments']);
      }
      if (!empty($view_data['exposed_input'])) {
        $view->setExposedInput($view_data['exposed_input']);
      }
      $view->build();
      $count = $view->query->query()->countQuery()->execute()->fetchField();
    }

    $form['#title'] = $this->t('Are you sure you wish to perform %action on %count entities?', [
      '%action' => $definition['label'],
      '%count' => $count,
    ]);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute action'),
      '#submit' => [
        [$this, 'submitForm'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $view_data = $form_state->getStorage();

    $form_state->setRedirectUrl(Url::fromUserInput($view_data['redirect_uri']['destination']));

    $batch = ViewsBulkOperationsBatch::getBatch($view_data);

    $this->tempStoreFactory->get($view_data['tempstore_name'])->delete($this->currentUser()->id());

    batch_set($batch);
  }

}
