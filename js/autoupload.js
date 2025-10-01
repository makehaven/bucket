(function ($, Drupal) {
  'use strict';

  $.fn.bucketAutosubmit = function () {
    $('[data-bucket-submit="true"]').click();
  };

})(jQuery, Drupal);
