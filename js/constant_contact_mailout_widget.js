(function ($, Drupal, once)
{
  function init() {
    if ($('.sendnow-checkbox').is(':checked')) {
      $('.sendnow-contact-lists').show();
    } else {
      $('.sendnow-contact-lists').hide();
    }

    if ($('.sendlater-checkbox').is(':checked')) {
      $('.sendlater-datetime').parent('.form-datetime-wrapper').show();
    } else {
      $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
    }

    $('.sendnow-checkbox').click(function () {
      if ($(this).is(':checked')) {
        $('.sendlater-checkbox').prop('checked', false);
        $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
      }

      if ($(this).is(':checked')) {
        $('.sendnow-contact-lists').show();
      } else {
        $('.sendnow-contact-lists').hide();
      }
    });

    $('.sendlater-checkbox').click(function () {
      if ($(this).is(':checked')) {
        $('.sendnow-checkbox').prop('checked', false);
        $('.sendlater-datetime').parent('.form-datetime-wrapper').show();
      } else {
        $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
      }
    });
  }

  Drupal.behaviors.constantContactMailoutWidget = {
    attach: function (context, settings)
    {
      // Only initialise once per widget, so handlers are not bound again
      // every time behaviors are re-attached.
      if (once('constant-contact-mailout-widget', '.sendnow-checkbox', context).length) {
        init();
      }
    }
  };
}(jQuery, Drupal, once));
