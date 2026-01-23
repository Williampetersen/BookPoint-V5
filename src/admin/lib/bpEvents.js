const listeners = {};

export function bpEmit(name, payload) {
  (listeners[name] || []).forEach(fn => fn(payload));
}

export function bpOn(name, fn) {
  listeners[name] = listeners[name] || [];
  listeners[name].push(fn);
  return () => {
    listeners[name] = listeners[name].filter(x => x !== fn);
  };
}
