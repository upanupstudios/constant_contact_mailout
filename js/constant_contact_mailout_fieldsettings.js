(function ($, Drupal)
{
  Drupal.behaviors.constant_contact_mailout_fieldsettings = {
    attach: function (context, settings)
    {
      $('input.contact-list-creation-radios').each(function() {
        if ($(this).is(':checked')) {
          $('details.contact-list-creation-' + $(this).val() + '-details').show();
        } else {
          $('details.contact-list-creation-' + $(this).val() + '-details').hide();
        }
      });

      $('input.contact-list-creation-radios').click(function() {
        // Hide all
        $('input.contact-list-creation-radios').each(function () {
          $('details.contact-list-creation-' + $(this).val() + '-details').hide();
        });

        // Show selected
        $('details.contact-list-creation-' + $(this).val() + '-details').show();
      });

      $('input.contact-list-creation-taxonomy-vocabularies').each(function () {
        if ($(this).is(':checked')) {
          $('details.contact-list-creation-taxonomy-' + $(this).val() + '-details').show();
        } else {
          $('details.contact-list-creation-taxonomy-' + $(this).val() + '-details').hide();
        }
      });

      $('input.contact-list-creation-taxonomy-vocabularies').click(function () {
        // Hide all
        $('input.contact-list-creation-taxonomy-vocabularies').each(function () {
          $('details.contact-list-creation-taxonomy-' + $(this).val() + '-details').hide();
        });

        // Show/hde selected
        if ($(this).is(':checked')) {
          $('details.contact-list-creation-taxonomy-' + $(this).val() + '-details').show();
        } else {
          $('details.contact-list-creation-taxonomy-' + $(this).val() + '-details').hide();
        }
      });

      if ($('#edit-settings-scheduled-mailout').is(':checked')) {
        $('div.js-form-item-settings-scheduled-mailout-label').show();
      } else {
        $('div.js-form-item-settings-scheduled-mailout-label').hide();
      }

      $('#edit-settings-scheduled-mailout').click(function() {
        if ($(this).is(':checked')) {
          $('div.js-form-item-settings-scheduled-mailout-label').show();
        } else {
          $('div.js-form-item-settings-scheduled-mailout-label').hide();
        }
      });
    }
  };
}(jQuery, Drupal));
