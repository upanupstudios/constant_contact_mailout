(function ($, Drupal)
{
  Drupal.behaviors.constant_contact_mailout_widget = {
    attach: function (context, settings)
    {
      if($('.sendnow-checkbox').is(':checked')) {
        $('.sendnow-contact-lists').show();
      } else {
        $('.sendnow-contact-lists').hide();
      }

      if($('.sendlater-checkbox').is(':checked')) {
        $('.sendlater-datetime').parent('.form-datetime-wrapper').show();
      } else {
        $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
      }

      $('.sendnow-checkbox').click(function() {
        if($(this).is(':checked')) {
          $('.sendlater-checkbox').prop('checked', false);
          $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
        }

        if($(this).is(':checked')) {
          $('.sendnow-contact-lists').show();
        } else {
          $('.sendnow-contact-lists').hide();
        }
      });

      $('.sendlater-checkbox').click(function() {
        if($(this).is(':checked')) {
          $('.sendnow-checkbox').prop('checked', false);
          $('.sendlater-datetime').parent('.form-datetime-wrapper').show();
        } else {
          $('.sendlater-datetime').parent('.form-datetime-wrapper').hide();
        }
      });
    }
  };
}(jQuery, Drupal));
