/**
 * @file
 */

(function ($) {
  'use strict';
  Drupal.behaviors.flot_examples = {
    attach: function () {
      // hard-code color indices to prevent them from shifting as
      // countries are turned on/off.
      var options = drupalSettings.flot.placeholder.options;
      var datasets = drupalSettings.flot.placeholder.data;
      var i = 0;
      $.each(datasets, function (key, val) {
        val.color = i;
        ++i;
      });

      // Insert checkboxes.
      var choiceContainer = $('#choices');
      $.each(datasets, function (key, val) {
        choiceContainer.append("<br/><input type='checkbox' name='" + key +
          "' checked='checked' id='id" + key + "'></input>" +
          "<label for='id" + key + "'>" + val.label + '</label>');
      });

      choiceContainer.find('input').click(plotAccordingToChoices);

      function plotAccordingToChoices() {
        var data = [];
        choiceContainer.find('input:checked').each(function () {
          var key = $(this).attr('name');
          if (key && datasets[key]) {
            data.push(datasets[key]);
          }
        });
        if (data.length > 0) {
          $.plot('#placeholder', data, options);
        }
      }
      plotAccordingToChoices();
    }
  };
}(jQuery));
