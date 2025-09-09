(function($){
  function icon(state){
    if (state === 'ok')   return '<span class="ic ic-ok" aria-hidden="true"></span>';
    if (state === 'warn') return '<span class="ic ic-warn" aria-hidden="true"></span>';
    if (state === 'error')return '<span class="ic ic-err" aria-hidden="true"></span>';
    return '<span class="ic" aria-hidden="true"></span>';
  }

  // Auto-link URL details so long URLs are clickable
  function formatDetail(detail){
    if (!detail) return '';
    var str = String(detail);
    if (/^https?:\/\//i.test(str)) {
      return '<a href="'+ str +'" target="_blank" rel="noopener noreferrer">'+ str +'</a>';
    }
    return str;
  }

  function li(item){
    // item = { state, label, detail }
    var cls = 'status-item status-' + (item.state || 'info');
    var detailHtml = item.detail ? '<span class="detail">'+ formatDetail(item.detail) +'</span>' : '';
    return '<li class="'+cls+'">'+ icon(item.state) +'<span class="label">'+ item.label +'</span>'+ detailHtml +'</li>';
  }

  $('#bimichecker-form').on('submit', function(e){
    e.preventDefault();

    const domain   = $('#bimi-domain').val().trim();
    const selector = ($('#bimi-selector').val().trim() || 'default');
    const $btn     = $('.bimichecker-btn');

    $btn.addClass('loading').prop('disabled', true);

    $.ajax({
      url: bimiChecker.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'bimi_checker_check',
        nonce: bimiChecker.nonce,
        domain: domain,
        selector: selector
      }
    }).done(function(res){
      $('#bimichecker-results').prop('hidden', false);
      const $bimi  = $('#bimi-status').empty();
      const $dmarc = $('#dmarc-status').empty();

      if (!res || !res.success) {
        const msg = (res && res.data && res.data.message) ? res.data.message : 'Unexpected error.';
        $bimi.append(li({state:'error', label: 'AJAX error', detail: msg}));
        return;
      }

      (res.data.bimi || []).forEach(function(item){ $bimi.append(li(item)); });
      (res.data.dmarc || []).forEach(function(item){ $dmarc.append(li(item)); });

      // Preview
      if (res.data.logo) {
        $('#mock-logo').css('background-image', 'url("'+res.data.logo+'")');
      } else {
        $('#mock-logo').css('background-image', 'none');
      }
      $('#mock-from').text(domain + ' <email@' + domain + '>');
      $('#mock-subject').text('Subject: Example subject for ' + domain);
    }).fail(function(xhr){
      $('#bimichecker-results').prop('hidden', false);
      const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
        ? xhr.responseJSON.data.message
        : (xhr.responseText || (xhr.status + ' ' + xhr.statusText));
      $('#bimi-status').html(li({state:'error', label:'AJAX failed', detail: msg}));
    }).always(function(){
      $btn.removeClass('loading').prop('disabled', false);
    });
  });
})(jQuery);
