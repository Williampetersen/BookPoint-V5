import React from "react";

/**
 * Requires wp.media to be available (in wp-admin it is).
 * In PHP enqueue for this screen: wp_enqueue_media();
 */
export default function WpMediaPicker({
  label = "Step image",
  valueId,
  valueUrl,
  onChange,
  help,
}) {
  const open = () => {
    if (!window.wp?.media) {
      alert("WordPress media library is not available. (wp.media)");
      return;
    }

    const frame = window.wp.media({
      title: "Select image",
      button: { text: "Use this image" },
      multiple: false,
      library: { type: "image" },
    });

    frame.on("select", () => {
      const attachment = frame.state().get("selection").first().toJSON();
      onChange?.({
        imageId: attachment.id,
        imageUrl: attachment.url,
      });
    });

    frame.open();
  };

  return (
    <div className="bp-mt-12">
      <label className="bp-label">{label}</label>

      <div className="bp-media-row">
        <div className="bp-media-preview">
          {valueUrl ? <img src={valueUrl} alt="" /> : <div className="bp-media-empty">No image</div>}
        </div>

        <div className="bp-media-actions">
          <button type="button" className="bp-btn bp-btn-ghost" onClick={open}>
            Choose / Upload
          </button>

          {(valueId || valueUrl) ? (
            <button
              type="button"
              className="bp-btn bp-btn-ghost"
              onClick={() => onChange?.({ imageId: null, imageUrl: "" })}
            >
              Remove
            </button>
          ) : null}

          <div className="bp-text-xs bp-muted">
            {help || "Recommended: 72x72 or SVG/PNG."}
          </div>
        </div>
      </div>
    </div>
  );
}
