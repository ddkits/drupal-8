<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Views;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\views\ResultRow;

/**
 * Defines VBO action processor.
 */
class ViewsBulkOperationsActionProcessor {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * VBO action manager.
   *
   * @var \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager
   */
  protected $actionManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * Definition of the processed action.
   *
   * @var array
   */
  protected $actionDefinition;

  /**
   * The processed action object.
   *
   * @var array
   */
  protected $action;

  /**
   * Type of the processed entities.
   *
   * @var string
   */
  protected $entityType;

  /**
   * Entity storage object for the current type.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Data describing the view and item selection.
   *
   * @var array
   */
  protected $viewData;

  /**
   * Array of entities that will be processed in the current batch.
   *
   * @var array
   */
  protected $queue;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ViewsBulkOperationsActionManager $actionManager, AccountProxyInterface $user) {
    $this->entityTypeManager = $entityTypeManager;
    $this->actionManager = $actionManager;
    $this->user = $user;
  }

  /**
   * Set values.
   *
   * @param array $view_data
   *   Data concerning the view that will be processed.
   */
  public function initialize(array $view_data) {
    if (!isset($view_data['configuration'])) {
      $view_data['configuration'] = [];
    }
    if (!empty($view_data['preconfiguration'])) {
      $view_data['configuration'] += $view_data['preconfiguration'];
    }

    // Initialize action object.
    $this->actionDefinition = $this->actionManager->getDefinition($view_data['action_id']);
    $this->action = $this->actionManager->createInstance($view_data['action_id'], $view_data['configuration']);

    // Set action context.
    $this->setActionContext($view_data);

    // Set-up action processor.
    $this->entityType = $view_data['entity_type'];
    $this->entityStorage = $this->entityTypeManager->getStorage($this->entityType);

    // Set entire view data as object parameter for future reference.
    $this->viewData = $view_data;
  }

  /**
   * Populate entity queue for processing.
   */
  public function populateQueue($list, $data, &$context = []) {
    $this->queue = [];

    // Get the view if entity list is empty
    // or we have to pass rows to the action.
    if (empty($list) || $this->actionDefinition['pass_view']) {
      $view = Views::getView($data['view_id']);
      $view->setDisplay($data['display_id']);
      if (!empty($data['arguments'])) {
        $view->setArguments($data['arguments']);
      }
      if (!empty($data['exposed_input'])) {
        $view->setExposedInput($data['exposed_input']);
      }
      $view->build();
    }

    // Extra processing if this is a batch operation.
    if (!empty($context)) {
      $batch_size = empty($data['batch_size']) ? 10 : $data['batch_size'];
      if (!isset($context['sandbox']['offset'])) {
        $context['sandbox']['offset'] = 0;
      }
      $offset = &$context['sandbox']['offset'];

      if (!isset($context['sandbox']['total'])) {
        if (empty($list)) {
          $context['sandbox']['total'] = $view->query->query()->countQuery()->execute()->fetchField();
        }
        else {
          $context['sandbox']['total'] = count($list);
        }
      }
      if ($this->actionDefinition['pass_context']) {
        $this->action->setContext($context);
      }
    }
    else {
      $offset = 0;
      $batch_size = 0;
    }

    // Get view results if required.
    if (empty($list)) {
      if ($batch_size) {
        $view->query->setLimit($batch_size);
      }
      $view->query->setOffset($offset);
      $view->query->execute($view);
      foreach ($view->result as $row) {
        $this->queue[] = $this->getEntityTranslation($row);
      }
    }
    else {
      if ($batch_size) {
        $list = array_slice($list, $offset, $batch_size);
      }
      foreach ($list as $item) {
        $this->queue[] = $this->getEntity($item);
      }

      // Get view rows if required.
      if ($this->actionDefinition['pass_view']) {
        $this->getViewResult($view, $list);
      }
    }

    if ($batch_size) {
      $offset += $batch_size;
    }

    if ($this->actionDefinition['pass_view']) {
      $this->action->setView($view);
    }

    return count($this->queue);
  }

  /**
   * Set action context if action method exists.
   *
   * @param array $context
   *   The context to be set.
   */
  public function setActionContext(array $context) {
    if (isset($this->action) && method_exists($this->action, 'setContext')) {
      $this->action->setContext($context);
    }
  }

  /**
   * Process result.
   */
  public function process() {
    $output = [];

    // Check access.
    foreach ($this->queue as $delta => $entity) {
      if (!$this->action->access($entity, $this->user)) {
        $output[] = $this->t('Access denied');
        unset($this->queue[$delta]);
      }
    }

    // Process queue.
    $results = $this->action->executeMultiple($this->queue);

    // Populate output.
    if (empty($results)) {
      $count = count($this->queue);
      for ($i = 0; $i < $count; $i++) {
        $output[] = $this->actionDefinition['label'];
      }
    }
    else {
      foreach ($results as $result) {
        $output[] = $result;
      }
    }
    return $output;
  }

  /**
   * Get entity for processing.
   */
  public function getEntity($entity_data) {
    $revision_id = NULL;

    // If there are 3 items, vid will be last.
    if (count($entity_data) === 3) {
      $revision_id = array_pop($entity_data);
    }

    // The first two items will always be langcode and ID.
    $id = array_pop($entity_data);
    $langcode = array_pop($entity_data);

    // Load the entity or a specific revision depending on the given key.
    $entity = $revision_id ? $this->entityStorage->loadRevision($revision_id) : $this->entityStorage->load($id);

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

  /**
   * Get entity translation from views row.
   *
   * @param \Drupal\views\ResultRow $row
   *   Views result row.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The translated entity.
   */
  public function getEntityTranslation(ResultRow $row) {
    if ($row->_entity->isTranslatable()) {
      $language_field = $this->entityType . '_field_data_langcode';
      if ($row->_entity instanceof TranslatableInterface && isset($row->{$language_field})) {
        return $row->_entity->getTranslation($row->{$language_field});
      }
    }
    return $row->_entity;
  }

  /**
   * Populate view result with selected rows.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view object.
   * @param array $list
   *   User selection data.
   */
  protected function getViewResult(ViewExecutable $view, array $list) {
    $ids = [];
    foreach ($this->queue as $entity) {
      $id = $entity->id();
      $ids[$id] = $id;
    }

    $base_table = $view->storage->get('base_table');
    $alias = $view->query->tables[$base_table][$base_table]['alias'];
    $view->build_info['query']->condition($alias . '.' . $view->storage->get('base_field'), $ids, 'in');
    $view->query->execute($view);

    // Filter result using the $list array.
    $language_field = $this->entityType . '_field_data_langcode';
    $selection = [];
    foreach ($list as $item) {
      $selection[$item[0]][$item[1]] = TRUE;
    }
    foreach ($view->result as $delta => $row) {
      if (isset($row->{$language_field}) && !isset($selection[$row->{$language_field}][$row->_entity->id()])) {
        unset($view->result[$delta]);
      }
    }
    $view->result = array_values($view->result);
  }

}
