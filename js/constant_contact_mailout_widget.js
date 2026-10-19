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

      // Change-list checkbox: shows/hides the contact list select.
      $(once('ccm-widget-change-list', '.change-list-checkbox', context)).each(function () {
        var $self = $(this);
        var $select = $('.change-list-contact_list_id').closest('.form-item');

        if ($self.is(':checked')) {
          $select.show();
        } else {
          $select.hide();
        }

        $self.on('change', function () {
          if ($self.is(':checked')) {
            $select.show();
          } else {
            $select.hide();
          }
        });
      });
    }
  };
}(jQuery, Drupal, once));
