(function($){
  function setMessage($wrap, msg, ok){
    $wrap.find('.bp-message').html('<div style="padding:10px;border:1px solid #ccc;">'+msg+'</div>');
  }

  // Step 18: Load agents for service
  function loadAgentsForService($wrap, serviceId){
    const $select = $wrap.find('.bp-agent');
    if(!$select.length) return;

    $select.prop('disabled', true);
    $select.empty().append('<option value="0">Any agent</option>');

    if(!serviceId || serviceId <= 0){
      $select.prop('disabled', false);
      return;
    }

    fetch(`/wp-json/bp/v1/service-agents?service_id=${serviceId}`)
      .then(r => r.json())
      .then(res => {
        const list = res && res.data ? res.data : [];
        if(Array.isArray(list)){
          list.forEach(a => {
            $select.append(`<option value="${a.id}">${a.name}</option>`);
          });
        }
      })
      .catch(()=>{})
      .finally(()=>{
        $select.prop('disabled', false);
      });
  }

  function loadSlots($wrap){
    const serviceId = $wrap.data('service-id');
    const nonce = $wrap.find('.bp-nonce').val();
    const date = $wrap.find('.bp-date').val();
    const agentId = $wrap.find('.bp-agent').val() || 0;

    if(!serviceId || serviceId <= 0){
      $wrap.find('.bp-time').html('<option value="">Select a service first</option>');
      return;
    }
    if(!date){ return; }

    const $select = $wrap.find('.bp-time');
    $select.html('<option>Loading...</option>');

    $.post(bp.ajax_url, {
      action: 'bp_slots',
      _wpnonce: nonce,
      service_id: serviceId,
      date: date,
      agent_id: agentId
    }).done(function(res){
      if(!res || res.status !== 'success'){
        $select.html('<option value="">No slots</option>');
        setMessage($wrap, (res && res.message) ? res.message : 'Error', false);
        return;
      }
      const slots = (res.data && res.data.slots) ? res.data.slots : [];
      if(!slots.length){
        $select.html('<option value="">No slots available</option>');
        return;
      }
      const opts = ['<option value="">Select time</option>'].concat(
        slots.map(t => `<option value="${t}">${t}</option>`)
      );
      $select.html(opts.join(''));
    }).fail(function(){
      $select.html('<option value="">No slots</option>');
      setMessage($wrap, 'Request failed', false);
    });
  }

  function submitBooking($wrap){
    const serviceId = $wrap.data('service-id');
    const nonce = $wrap.find('.bp-nonce').val();

    const payload = {
      action: 'bp_submit_booking',
      _wpnonce: nonce,
      bp_hp: $wrap.find('.bp-hp').val(),
      service_id: serviceId,
      date: $wrap.find('.bp-date').val(),
      time: $wrap.find('.bp-time').val(),
      first_name: $wrap.find('.bp-first-name').val(),
      last_name: $wrap.find('.bp-last-name').val(),
      email: $wrap.find('.bp-email').val(),
      phone: $wrap.find('.bp-phone').val(),
      notes: $wrap.find('.bp-notes').val(),
      agent_id: $wrap.find('.bp-agent').val() || 0
    };

    if(!payload.service_id || payload.service_id <= 0){
      setMessage($wrap, 'Please select a service.', false);
      return;
    }
    if(!payload.date || !payload.time){
      setMessage($wrap, 'Please select date and time.', false);
      return;
    }

    const requirePhone = $wrap.data('require-phone') === 1 || $wrap.data('require-phone') === '1';
    if(requirePhone && !payload.phone){
      setMessage($wrap, 'Phone is required.', false);
      return;
    }

    $wrap.find('.bp-submit').prop('disabled', true).text('Booking...');

    $.post(bp.ajax_url, payload).done(function(res){
      if(!res || res.status !== 'success'){
        setMessage($wrap, (res && res.message) ? res.message : 'Error', false);
        return;
      }
      const manageUrl = res.data && res.data.manage_url ? res.data.manage_url : '';
      const msg = res.message + (manageUrl ? `<br><a href="${manageUrl}">Manage booking</a>` : '');
      setMessage($wrap, msg, true);
    }).fail(function(){
      setMessage($wrap, 'Request failed', false);
    }).always(function(){
      $wrap.find('.bp-submit').prop('disabled', false).text('Book now');
    });
  }

  $(document).on('change', '.bp-book-form .bp-date', function(){
    loadSlots($(this).closest('.bp-book-form'));
  });

  $(document).on('change', '.bp-book-form .bp-service', function(){
    const $wrap = $(this).closest('.bp-book-form');
    const serviceId = parseInt($(this).val() || '0', 10);
    $wrap.data('service-id', serviceId);
    loadAgentsForService($wrap, serviceId);
    loadSlots($wrap);
  });

  // Step 16: Reload slots when agent changes
  $(document).on('change', '.bp-book-form .bp-agent', function(){
    loadSlots($(this).closest('.bp-book-form'));
  });

  $(document).on('click', '.bp-book-form .bp-submit', function(){
    submitBooking($(this).closest('.bp-book-form'));
  });

  function loadServices($wrap){
    const $select = $wrap.find('.bp-service');
    if(!$select.length) return;

    $select.prop('disabled', true);

    fetch('/wp-json/bp/v1/services')
      .then(r => r.json())
      .then(res => {
        const list = res && res.data ? res.data : [];
        if(Array.isArray(list)){
          list.forEach(s => {
            $select.append(`<option value="${s.id}">${s.name}</option>`);
          });
        }
      })
      .catch(()=>{})
      .finally(()=>{
        $select.prop('disabled', false);
      });
  }

  $(function(){
    $('.bp-book-form').each(function(){
      const $wrap = $(this);
      const serviceId = $wrap.data('service-id');
      const hasServiceSelect = $wrap.find('.bp-service').length > 0;

      if(hasServiceSelect){
        loadServices($wrap);
      } else {
        loadAgentsForService($wrap, serviceId);
      }

      const date = $wrap.find('.bp-date').val();
      if(date){
        loadSlots($wrap);
      }
    });
  });

})(jQuery);

