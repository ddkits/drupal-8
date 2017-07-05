<?php

namespace Drupal\views_bulk_operations\Service;

use Drupal\Core\Action\ActionManager;

/**
 * Allow VBO actions to define additional configuration.
 */
class ViewsBulkOperationsActionManager extends ActionManager {

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();
    foreach ($definitions as $id => $definition) {
      $this->extendDefinition($definitions[$id]);
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    $definition = parent::getDefinition($plugin_id, $exception_on_invalid);
    $this->extendDefinition($definition);
    return $definition;
  }

  /**
   * Add additional configuration to action definition.
   *
   * @param array $definition
   *   The plugin definition.
   */
  protected function extendDefinition(array &$definition) {
    // Merge in defaults.
    $definition += [
      'confirm' => FALSE,
      'pass_context' => FALSE,
      'pass_view' => FALSE,
    ];

    // Add default confirmation form if confirm set to TRUE
    // and not explicitly set.
    if ($definition['confirm'] && empty($definition['confirm_form_route_name'])) {
      $definition['confirm_form_route_name'] = 'views_bulk_operations.confirm';
    }
  }

}
