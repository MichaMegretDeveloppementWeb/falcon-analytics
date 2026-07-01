/**
 * Falcon Analytics collector. Framework-agnostic, no dependencies.
 * Reads its configuration from window.__falconAnalytics (injected by the
 * @analyticsScripts Blade directive), auto-captures page views and clicks,
 * keeps the session alive with a visibility-gated heartbeat, and flushes
 * batches with navigator.sendBeacon.
 */
(function () {
  'use strict';

  var cfg = window.__falconAnalytics;
  if (!cfg || !cfg.endpoint || typeof document === 'undefined') {
    return;
  }

  var buffer = [];
  var flushTimer = null;
  var FLUSH_MS = cfg.flush || 5000;
  var HEARTBEAT_MS = cfg.heartbeat || 20000;

  function now() {
    return Date.now();
  }

  function baseEvent(type) {
    return { type: type, ts: now(), route: cfg.route || null, url: location.href };
  }

  function queue(event) {
    buffer.push(event);
    if (!flushTimer) {
      flushTimer = setTimeout(flush, FLUSH_MS);
    }
  }

  function flush() {
    if (flushTimer) {
      clearTimeout(flushTimer);
      flushTimer = null;
    }
    if (!buffer.length) {
      return;
    }

    var payload = JSON.stringify({
      sent_at: now(),
      referrer: document.referrer || null,
      events: buffer.splice(0, buffer.length),
    });

    send(payload);
  }

  function send(payload) {
    try {
      var blob = new Blob([payload], { type: 'application/json' });
      if (navigator.sendBeacon && navigator.sendBeacon(cfg.endpoint, blob)) {
        return;
      }
    } catch (e) {
      /* fall through to fetch */
    }

    try {
      fetch(cfg.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true,
        credentials: 'same-origin',
      });
    } catch (e) {
      /* give up silently: analytics must never break the page */
    }
  }

  function toSnake(key) {
    return key.replace(/[A-Z]/g, function (m) {
      return '_' + m.toLowerCase();
    });
  }

  /**
   * Walk from the clicked node up the tree, collecting the first event name,
   * value and section, plus every data-track-prop-*. Stops (and ignores the
   * click) as soon as a data-track-ignore is found.
   */
  function readTracking(node) {
    var name = null;
    var value = null;
    var section = null;
    var props = null;
    var el = node;

    while (el && el.nodeType === 1) {
      if (el.hasAttribute('data-track-ignore')) {
        return { ignored: true };
      }

      var data = el.dataset || {};
      if (name === null && data.trackEvent) {
        name = data.trackEvent;
      }
      if (value === null && data.trackValue != null && data.trackValue !== '') {
        var parsed = parseFloat(data.trackValue);
        if (!isNaN(parsed)) {
          value = parsed;
        }
      }
      if (section === null && data.trackSection) {
        section = data.trackSection;
      }
      for (var key in data) {
        if (key.indexOf('trackProp') === 0 && key.length > 9) {
          var prop = toSnake(key.charAt(9).toLowerCase() + key.slice(10));
          props = props || {};
          if (!(prop in props)) {
            props[prop] = data[key];
          }
        }
      }

      el = el.parentElement;
    }

    if (section) {
      props = props || {};
      if (!('section' in props)) {
        props.section = section;
      }
    }

    return { ignored: false, name: name, value: value, props: props };
  }

  function closestActionable(el) {
    while (el && el.nodeType === 1) {
      if (
        el.tagName === 'A' ||
        el.tagName === 'BUTTON' ||
        el.getAttribute('role') === 'button' ||
        el.hasAttribute('data-track-event')
      ) {
        return el;
      }
      el = el.parentElement;
    }
    return null;
  }

  function selectorFor(el) {
    if (el.id) {
      return ('#' + el.id).slice(0, 255);
    }
    var selector = el.tagName.toLowerCase();
    if (typeof el.className === 'string' && el.className.trim()) {
      selector += '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.');
    }
    return selector.slice(0, 255);
  }

  function onClick(e) {
    var node = e.target;
    if (!node || node.nodeType !== 1) {
      node = node && node.parentElement;
    }
    if (!node) {
      return;
    }

    var tracking = readTracking(node);
    if (tracking.ignored) {
      return;
    }

    var event = baseEvent('click');
    if (tracking.name) {
      event.name = tracking.name;
    }
    if (tracking.value != null) {
      event.value = tracking.value;
    }
    if (tracking.props) {
      event.props = tracking.props;
    }

    var actionable = closestActionable(node);
    if (actionable) {
      event.selector = selectorFor(actionable);
      var text = (actionable.innerText || actionable.textContent || '').trim();
      event.text = text ? text.slice(0, 120) : null;
    }

    queue(event);
  }

  function onHidden() {
    if (document.visibilityState === 'hidden') {
      flush();
    }
  }

  // Page view on load.
  queue(baseEvent('pageview'));

  // Delegated, passive click capture.
  document.addEventListener('click', onClick, { passive: true, capture: true });

  // Visibility-gated heartbeat keeps last_activity_at accurate for passive reading.
  setInterval(function () {
    if (document.visibilityState === 'visible') {
      queue(baseEvent('heartbeat'));
    }
  }, HEARTBEAT_MS);

  // Never lose the buffer when leaving or hiding the tab.
  document.addEventListener('visibilitychange', onHidden);
  window.addEventListener('pagehide', flush);
})();
