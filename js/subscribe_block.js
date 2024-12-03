(function ($, Drupal, once)
{
  var initialized = false;

  function init() {
    // Run once
    if (!initialized) {
      initialized = true;

      // Use class
      $('fieldset.subscription-radios input[type=radio]').each(function () {
        if ($(this).is(':checked')) {
          $('details.subscription-' + $(this).val() + '-details').show();
        } else {
          $('details.subscription-' + $(this).val() + '-details').hide();
        }
      });

      $('fieldset.subscription-radios input[type=radio]').click(function () {
        // Hide all
        $('fieldset.subscription-radios input[type=radio]').each(function () {
          $('details.subscription-' + $(this).val() + '-details').hide();
        });

        // Show selected
        $('details.subscription-' + $(this).val() + '-details').show();
      });

      if ($('input.show-contact-lists').is(':checked')) {
        $('.form-item--settings-group-contact-lists-label').show();
        $('.form-item--settings-group-contact-lists').show();
        $('.form-item--settings-group-contact-lists-delimiter').show();
      } else {
        $('.form-item--settings-group-contact-lists-label').hide();
        $('.form-item--settings-group-contact-lists').hide();
        $('.form-item--settings-group-contact-lists-delimiter').hide();
      }

      $('input.show-contact-lists').click(function () {
        if ($(this).is(':checked')) {
          $('div.form-item--settings-constant-contact-subscription-select-show-select-all').show();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists-label').show();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists').show();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists-delimiter').show();
        } else {
          $('div.form-item--settings-constant-contact-subscription-select-show-select-all').hide();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists-label').hide();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists').hide();
          $('div.form-item--settings-constant-contact-subscription-select-group-contact-lists-delimiter').hide();
        }
      });
    }
  }

  Drupal.behaviors.constantContactMailoutSubscribeBlock = {
    attach: function(context, settings)
    {
      init();
    },
  };
})(jQuery, Drupal, once);
