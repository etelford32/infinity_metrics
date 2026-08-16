(function (window, document) {
  'use strict';

  var STORAGE_SESSION = 'infinity_metrics_session_id';
  var STORAGE_PAGES = 'infinity_metrics_page_count';
  var STORAGE_VISITOR = 'infinity_metrics_visitor_id';
  var script = document.currentScript;
  var config = {
    source: script && script.dataset ? script.dataset.source || '' : '',
    key: script && script.dataset ? script.dataset.key || '' : '',
    endpoint: script && script.dataset ? script.dataset.endpoint || '' : ''
  };
  var sentAutomaticEvents = {};
  var engagedMilliseconds = 0;
  var visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
  var engagedTimer = null;
  var visitorMemoryId = null;

  if (!config.endpoint && script && script.src) {
    var scriptUrl = new URL(script.src);
    var contentMarker = scriptUrl.pathname.indexOf('/wp-content/');
    var sitePath = contentMarker === -1 ? '' : scriptUrl.pathname.slice(0, contentMarker);
    config.endpoint = scriptUrl.origin + sitePath + '/wp-json/infinity_metrics/v1/event';
  }

  function storageGet(key) {
    try {
      return window.sessionStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.sessionStorage.setItem(key, value);
    } catch (error) {
      // Tracking still works when storage is unavailable; the ID lasts one page.
    }
  }

  function localStorageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function localStorageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (error) {
      // Tracking falls back to the current session when local storage is unavailable.
    }
  }

  function randomId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      var bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      return Array.prototype.map.call(bytes, function (byte) {
        return byte.toString(16).padStart(2, '0');
      }).join('');
    }

    return String(Date.now()) + '-' + Math.random().toString(16).slice(2);
  }

  function sessionId() {
    var id = storageGet(STORAGE_SESSION);
    if (!id) {
      id = randomId();
      storageSet(STORAGE_SESSION, id);
    }
    return id;
  }

  function visitorId() {
    var id = localStorageGet(STORAGE_VISITOR) || visitorMemoryId;
    if (!id) {
      id = randomId();
      visitorMemoryId = id;
      localStorageSet(STORAGE_VISITOR, id);
    }
    return id;
  }

  function locationWithoutQuery(value) {
    if (!value) {
      return '';
    }
    try {
      var parsed = new URL(value, window.location.href);
      return parsed.origin + parsed.pathname;
    } catch (error) {
      return '';
    }
  }

  function disabled() {
    if (window.infinityMetricsDisabled === true) {
      return true;
    }
    return navigator.doNotTrack === '1' || window.doNotTrack === '1';
  }

  function validEventName(eventName) {
    return typeof eventName === 'string' && /^[a-z][a-z0-9_.:-]{0,189}$/.test(eventName);
  }

  function track(eventName, properties) {
    if (disabled() || !config.endpoint || !config.source || !config.key || !validEventName(eventName)) {
      return Promise.resolve(false);
    }

    var payload = {
      source: config.source,
      key: config.key,
      event: eventName,
      visitor_id: visitorId(),
      session_id: sessionId(),
      page: window.location.pathname || '/',
      referrer: locationWithoutQuery(document.referrer),
      timestamp: new Date().toISOString(),
      properties: properties && typeof properties === 'object' && !Array.isArray(properties) ? properties : {}
    };

    return window.fetch(config.endpoint, {
      method: 'POST',
      mode: 'cors',
      credentials: 'omit',
      keepalive: true,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.status === 202;
    }).catch(function () {
      return false;
    });
  }

  function automatic(eventName, properties) {
    if (sentAutomaticEvents[eventName]) {
      return;
    }
    sentAutomaticEvents[eventName] = true;
    track(eventName, properties);
  }

  function updateEngagement() {
    if (visibleSince !== null) {
      engagedMilliseconds += Date.now() - visibleSince;
      visibleSince = Date.now();
    }

    if (engagedMilliseconds >= 30000) {
      automatic('engaged_30s');
      window.clearInterval(engagedTimer);
      engagedTimer = null;
    }
  }

  function visibilityChanged() {
    if (document.visibilityState === 'visible') {
      visibleSince = Date.now();
    } else {
      updateEngagement();
      visibleSince = null;
    }
  }

  function scrollChanged() {
    var available = Math.max(document.documentElement.scrollHeight - window.innerHeight, 0);
    if (!available) {
      return;
    }
    var percent = Math.round((window.scrollY / available) * 100);
    if (percent >= 50) {
      automatic('scroll_50');
    }
    if (percent >= 90) {
      automatic('scroll_90');
      window.removeEventListener('scroll', scrollChanged);
    }
  }

  function initializeAutomaticTracking() {
    if (disabled() || !config.endpoint || !config.source || !config.key) {
      return;
    }

    automatic('page_view');

    var pageCount = parseInt(storageGet(STORAGE_PAGES) || '0', 10) + 1;
    storageSet(STORAGE_PAGES, String(pageCount));
    if (pageCount === 2) {
      automatic('second_page');
    }

    document.addEventListener('visibilitychange', visibilityChanged);
    window.addEventListener('scroll', scrollChanged, { passive: true });
    engagedTimer = window.setInterval(updateEngagement, 1000);
    scrollChanged();
  }

  var api = window.InfinityMetrics || {};
  api.track = track;
  api.configure = function (options) {
    options = options || {};
    config.source = options.source || config.source;
    config.key = options.key || config.key;
    config.endpoint = options.endpoint || config.endpoint;
  };
  window.InfinityMetrics = api;

  initializeAutomaticTracking();
}(window, document));
