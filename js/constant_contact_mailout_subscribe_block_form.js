(function ($, Drupal, once)
{
  // @todo Can the block be installed multiple times? How to identify?
  var $subscribeBlockForm = null;

  function validateSubscribeBlockForm(message = null) {
    var formValid = true;

    $subscribeBlockForm = $('form.constant-contact-mailout-subscribe-block-form');

    if ($subscribeBlockForm.length == 1) {
      $subscribeBlockForm.find('.details-success-message').remove();
      $subscribeBlockForm.find('.details-error-message').remove();

      // Validate fields not empty.
      var fields = {
        'first-name': 'First Name',
        'last-name': 'Last Name',
        'email': 'Email',
        'confirm-email': 'Confirm Email'
      };

      $.each(fields, function(index, value) {
        $subscribeBlockForm.find('.' + index).parent().removeClass('form-item--error');
        $subscribeBlockForm.find('.' + index).removeClass('error');

        if (formValid == true) {
          if (!(formValid = $subscribeBlockForm.find('.' + index).val().trim() != "")) {
            // Add error message
            $subscribeBlockForm.prepend('<div class="details-error-message">' + value + ' is required.</div>');

            $subscribeBlockForm.find('.' + index).parent().addClass('form-item--error');
            $subscribeBlockForm.find('.' + index).addClass('error');
          }
        }
      });

      $subscribeBlockForm.find('.confirm-email').parent().removeClass('form-item--error');
      $subscribeBlockForm.find('.confirm-email').removeClass('error');

      if (formValid == true) {
        var email = $subscribeBlockForm.find('.email').val().trim();
        var confirmEmail = $subscribeBlockForm.find('.confirm-email').val().trim();

        if (!(formValid = email === confirmEmail)) {
          // Add error message
          $subscribeBlockForm.prepend('<div class="details-error-message">Confirm Email must match Email.</div>');

          $subscribeBlockForm.find('.confirm-email').parent().addClass('form-item--error');
          $subscribeBlockForm.find('.confirm-email').addClass('error');
        }
      }

      // Checklist must be checked
      if(formValid == true) {
        if (!(formValid = $subscribeBlockForm.find('.contact-list-ids input[type=checkbox]:checked').length != 0)) {
          // Add error message
          $subscribeBlockForm.prepend('<div class="details-error-message">Select a list to subscribe to.</div>');
        }
      }

      if(formValid == true) {
        if(!(formValid = message == null)) {
          // Add error message
          $subscribeBlockForm.prepend('<div class="details-error-message">' + Drupal.checkPlain(message) + '</div>');
        }
      }
    }

    return formValid;
  }

  function submitSubscribeBlockForm(message = null) {
    var formValid = validateSubscribeBlockForm();

    if(formValid) {
      // Empty fields
      $subscribeBlockForm.find('.first-name').val("");
      $subscribeBlockForm.find('.last-name').val("");
      $subscribeBlockForm.find('.email').val("");
      $subscribeBlockForm.find('.confirm-email').val("");

      // Uncheck checkboxes
      $subscribeBlockForm.find('.contact-list-ids input[type=checkbox]').prop('checked', false);

      if(message != null) {
        $subscribeBlockForm.prepend('<div class="details-success-message">' + Drupal.checkPlain(message) + '</div>');
      }
    }
  }

  Drupal.behaviors.constantContactMailoutSubscribeBlockForm = {
    attach: function(context, settings)
    {
      // Test submit.
      // submitSubscribeBlockForm();
    },
  };

  // Argument passed from InvokeCommand.
  $.fn.validateSubscribeBlockFormCallback = function(message) {
    validateSubscribeBlockForm(message);
  };

  $.fn.submitSubscribeBlockFormCallback = function(message) {
    submitSubscribeBlockForm(message);
  };
})(jQuery, Drupal, once);
