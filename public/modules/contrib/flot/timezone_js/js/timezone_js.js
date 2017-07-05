/**
 * @file
 */

(function (Drupal, timezoneJS) {
  'use strict';
  Drupal.behaviors.timezone_js = {
    attach: function () {
      timezoneJS.timezone.zoneFileBasePath = '/tz';
      timezoneJS.timezone.init({async: false});
    }
  };
}(Drupal, timezoneJS));
