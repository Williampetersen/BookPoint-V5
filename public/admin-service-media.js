(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const pickBtn = document.getElementById('bp_service_pick_image');
    const removeBtn = document.getElementById('bp_service_remove_image');
    const input = document.getElementById('bp_service_image_id');
    const preview = document.getElementById('bp_service_image_preview');

    if (!pickBtn || !input || !preview) return;

    let frame;

    pickBtn.addEventListener('click', function(e){
      e.preventDefault();

      if (frame) { frame.open(); return; }

      frame = wp.media({
        title: 'Select Service Image',
        button: { text: 'Use this image' },
        multiple: false
      });

      frame.on('select', function(){
        const att = frame.state().get('selection').first().toJSON();
        input.value = att.id;

        const url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
        preview.innerHTML = `<img src="${url}" style="width:140px;height:140px;object-fit:cover;border-radius:14px;border:1px solid #ddd;">`;
      });

      frame.open();
    });

    removeBtn && removeBtn.addEventListener('click', function(e){
      e.preventDefault();
      input.value = '';
      preview.innerHTML = `<div style="width:140px;height:140px;border-radius:14px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;color:#777;">No image</div>`;
    });
  });
})();
