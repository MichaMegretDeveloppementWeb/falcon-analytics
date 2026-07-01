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

  // Server-side limits (keep in sync with IngestBatchRequest): truncate here so
  // one oversized field can never 422 the whole batch, and cap the batch size.
  var MAX_URL = 2048;
  var MAX_NAME = 120;
  var MAX_TEXT = 120;
  var MAX_SELECTOR = 255;
  var MAX_BATCH = 100;
  var MAX_BUFFER = 500; // drop oldest beyond this if the endpoint is unreachable

  var buffer = [];
  var flushTimer = null;
  var FLUSH_MS = cfg.flush || 5000;
  var HEARTBEAT_MS = cfg.heartbeat || 20000;

  function now() {
    return Date.now();
  }

  function cap(value, max) {
    return typeof value === 'string' && value.length > max ? value.slice(0, max) : value;
  }

  function baseEvent(type) {
    return { type: type, ts: now(), route: cfg.route || null, url: cap(location.href, MAX_URL) };
  }

  function queue(event) {
    if (buffer.length >= MAX_BUFFER) {
      buffer.shift();
    }
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

    var referrer = cap(document.referrer, MAX_URL) || null;

    // Chunk into batches the server accepts (events max is MAX_BATCH).
    while (buffer.length) {
      var chunk = buffer.splice(0, MAX_BATCH);
      send(JSON.stringify({ sent_at: now(), referrer: referrer, events: chunk }));
    }
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

  function has(object, key) {
    return Object.prototype.hasOwnProperty.call(object, key);
  }

  function readProps(data, props) {
    for (var key in data) {
      // data-track-prop-<name> -> dataset key trackProp<Name>; require the camel
      // boundary so data-track-property etc. don't over-match.
      if (key.indexOf('trackProp') === 0 && key.length > 9 && key.charAt(9) >= 'A' && key.charAt(9) <= 'Z') {
        var prop = toSnake(key.charAt(9).toLowerCase() + key.slice(10));
        props = props || {};
        if (!has(props, prop)) {
          props[prop] = data[key];
        }
      }
    }
    return props;
  }

  function isActionable(el) {
    return (
      el.tagName === 'A' ||
      el.tagName === 'BUTTON' ||
      el.getAttribute('role') === 'button' ||
      el.hasAttribute('data-track-event')
    );
  }

  /**
   * Single walk from the clicked node up the tree: collects the first event
   * name/value/section, every data-track-prop-*, and the nearest actionable
   * ancestor. Stops (and ignores the click) on data-track-ignore.
   */
  function inspect(node) {
    var name = null;
    var value = null;
    var section = null;
    var props = null;
    var actionable = null;
    var el = node;

    while (el && el.nodeType === 1) {
      if (el.hasAttribute('data-track-ignore')) {
        return { ignored: true };
      }
      if (!actionable && isActionable(el)) {
        actionable = el;
      }

      var data = el.dataset || {};
      // data-track-event on a <form> is captured on submit, not on click.
      if (name === null && data.trackEvent && el.tagName !== 'FORM') {
        name = cap(data.trackEvent, MAX_NAME);
      }
      if (value === null && data.trackValue != null && data.trackValue !== '') {
        var parsed = Number(data.trackValue);
        if (Number.isFinite(parsed)) {
          value = parsed;
        }
      }
      if (section === null && data.trackSection) {
        section = data.trackSection;
      }
      props = readProps(data, props);

      el = el.parentElement;
    }

    if (section) {
      props = props || {};
      if (!has(props, 'section')) {
        props.section = section;
      }
    }

    return { ignored: false, name: name, value: value, props: props, actionable: actionable };
  }

  function selectorFor(el) {
    if (el.id) {
      return ('#' + el.id).slice(0, MAX_SELECTOR);
    }
    var selector = el.tagName.toLowerCase();
    if (typeof el.className === 'string' && el.className.trim()) {
      selector += '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.');
    }
    return selector.slice(0, MAX_SELECTOR);
  }

  function onClick(e) {
    var node = e.target;
    if (!node || node.nodeType !== 1) {
      node = node && node.parentElement;
    }
    if (!node) {
      return;
    }

    var found = inspect(node);
    if (found.ignored) {
      return;
    }

    var event = baseEvent('click');
    if (found.name) {
      event.name = found.name;
    }
    if (found.value != null) {
      event.value = found.value;
    }
    if (found.props) {
      event.props = found.props;
    }

    if (found.actionable) {
      event.selector = selectorFor(found.actionable);
      // textContent (not innerText) avoids a synchronous layout reflow on click.
      var text = (found.actionable.textContent || '').trim();
      event.text = text ? text.slice(0, MAX_TEXT) : null;
    }

    queue(event);
  }

  function onSubmit(e) {
    var form = e.target;
    if (!form || form.nodeType !== 1 || !form.dataset || !form.dataset.trackEvent) {
      return;
    }

    var data = form.dataset;
    var event = baseEvent('click');
    event.name = cap(data.trackEvent, MAX_NAME);

    if (data.trackValue != null && data.trackValue !== '') {
      var parsed = Number(data.trackValue);
      if (Number.isFinite(parsed)) {
        event.value = parsed;
      }
    }

    var props = readProps(data, null);
    if (props) {
      event.props = props;
    }

    event.selector = selectorFor(form);
    queue(event);
  }

  function onHidden() {
    if (document.visibilityState === 'hidden') {
      flush();
    }
  }

  // Page view on load.
  queue(baseEvent('pageview'));

  // Delegated click capture. Capture phase so app handlers calling
  // stopPropagation can't swallow it; not passive (passive is a no-op for click).
  document.addEventListener('click', onClick, true);

  // Delegated form submit (capture) so Enter-key submissions are tracked too.
  document.addEventListener('submit', onSubmit, true);

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
