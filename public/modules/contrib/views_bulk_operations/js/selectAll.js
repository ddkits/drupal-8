/**
 * @file
 * Select-All Button functionality.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.views_bulk_operations = {
    attach: function (context, settings) {
      $('.vbo-select-all').closest('.view-content').once('select-all').each(Drupal.selectAll);
    }
  };

  /**
   * Callback used in {@link Drupal.behaviors.views_bulk_operations}.
   */
  Drupal.selectAll = function () {
    var $viewContent = $(this);
    var $viewsTable = $('table.views-table', $viewContent);
    var colspan = $('table.views-table > thead th', $viewContent).length;
    var $primarySelectAll = $('.vbo-select-all', $viewContent);
    var $tableSelectAll = $(this).find('.select-all input').first();
    $primarySelectAll.parent().hide();

    var strings = {
      selectAll: $('label', $primarySelectAll.parent()).html(),
      selectRegular: Drupal.t('Select only items on this page')
    };

    // Initialize all selector.
    var $allSelector;
    $allSelector = $('<tr class="views-table-row-vbo-select-all even" style="display: none"><td colspan="' + colspan + '"><div><input type="submit" class="form-submit" value="' + strings.selectAll + '"></div></td></tr>');
    $('tbody', $viewsTable).prepend($allSelector);

    if ($primarySelectAll.is(':checked')) {
      $('input', $allSelector).val(strings.selectRegular);
      $allSelector.show();
    }
    else if ($tableSelectAll.is(':checked')) {
      $allSelector.show();
    }

    $('input', $allSelector).click(function (event) {
      event.preventDefault();
      if ($primarySelectAll.is(':checked')) {
        $primarySelectAll.prop('checked', false);
        $allSelector.removeClass('all-selected');
        $(this).val(strings.selectAll);
      }
      else {
        $primarySelectAll.prop('checked', true);
        $allSelector.addClass('all-selected');
        $(this).val(strings.selectRegular);
      }
    });

    $tableSelectAll.change(function (event) {
      if (this.checked) {
        $allSelector.show();
      }
      else {
        $allSelector.hide();
        if ($primarySelectAll.is(':checked')) {
          $('input', $allSelector).trigger('click');
        }
      }

    });
  };
})(jQuery, Drupal);
