(function ($, Drupal, once)
{
  var initialized = false;
  var $subscribeBlockForm = null;
  var $test = false;

  function init() {
    $subscribeBlockForm = $('form.constant-contact-mailout-subscribe-block-form');

    // Run once
    if (!initialized && $subscribeBlockForm.length == 1) {
      initialized = true;

      if ($subscribeBlockForm.find('fieldset.contact-list-ids').length != 0) {
        if ($subscribeBlockForm.find('#edit-contact-list-ids-all-all').length) {
          $subscribeBlockForm.find('#edit-contact-list-ids-all-all').click(function() {
            var $isAllChecked = $(this).is(':checked');
            $subscribeBlockForm.find('input[type=checkbox]').prop('checked', $isAllChecked);
          });
        }
        else {
          $subscribeBlockForm.find('input[value=all]').click(function() {
            var $isAllChecked = $(this).is(':checked');
            $subscribeBlockForm.find('input[type=checkbox]').not('input[value=all]').prop('checked', $isAllChecked);
          });
        }

        $subscribeBlockForm.find('input[type=checkbox]').not('input[value=all]').click(function() {
          var $isAllChecked = $subscribeBlockForm.find('input[type=checkbox]').not('input[value=all]').length == $subscribeBlockForm.find('input[type=checkbox]:checked').not('input[value=all]').length;

          if ($subscribeBlockForm.find('#edit-contact-list-ids-all-all').length) {
            $subscribeBlockForm.find('#edit-contact-list-ids-all-all').prop('checked', $isAllChecked);
          }
          else {
            $subscribeBlockForm.find('input[value=all]').prop('checked', $isAllChecked);
          }
        });
      }

      if ($test) {
        $subscribeBlockForm.find('#edit-first-name').val('Marco');
        $subscribeBlockForm.find('#edit-last-name').val('Maranao');
        $subscribeBlockForm.find('#edit-email').val('marcom@upanup.com');
        $subscribeBlockForm.find('#edit-confirm-email').val('marcom@upanup.com');

        // submitSubscribeBlockForm();
      }

    }
  }

  function validateSubscribeBlockForm(message = null) {
    var formValid = true;

    if ($subscribeBlockForm.length == 1) {
      // Remove messages.
      $subscribeBlockForm.find('.messages').remove();

      // Validate fields not empty.
      var fields = {
        'first-name': 'First Name',
        'last-name': 'Last Name',
        'email': 'Email',
        'confirm-email': 'Confirm Email'
      };

      $.each(fields, function(index, value) {
        if (formValid == true && $subscribeBlockForm.find('.' + index).length != 0) {
          $subscribeBlockForm.find('.' + index).parent().removeClass('form-item--error');
          $subscribeBlockForm.find('.' + index).removeClass('error');

          if (index == 'email') {
            if (formValid = $subscribeBlockForm.find('.' + index).val().trim() != "") {
              if (!(formValid = isValidEmail($subscribeBlockForm.find('.' + index).val().trim()))) {
                displayMessage('error', value + ' is invalid.', '.' + index);
              }
            }
            else {
              displayMessage('error', value + ' is required.', '.' + index);
            }
          }
          else {
            if (!(formValid = $subscribeBlockForm.find('.' + index).val().trim() != "")) {
              displayMessage('error', value + ' is required.', '.' + index);
            }
          }
        }
      });

      if (formValid == true && $subscribeBlockForm.find('.confirm-email').length != 0) {
        $subscribeBlockForm.find('.confirm-email').parent().removeClass('form-item--error');
        $subscribeBlockForm.find('.confirm-email').removeClass('error');

        var email = $subscribeBlockForm.find('.email').val().trim();
        var confirmEmail = $subscribeBlockForm.find('.confirm-email').val().trim();

        if (!(formValid = email === confirmEmail)) {
          displayMessage('error', 'Confirm Email must match Email.', '.confirm-email');
        }
      }

      // Checklist must be checked
      if(formValid == true) {
        if ($subscribeBlockForm.find('.contact-list-ids input[type=checkbox]').not('.contact-list-ids input[value=all]').length > 0) {
          $subscribeBlockForm.find('.contact-list-ids').parent().removeClass('form-item--error');
          $subscribeBlockForm.find('.contact-list-ids').removeClass('error');

          if (!(formValid = $subscribeBlockForm.find('.contact-list-ids input[type=checkbox]:checked').not('.contact-list-ids input[value=all]').length != 0)) {
            displayMessage('error', 'Select a list to subscribe to.', '.contact-list-ids');
          }
        }
      }

      if(formValid == true) {
        if(!(formValid = message == null)) {
          displayMessage('error', Drupal.checkPlain(message));
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

      if (message != null) {
        displayMessage('status', Drupal.checkPlain(message));
      }
    }
  }

  function isValidEmail(email) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailPattern.test(email);
  }

  function displayMessage(type, message, selector) {
    // Decodes entities in message.
    var $message = {
      type: type,
      message: $("<div>").html(message).text(),
    };

    // Add error message
    $subscribeBlockForm.prepend($('#MessageTemplate').tmpl($message));

    if (selector) {
      $subscribeBlockForm.find(selector).parent().addClass('form-item--error');
      $subscribeBlockForm.find(selector).addClass('error');
    }
  }

  Drupal.behaviors.constantContactMailoutSubscribeBlockForm = {
    attach: function(context, settings)
    {
      init();
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
