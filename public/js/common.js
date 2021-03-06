/**
 * Shows users a message.
 * Currently uses humane.js
 *
 * @param string message
 * @returns void
 */
function showMessage (message) {
  humane.log(message, {
    timeout: 3500
  })
}

function showHelp (message) {
  humane.log(message, {
    timeout: 12000
  })
}

function hideMessage () {
  humane.remove()
}
/**
 * Custom file inputs
 */
$(document).on('change', '.btn-file :file', function () {
  var input = $(this),
    numFiles = input.get(0).files ? input.get(0).files.length : 1,
    label = input.val().replace(/\\/g, '/').replace(/.*\//, '')

  var input = input.parents('.form-group').find(':text'),
    log = numFiles > 1 ? numFiles + ' files selected' : label
  if (input.length) {
    input.val(log)
  } else {
    if (log) {
      console.log(log)
    }

  }

})

/*
 * --------------------
 * Create a simple way to show remote dynamic modals from the frontend
 * --------------------
 *
 * E.g :
 * <a href='/route/to/modal' class='loadModal'>
 *  Click For Modal
 * </a>
 *
 */
$(document.body).on('click', '.loadModal, [data-invoke~=modal]', function (e) {
  var loadUrl = $(this).data('href'),
    modalId = $(this).data('modal-id'),
    cacheResult = $(this).data('cache') === 'on'

  // $('#' + modalId).remove()
  $('.modal').remove()
  $('html').addClass('working')

  /*
   * Hopefully this message will rarely show
   */
  setTimeout(function () {
    // showMessage('One second...'); #far to annoying
  }, 750)

  $.ajax({
    url: loadUrl,
    data: {'modal_id': modalId},
    localCache: cacheResult,
    dataType: 'html',
    success: function (data) {
      hideMessage()

      $('body').append(data)

      var $modal = $('#' + modalId)

      $modal.modal({
        'backdrop': 'static'
      })

      $modal.modal('show')

      $modal.on('hidden.bs.modal', function (e) {
        // window
        location.hash = ''
      })

      $('html').removeClass('working')
    }
  }).done().fail(function (data) {
    $('html').removeClass('working')
    showMessage('Whoops!, something has gone wrong.<br><br>' + data.status + ' ' + data.statusText)
  })

  e.preventDefault()
})

/*
 * ------------------------------------------------------------
 * A slightly hackish way to close modals on back button press.
 * ------------------------------------------------------------
 */
$(window).on('hashchange', function (e) {
  $('.modal').modal('hide')
})

/*
 * -------------------------------------------------------------
 * Simple way for any type of object to be deleted.
 * -------------------------------------------------------------
 *
 * E.g markup:
 * <a data-route='/route/to/delete' data-id='123' data-type='objectType'>
 *  Delete This Object
 * </a>
 *
 */
$('.deleteThis').on('click', function (e) {
  /*
   * Confirm if the user wants to delete this object
   */
  if ($(this).data('confirm-delete') !== 'yes') {
    $(this).data('original-text', $(this).html()).html('Click To Confirm?').data('confirm-delete', 'yes')

    var that = $(this)
    setTimeout(function () {
      that.data('confirm-delete', 'no').html(that.data('original-text'))
    }, 2000)

    return
  }

  var deleteId = $(this).data('id'),
    deleteType = $(this).data('type'),
    route = $(this).data('route')

  $.post(route, deleteType + '_id=' + deleteId)
    .done(function (data) {
      if (typeof data.message !== 'undefined') {
        showMessage(data.message)
      }

      switch (data.status) {
        case 'success':
          $('#' + deleteType + '_' + deleteId).fadeOut()
          break
        case 'error':
          /* Error */
          break

        default:
          break
      }
    }).fail(function (data) {
    showMessage(Attendize.GenericErrorMessages)
  })

  e.preventDefault()
})

/*
 * ------------------------------------------------------------
 * Toggle hidden content when a.show-more-content is clicked
 * ------------------------------------------------------------
 */
$(document.body).on('click', '.show-more-options', function (e) {
  var toggleClass = !$(this).data('toggle-class')
    ? '.more-options'
    : $(this).data('toggle-class')

  if ($(this).hasClass('toggled')) {
    $(this).html($(this)
      .data('original-text'))

  } else {
    if (!$(this).data('original-text')) {
      $(this).data('original-text', $(this).html())
    }
    $(this).html(!$(this).data('show-less-text') ? 'Show Less' : $(this).data('show-less-text'))
  }

  $(this).toggleClass('toggled')

  /*
   * ?
   */
  if ($(this).data('clear-field')) {
    $($(this).data('clear-field')).val('')
  }

  $(toggleClass).slideToggle()
  e.preventDefault()
})
