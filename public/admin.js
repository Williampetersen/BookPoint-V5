(function () {
  var script = document.currentScript;
  if (!script || !script.src) return;

  var buildJs = script.src.replace(/\/public\/admin\.js(\?.*)?$/, '/build/admin.js');
  var el = document.createElement('script');
  el.src = buildJs;
  el.defer = true;
  document.head.appendChild(el);

  var buildCss = script.src.replace(/\/public\/admin\.js(\?.*)?$/, '/build/admin.css');
  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = buildCss;
  document.head.appendChild(link);
})();
