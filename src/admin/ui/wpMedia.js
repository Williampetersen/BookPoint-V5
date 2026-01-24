export function pickImage({ title = 'Select image', button = 'Use this image' } = {}) {
  return new Promise((resolve, reject) => {
    if (!window.wp || !window.wp.media) {
      reject(new Error('WordPress media library not loaded'));
      return;
    }
    const frame = window.wp.media({
      title,
      button: { text: button },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      resolve({
        id: attachment.id,
        url: attachment.sizes?.medium?.url || attachment.url,
        full: attachment.url,
      });
      frame.close();
    });

    frame.open();
  });
}
