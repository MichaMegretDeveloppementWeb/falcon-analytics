/**
 * Falcon Analytics collector. Framework-agnostic, no dependencies.
 * Reads its configuration from window.__falconAnalytics (injected by the
 * @analyticsCollector Blade directive), auto-captures page views and clicks,
 * keeps the session alive with a visibility-gated heartbeat, and flushes
 * batches with navigator.sendBeacon.
 */
(function () {
  'use strict';

  const cfg = window.__falconAnalytics;
  if (!cfg || !cfg.endpoint || typeof document === 'undefined') {
    return;
  }

  // A page that loads the collector twice keeps the first, or it counts everything twice.
  if (window.__falconAnalyticsStarted) {
    return;
  }
  window.__falconAnalyticsStarted = true;

  // Server-side limits (keep in sync with IngestBatchRequest): truncate here so
  // one oversized field can never 422 the whole batch, and cap the batch size.
  const MAX_URL = 2048;
  const MAX_NAME = 120;
  const MAX_TEXT = 255;
  const MAX_SELECTOR = 255;
  const MIN_SCORE = -2147483648;
  const MAX_SCORE = 2147483647;
  const MAX_BATCH = 100;
  const MAX_BUFFER = 500; // the oldest go first when more than this piles up between two flushes

  const buffer = [];
  let flushTimer = null;
  const FLUSH_MS = cfg.flush || 5000;
  const HEARTBEAT_MS = cfg.heartbeat || 20000;

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

    const referrer = cap(document.referrer, MAX_URL) || null;

    while (buffer.length) {
      const chunk = buffer.splice(0, MAX_BATCH);
      send(JSON.stringify({ sent_at: now(), referrer: referrer, events: chunk }));
    }
  }

  function send(payload) {
    try {
      const blob = new Blob([payload], { type: 'application/json' });
      if (navigator.sendBeacon && navigator.sendBeacon(cfg.endpoint, blob)) {
        return;
      }
    } catch (_e) {
      /* fetch below is the fallback */
    }

    try {
      fetch(cfg.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true,
        credentials: 'same-origin',
      }).catch(function () {
        /* unreachable endpoint: swallow the rejection so the host console
           never shows an uncaught promise from analytics */
      });
    } catch (_e) {
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

  /**
   * The score an element declares, as a whole number of points, or null. The
   * server refuses a batch holding any other score, so one bad attribute must
   * not cost the events sent with it.
   */
  function scoreOf(raw) {
    if (raw == null || raw === '') {
      return null;
    }
    const parsed = Number(raw);
    return Number.isInteger(parsed) && parsed >= MIN_SCORE && parsed <= MAX_SCORE ? parsed : null;
  }

  function readProps(data, props) {
    for (const key in data) {
      // data-track-prop-<name> -> dataset key trackProp<Name>; require the camel
      // boundary so data-track-property etc. don't over-match.
      if (key.indexOf('trackProp') === 0 && key.length > 9 && key.charAt(9) >= 'A' && key.charAt(9) <= 'Z') {
        const prop = toSnake(key.charAt(9).toLowerCase() + key.slice(10));
        props = props || {};
        if (!has(props, prop)) {
          props[prop] = data[key];
        }
      }
    }
    return props;
  }

  const ACTIONABLE_ROLES = { button: 1, link: 1, menuitem: 1, menuitemcheckbox: 1, menuitemradio: 1, tab: 1, option: 1, switch: 1 };
  const ACTIONABLE_INPUTS = { submit: 1, button: 1, reset: 1, image: 1, checkbox: 1, radio: 1 };

  /**
   * True only for elements a user meaningfully clicks to trigger something:
   * native controls (links, buttons, actionable inputs, summary), ARIA widgets,
   * elements made interactive by a framework (wire:click / @click / onclick),
   * and explicit opt-ins via data-track-event. A plain click on text or empty
   * space is never recorded. A <form>'s data-track-event is for submit, not click.
   */
  function isActionable(el) {
    const tag = el.tagName;

    if (tag === 'A' || tag === 'BUTTON' || tag === 'SUMMARY') {
      return true;
    }
    if (tag === 'INPUT') {
      return has(ACTIONABLE_INPUTS, (el.getAttribute('type') || 'text').toLowerCase());
    }
    if (has(ACTIONABLE_ROLES, el.getAttribute('role') || '')) {
      return true;
    }
    if (tag !== 'FORM' && el.hasAttribute('data-track-event')) {
      return true;
    }

    return (
      el.hasAttribute('wire:click') ||
      el.hasAttribute('@click') ||
      el.hasAttribute('x-on:click') ||
      el.hasAttribute('onclick')
    );
  }

  /**
   * Single walk from the clicked node up the tree: collects the first event
   * name/value/section, every data-track-prop-*, and the nearest actionable
   * ancestor. Stops (and ignores the click) on data-track-ignore.
   */
  function inspect(node) {
    let name = null;
    let label = null;
    let value = null;
    let section = null;
    let props = null;
    let actionable = null;
    let el = node;

    while (el && el.nodeType === 1) {
      if (el.hasAttribute('data-track-ignore')) {
        return { ignored: true };
      }
      if (!actionable && isActionable(el)) {
        actionable = el;
      }

      const data = el.dataset || {};
      // data-track-event on a <form> is captured on submit, not on click.
      if (name === null && data.trackEvent && el.tagName !== 'FORM') {
        name = cap(data.trackEvent, MAX_NAME);
      }
      if (label === null && data.trackLabel) {
        label = cap(data.trackLabel, MAX_TEXT);
      }
      if (value === null) {
        value = scoreOf(data.trackValue);
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

    return { ignored: false, name: name, label: label, value: value, props: props, actionable: actionable };
  }

  function selectorFor(el) {
    if (el.id) {
      return ('#' + el.id).slice(0, MAX_SELECTOR);
    }
    let selector = el.tagName.toLowerCase();
    if (typeof el.className === 'string' && el.className.trim()) {
      selector += '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.');
    }
    return selector.slice(0, MAX_SELECTOR);
  }

  function onClick(e) {
    let node = e.target;
    if (!node || node.nodeType !== 1) {
      node = node && node.parentElement;
    }
    if (!node) {
      return;
    }

    const found = inspect(node);
    if (found.ignored || !found.actionable) {
      return;
    }

    const event = baseEvent('click');
    if (found.name) {
      event.name = found.name;
    }
    if (found.value != null) {
      event.value = found.value;
    }
    if (found.props) {
      event.props = found.props;
    }

    event.selector = selectorFor(found.actionable);
    // textContent, not innerText: it forces no synchronous layout reflow.
    const text = found.label != null ? found.label : (found.actionable.textContent || '').trim();
    event.text = text ? text.slice(0, MAX_TEXT) : null;

    queue(event);
  }

  function onSubmit(e) {
    const form = e.target;
    if (!form || form.nodeType !== 1 || !form.dataset || !form.dataset.trackEvent) {
      return;
    }

    const data = form.dataset;
    const event = baseEvent('click');
    event.name = cap(data.trackEvent, MAX_NAME);

    const score = scoreOf(data.trackValue);
    if (score !== null) {
      event.value = score;
    }

    const props = readProps(data, null);
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

  queue(baseEvent('pageview'));

  // Capture phase, so an app handler calling stopPropagation cannot swallow the
  // click; not passive, which is a no-op for a click.
  document.addEventListener('click', onClick, true);

  // On submit, so a form sent with the Enter key is tracked too.
  document.addEventListener('submit', onSubmit, true);

  setInterval(function () {
    if (document.visibilityState === 'visible') {
      queue(baseEvent('heartbeat'));
    }
  }, HEARTBEAT_MS);

  document.addEventListener('visibilitychange', onHidden);
  window.addEventListener('pagehide', flush);
})();
