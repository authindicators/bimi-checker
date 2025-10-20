(function($){
  // Optional: ping admin-ajax to detect nonce/caching/security issues that cause -1 or 403
  function pingAjax() {
    return $.ajax({
      url: (window.BIMIChecker && BIMIChecker.ajaxUrl) ? BIMIChecker.ajaxUrl : '',
      method: 'POST',
      data: {
        action: 'bimi_checker_ping',
        _ajax_nonce: (window.BIMIChecker && BIMIChecker.nonce) ? BIMIChecker.nonce : ''
      }
    });
  }

  $(function(){
    // Run a background ping once per page load
    if (window.BIMIChecker && BIMIChecker.ajaxUrl) {
      pingAjax().fail(function(xhr){
        var $wrap = $('.bimichecker');
        if (!$wrap.length) return;

        var msg = 'AJAX error. ';
        if (xhr && xhr.status === 403) {
          msg += '403 Forbidden – likely a nonce, caching, or security filter issue blocking admin-ajax.php.';
        } else if (xhr && xhr.responseText === '-1') {
          msg += '-1 from admin-ajax – missing/invalid nonce, or the request was stripped by caching.';
        } else {
          msg += 'Check server logs or security rules.';
        }
        var $warn = $('<div class="alert error" style="margin-top:10px;"></div>').text(msg);
        $wrap.prepend($warn);
      });
    }

    // Minor UX: disable button briefly on submit
    $(document).on('submit', '.bimichecker-form', function(){
      var $btn = $(this).find('.bimichecker-btn');
      $btn.addClass('loading').prop('disabled', true);
      setTimeout(function(){ $btn.removeClass('loading').prop('disabled', false); }, 1500);
    });
  });
})(jQuery);
