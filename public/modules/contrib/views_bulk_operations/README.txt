Introduction
------------

Views Bulk Operations augments Views by allowing actions
(provided by Drupal core or contrib modules) to be executed
on the selected view rows.

It does so by showing a checkbox in front of each displayed row, and adding a
select box on top of the View containing operations that can be applied.


Getting started
-----------------

1. Create a View with a page or block display.
2. Add a "Views bulk operations" field (global), available on
   all entity types.
3. Configure the field by selecting at least one operation.
4. Go to the View page. VBO functionality should be present.


Creating custom actions
-----------------------

Example that covers different possibilities is available in
modules/views_bulk_operatios_example/.

In a module, create an action plugin (check the example module
or \core\modules\node\src\Plugin\Action\ for simple implementations).

Available annotation parameters:
  - id: The action ID (required),
  - label: Action label (required),
  - type: Entity type for the action, if left empty, action will be
    applicable to all entity types (required),
  - confirm: If set to TRUE and the next parameter is empty,
    the module default confirmation form will be used (default: FALSE),
  - confirm_form_route_name: Route name of the action confirmation form.
    If left empty and the previous parameter is empty, there will be
    no confirmation step (default: empty string).
  - pass_context: If set to TRUE, the entire batch context 
    will be added to the action $context parameter (default: FALSE).
  - pass_view: If set to TRUE, the entire view with selected
    results ($view->result) of the current batch will be available
    in the action $view parameter (default: FALSE).


Additional notes
----------------

Documentation also available at
https://www.drupal.org/docs/8/modules/views-bulk-operations-vbo.
