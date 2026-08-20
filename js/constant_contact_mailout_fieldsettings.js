(function ($, Drupal, once)
{
  function init() {
    $('input.contact-list-creation-radios').each(function () {
      if ($(this).is(':checked')) {
        $('details.contact-list-creation-' + $(this).val() + '-details').show();
      } else {
        $('details.contact-list-creation-' + $(this).val() + '-details').hide();
      }
    });

    $('input.contact-list-creation-radios').click(function () {
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

    $('#edit-settings-scheduled-mailout').click(function () {
      if ($(this).is(':checked')) {
        $('div.js-form-item-settings-scheduled-mailout-label').show();
      } else {
        $('div.js-form-item-settings-scheduled-mailout-label').hide();
      }
    });
  }


  Drupal.behaviors.constantContactMailoutFieldItem = {
    attach: function (context, settings)
    {
      // Only initialise once per set of radios, so handlers are not bound
      // again every time behaviors are re-attached.
      if (once('constant-contact-mailout-fieldsettings', 'input.contact-list-creation-radios', context).length) {
        init();
      }
    }
  };
}(jQuery, Drupal, once));
