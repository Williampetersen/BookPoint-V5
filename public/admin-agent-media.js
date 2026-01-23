(function($){
  function renderPreview(url){
    var $preview = $('#bp_agent_image_preview');
    if(!url){
      $preview.html('<div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">No image</div>');
      return;
    }
    $preview.html('<img src="' + url + '" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">');
  }

  $(document).on('click', '#bp_agent_pick_image', function(e){
    e.preventDefault();
    var frame = wp.media({
      title: 'Select Agent Image',
      button: { text: 'Use this image' },
      multiple: false
    });
    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#bp_agent_image_id').val(attachment.id);
      renderPreview(attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
    });
    frame.open();
  });

  $(document).on('click', '#bp_agent_remove_image', function(e){
    e.preventDefault();
    $('#bp_agent_image_id').val('');
    renderPreview('');
  });
})(jQuery);
