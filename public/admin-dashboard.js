(function(){
  function parseJSON(str, fallback){
    try { return JSON.parse(str); } catch(e){ return fallback; }
  }

  function renderBarChart(el, labels, values){
    const w = 900;
    const h = 180;
    const pad = 16;
    const max = Math.max(1, ...values);

    const barCount = values.length;
    const gap = 6;
    const barW = Math.max(10, Math.floor((w - pad*2 - gap*(barCount-1)) / barCount));

    let x = pad;
    const bars = values.map((v, i) => {
      const barH = Math.round((v / max) * (h - pad*2));
      const y = h - pad - barH;

      const title = `${labels[i]}: ${v}`;
      const rect = `<rect x="${x}" y="${y}" width="${barW}" height="${barH}" rx="8" ry="8" fill="rgba(67,24,255,.18)"></rect>
                    <rect x="${x}" y="${y}" width="${barW}" height="${barH}" rx="8" ry="8" fill="rgba(67,24,255,.55)" opacity="0.55"></rect>
                    <title>${title}</title>`;
      x += barW + gap;
      return rect;
    }).join('');

    const axis = `<line x1="${pad}" y1="${h-pad}" x2="${w-pad}" y2="${h-pad}" stroke="#e5e7eb" />`;

    el.innerHTML = `
      <svg viewBox="0 0 ${w} ${h}" role="img" aria-label="Bookings chart">
        ${axis}
        ${bars}
      </svg>
    `;
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.bp-chart').forEach(el => {
      const labels = parseJSON(el.getAttribute('data-labels') || '[]', []);
      const values = parseJSON(el.getAttribute('data-values') || '[]', []);
      renderBarChart(el, labels, values);
    });
  });
})();
