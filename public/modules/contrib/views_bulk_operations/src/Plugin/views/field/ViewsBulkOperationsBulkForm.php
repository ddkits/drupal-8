<?php

namespace Drupal\views_bulk_operations\Plugin\views\field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Defines a actions-based bulk operation form element.
 *
 * @ViewsField("views_bulk_operations_bulk_form")
 * @class
 */
class ViewsBulkOperationsBulkForm extends FieldPluginBase implements CacheableDependencyInterface {

  use RedirectDestinationTrait;
  use UncacheableFieldHandlerTrait;
  use EntityTranslationRenderTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The action storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $actionStorage;

  /**
   * An array of actions that can be executed.
   *
   * @var \Drupal\system\ActionConfigEntityInterface[]
   */
  protected $actions = [];

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new BulkForm object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager $actionManager
   *   Extended action manager object.
   * @param \Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor $actionProcessor
   *   Views Bulk Operations action processor.
   * @param \Drupal\user\PrivateTempStoreFactory $tempStoreFactory
   *   User private temporary storage factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, LanguageManagerInterface $language_manager, ViewsBulkOperationsActionManager $actionManager, ViewsBulkOperationsActionProcessor $actionProcessor, PrivateTempStoreFactory $tempStoreFactory, AccountInterface $currentUser) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
    $this->actionManager = $actionManager;
    $this->actionProcessor = $actionProcessor;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('plugin.manager.views_bulk_operations_action'),
      $container->get('views_bulk_operations.processor'),
      $container->get('user.private_tempstore'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $entity_type = $this->getEntityType();

    // Fetch actions.
    $this->actions = [];
    foreach ($this->actionManager->getDefinitions() as $id => $definition) {
      if (empty($definition['type']) || $definition['type'] === $entity_type) {
        $definition['action_type'] = 'action';
        $this->actions[$id] = $definition;
      }
    }

    // Initialize tempstore object.
    $tempstore_name = 'views_bulk_operations_' . $view->id() . '_' . $view->current_display;
    $this->userTempStore = $this->tempStoreFactory->get($tempstore_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // @todo Consider making the bulk operation form cacheable. See
    //   https://www.drupal.org/node/2503009.
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->languageManager->isMultilingual() ? $this->getEntityTranslationRenderer()->getCacheContexts() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityManager() {
    return $this->entityManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['batch'] = ['default' => TRUE];
    $options['batch_size'] = ['default' => 10];
    $options['form_step'] = ['default' => TRUE];
    $options['action_title'] = ['default' => $this->t('Action')];
    $options['include_exclude'] = ['default' => 'exclude'];
    $options['selected_actions'] = ['default' => []];
    $options['preconfiguration'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['batch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Process in a batch operation'),
      '#default_value' => $this->options['batch'],
    ];

    $form['batch_size'] = [
      '#title' => $this->t('Batch size'),
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#description' => $this->t('Only applicable if results are processed in a batch operation.'),
      '#default_value' => $this->options['batch_size'],
    ];

    $form['form_step'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Configuration form on new page (configurable actions)'),
      '#default_value' => $this->options['form_step'],
    ];

    $form['action_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action title'),
      '#default_value' => $this->options['action_title'],
      '#description' => $this->t('The title shown above the actions dropdown.'),
    ];

    $form['include_exclude'] = [
      '#type' => 'radios',
      '#title' => $this->t('Available actions'),
      '#options' => [
        'include' => $this->t('Only selected actions'),
        'exclude' => $this->t('All actions, except selected'),
      ],
      '#default_value' => $this->options['include_exclude'],
    ];

    $form['selected_actions'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Selected actions'),
    ];

    // Load values for AJAX functionality.
    $form_values = $form_state->getValue(['options', 'selected_actions']);
    if (is_null($form_values)) {
      $selected_actions = $this->options['selected_actions'];
      $preconfiguration = $this->options['preconfiguration'];
    }
    else {
      $selected_actions = [];
      $preconfiguration = [];
      foreach ($form_values as $id => $value) {
        $selected_actions[$id] = $value['state'] ? $id : 0;
        $preconfiguration[$id] = isset($value['preconfiguration']) ? $value['preconfiguration'] : [];
      }
    }

    foreach ($this->actions as $id => $action) {
      if (method_exists($action['class'], 'buildPreConfigurationForm')) {
        $wrapper_id = 'action-' . $id . '-wrapper';

        $form['selected_actions'][$id]['state'] = [
          '#type' => 'checkbox',
          '#title' => $action['label'],
          '#default_value' => empty($selected_actions[$id]) ? 0 : 1,
          '#ajax' => [
            'callback' => [__CLASS__, 'optionsFormAjax'],
            'wrapper' => $wrapper_id,
          ],
        ];
        // Without this there are problems when entering configuration
        // value for the first time. TODO: investigate further.
        $form['selected_actions'][$id]['preconfiguration'] = [
          '#type' => 'details',
          '#title' => $this->t('Action preconfiguration'),
          '#open' => TRUE,
          // The next line is EVIL, but couldn't find a solution for this.
          '#attributes' => ['style' => 'display: none', 'id' => $wrapper_id],
        ];

        // Load preconfiguration form.
        $actionObject = $this->actionManager->createInstance($id);

        if (!empty($selected_actions[$id])) {
          unset($form['selected_actions'][$id]['preconfiguration']['#attributes']['style']);
        }
        if (!isset($preconfiguration[$id])) {
          $preconfiguration[$id] = [];
        }
        $form['selected_actions'][$id]['preconfiguration'] = $actionObject->buildPreConfigurationForm($form['selected_actions'][$id]['preconfiguration'], $preconfiguration[$id], $form_state);
      }
      else {
        $form['selected_actions'][$id]['state'] = [
          '#type' => 'checkbox',
          '#title' => $action['label'],
          '#default_value' => empty($this->options['selected_actions'][$id]) ? 0 : 1,
        ];
      }
    }

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    $options = &$form_state->getValue('options');
    foreach ($options['selected_actions'] as $id => $action) {
      if (!empty($action['state'])) {
        if (isset($action['preconfiguration'])) {
          $options['preconfiguration'][$id] = $action['preconfiguration'];
          unset($options['selected_actions'][$id]['preconfiguration']);
        }
        $options['selected_actions'][$id] = $id;
      }
      else {
        unset($options['preconfiguration'][$id]);
        $options['selected_actions'][$id] = 0;
      }
    }
    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * AJAX callback that returns config form element.
   */
  public static function optionsFormAjax($form, $form_state) {
    $parents = $form_state->getTriggeringElement()['#parents'];
    unset($parents[count($parents) - 1]);
    $element = NestedArray::getValue($form, $parents);
    return $element['preconfiguration'];
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);

    // If the view is using a table style, provide a placeholder for a
    // "select all" checkbox.
    if (!empty($this->view->style_plugin) && $this->view->style_plugin instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    // Make sure we do not accidentally cache this form.
    // @todo Evaluate this again in https://www.drupal.org/node/2503009.
    $form['#cache']['max-age'] = 0;

    // Add the tableselect javascript.
    $form['#attached']['library'][] = 'core/drupal.tableselect';
    $use_revision = array_key_exists('revision', $this->view->getQuery()->getEntityTableInfo());

    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        $entity = $this->getEntityTranslation($this->getEntity($row), $row);

        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
          // We are not able to determine a main "title" for each row, so we can
          // only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
          '#return_value' => self::calculateEntityBulkFormKey($entity, $use_revision),
        ];
      }

      // Replace the form submit button label.
      $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

      // Ensure a consistent container for filters/operations
      // in the view header.
      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'vbo-action-form-wrapper',
        ],
      ];
      $form['header'][$this->options['id']]['action'] = [
        '#type' => 'select',
        '#title' => $this->options['action_title'],
        '#options' => $this->getBulkOptions(),
      ];

      // Add AJAX functionality if actions are configurable through this form.
      if (empty($this->options['form_step'])) {
        $form['header'][$this->options['id']]['action']['#ajax'] = [
          'callback' => [__CLASS__, 'viewsFormAjax'],
          'wrapper' => 'vbo-action-form-wrapper',
        ];

        $action_id = $form_state->getValue('action');
        if (!empty($action_id)) {
          $action = $this->actions[$action_id];
          if ($this->isConfigurable($action)) {
            $actionObject = $this->actionManager->createInstance($action_id);
            if (!isset($form['header'][$this->options['id']])) {
              $form['header'][$this->options['id']] = [];
            }
            $form['header'][$this->options['id']] += $actionObject->buildConfigurationForm($form['header'][$this->options['id']], $form_state);
          }
        }
      }

      // Select all results checkbox.
      $total_results = $this->view->query->query()->countQuery()->execute()->fetchField();
      $items_per_page = $this->view->getItemsPerPage();
      if (!empty($items_per_page) && $total_results > $items_per_page) {
        $form['header'][$this->options['id']]['select_all'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Select all @count results in this view', [
            '@count' => $total_results,
          ]),
          '#attributes' => ['class' => ['vbo-select-all']],
        ];

        // Add fancy select all library.
        $form['#attached']['library'][] = 'views_bulk_operations/views_bulk_operations.selectAll';
      }

      // Duplicate the form actions into the action container in the header.
      $form['header'][$this->options['id']]['actions'] = $form['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  /**
   * AJAX callback.
   */
  public static function viewsFormAjax(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#array_parents'][1];
    return $form['header'][$plugin_id];
  }

  /**
   * Returns the available operations for this form.
   *
   * @return array
   *   An associative array of operations, suitable for a select element.
   */
  protected function getBulkOptions() {
    $options = [
      '' => $this->t('-- Select action --'),
    ];
    foreach ($this->actions as $id => $definition) {
      $in_selected = in_array($id, $this->options['selected_actions'], TRUE);
      // If the field is configured to include only the selected actions,
      // skip actions that were not selected.
      if (($this->options['include_exclude'] == 'include') && !$in_selected) {
        continue;
      }
      // Otherwise, if the field is configured to exclude the selected
      // actions, skip actions that were selected.
      elseif (($this->options['include_exclude'] == 'exclude') && $in_selected) {
        continue;
      }

      $options[$id] = $definition['label'];
    }

    return $options;
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user tried to access an action without access to it.
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('step') == 'views_form_views_form') {

      $action_id = $form_state->getValue('action');

      $action = $this->actions[$action_id];

      $data = [
        'action_id' => $action_id,
        'action_label' => $action['label'],
        'entity_type' => $this->getEntityType(),
        'preconfiguration' => isset($this->options['preconfiguration'][$action_id]) ? $this->options['preconfiguration'][$action_id] : [],
        'list' => [],
        'view_id' => $this->view->id(),
        'display_id' => $this->view->current_display,
      ];

      if (!$form_state->getValue('select_all')) {
        // We get selected items from user input as
        // in \Drupal\system\Plugin\views\field\BulkForm.
        // TODO: Check if this is necessary.
        $user_input = $form_state->getUserInput();
        $selected = array_values(array_filter($user_input[$this->options['id']]));
        foreach ($selected as $bulk_form_key) {
          $data['list'][] = json_decode(base64_decode($bulk_form_key));
        }
      }

      $configurable = $this->isConfigurable($action);

      // Routing - determine redirect route.
      if ($this->options['form_step'] && $configurable) {
        $redirect_route = 'views_bulk_operations.execute_configurable';
      }
      elseif ($this->options['batch']) {
        if (!empty($action['confirm_form_route_name'])) {
          $redirect_route = $action['confirm_form_route_name'];
        }
        else {
          $redirect_route = 'views_bulk_operations.execute_batch';
        }
      }
      elseif (!empty($action['confirm_form_route_name'])) {
        $redirect_route = $action['confirm_form_route_name'];
      }

      // Redirect if needed.
      if (!empty($redirect_route)) {
        if ($form_state->getValue('select_all')) {
          $data['arguments'] = $this->view->args;
          $data['exposed_input'] = $this->view->getExposedInput();
        }
        $data['batch_size'] = $this->options['batch_size'];
        $data['redirect_uri'] = $this->getDestinationArray();

        $this->userTempStore->set($this->currentUser->id(), $data);

        $form_state->setRedirect($redirect_route, [
          'view_id' => $this->view->id(),
          'display_id' => $this->view->current_display,
        ]);
      }
      // Or process rows here.
      else {
        // Get configuration.
        if ($configurable) {
          $actionObject = $this->actionManager->createInstance($action_id);
          if (method_exists($actionObject, 'submitConfigurationForm')) {
            $actionObject->submitConfigurationForm($form, $form_state);
            $data['configuration'] = $actionObject->getConfiguration();
          }
          else {
            $form_state->cleanValues();
            $data['configuration'] = $form_state->getValues();
          }
        }

        $count = 0;
        $list = $data['list'];
        unset($data['list']);

        // Populate and process queue.
        $this->actionProcessor->initialize($data);
        if ($this->actionProcessor->populateQueue($list, $data)) {
          $results = $this->actionProcessor->process();
          $count = count($results);
        }

        if ($count) {
          drupal_set_message($this->formatPlural($count, '%action was applied to @count item.', '%action was applied to %count items.', [
            '%action' => $action['label'],
            '%count' => $count,
          ]));
        }
        else {
          drupal_set_message($this->t('%action was executed but no items were processed.', [
            '%action' => $action['label'],
          ]));
        }

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('action'))) {
      $form_state->setErrorByName('action', $this->t('Please select an action to perform.'));
    }

    // This happened once, can't reproduce but here's a safety switch.
    if (!isset($this->actions[$form_state->getValue('action')])) {
      $form_state->setErrorByName('action', $this->t('Form error occurred, please try again.'));
    }

    if (!$form_state->getValue('select_all')) {
      $selected = array_filter($form_state->getValue($this->options['id']));
      if (empty($selected)) {
        $form_state->setErrorByName('', $this->t('No items selected.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if ($this->languageManager->isMultilingual()) {
      $this->getEntityTranslationRenderer()->query($this->query, $this->relationship);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * Wraps drupal_set_message().
   */
  protected function drupalSetMessage($message = NULL, $type = 'status', $repeat = FALSE) {
    drupal_set_message($message, $type, $repeat);
  }

  /**
   * Calculates a bulk form key.
   *
   * This generates a key that is used as the checkbox return value when
   * submitting a bulk form. This key allows the entity for the row to be loaded
   * totally independently of the executed view row.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to calculate a bulk form key for.
   * @param bool $use_revision
   *   Whether the revision id should be added to the bulk form key. This should
   *   be set to TRUE only if the view is listing entity revisions.
   *
   * @return string
   *   The bulk form key representing the entity's id, language and revision (if
   *   applicable) as one string.
   *
   * @see self::loadEntityFromBulkFormKey()
   */
  public static function calculateEntityBulkFormKey(EntityInterface $entity, $use_revision) {
    $key_parts = [$entity->language()->getId(), $entity->id()];

    if ($entity instanceof RevisionableInterface && $use_revision) {
      $key_parts[] = $entity->getRevisionId();
    }

    // An entity ID could be an arbitrary string (although they are typically
    // numeric). JSON then Base64 encoding ensures the bulk_form_key is
    // safe to use in HTML, and that the key parts can be retrieved.
    $key = json_encode($key_parts);
    return base64_encode($key);
  }

  /**
   * Check if an action is configurable.
   */
  protected function isConfigurable($action) {
    return (in_array('Drupal\Core\Plugin\PluginFormInterface', class_implements($action['class']), TRUE) || method_exists($action['class'], 'buildConfigurationForm'));
  }

}
