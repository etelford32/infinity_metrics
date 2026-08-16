'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const trackerCode = fs.readFileSync(path.join(__dirname, '..', 'sdk', 'javascript', 'infinity_metrics.js'), 'utf8');

function assert(condition, message) {
  if (!condition) throw new Error(`FAIL: ${message}`);
}

let uuidCounter = 0;

function makeContext(storage, doNotTrack = '0', persistentStorage = storage) {
  const requests = [];
  const listeners = {};
  let now = Date.now();
  class TestDate extends Date {
    constructor(...args) { super(args.length ? args[0] : now); }
    static now() { return now; }
  }
  const document = {
    currentScript: {
      src: 'https://analytics.example.com/wp-content/plugins/infinity-metrics/assets/tracker.js',
      dataset: { source: 'elliottelford', key: 'im_test_key' }
    },
    referrer: 'https://search.example/results?private=query#fragment',
    visibilityState: 'visible',
    documentElement: { scrollHeight: 2000 },
    addEventListener() {}
  };
  const window = {
    document,
    location: { href: 'https://elliottelford.com/work/?private=yes', pathname: '/work/' },
    innerHeight: 1000,
    scrollY: 0,
    doNotTrack,
    navigator: { doNotTrack },
    crypto: { randomUUID: () => `test-random-${++uuidCounter}` },
    sessionStorage: {
      getItem: key => Object.prototype.hasOwnProperty.call(storage, key) ? storage[key] : null,
      setItem: (key, value) => { storage[key] = value; }
    },
    localStorage: {
      getItem: key => Object.prototype.hasOwnProperty.call(persistentStorage, key) ? persistentStorage[key] : null,
      setItem: (key, value) => { persistentStorage[key] = value; }
    },
    fetch(endpoint, request) {
      requests.push({ endpoint, request, payload: JSON.parse(request.body) });
      return Promise.resolve({ status: 202 });
    },
    addEventListener(name, callback) { listeners[name] = callback; },
    removeEventListener(name) { delete listeners[name]; },
    setInterval(callback) { window.intervalCallback = callback; return 1; },
    clearInterval() {},
    URL,
    Promise,
    Uint8Array,
    Math,
    Date: TestDate,
    JSON
  };
  window.window = window;
  vm.runInContext(trackerCode, vm.createContext({ window, document, navigator: window.navigator, URL, Promise, Uint8Array, Math, Date: TestDate, JSON }));
  return { window, requests, listeners, advance: milliseconds => { now += milliseconds; } };
}

async function run() {
  const storage = {};
  const first = makeContext(storage);
  assert(first.requests.length === 1, 'page load sends one automatic event');
  assert(first.requests[0].payload.event === 'page_view', 'automatic event is page_view');
  assert(first.requests[0].endpoint === 'https://analytics.example.com/wp-json/infinity_metrics/v1/event', 'endpoint derives from script origin');
  assert(first.requests[0].request.credentials === 'omit', 'request omits credentials');
  assert(first.requests[0].payload.page === '/work/', 'page contains only the path');
  assert(first.requests[0].payload.referrer === 'https://search.example/results', 'referrer query is removed');
  assert(first.requests[0].payload.visitor_id, 'payload includes an anonymous visitor ID');

  assert(await first.window.InfinityMetrics.track('cta_click', { target: 'signup' }), 'manual event succeeds');
  assert(first.requests[1].payload.properties.target === 'signup', 'manual properties are sent');

  first.advance(30000);
  first.window.intervalCallback();
  assert(first.requests.filter(item => item.payload.event === 'engaged_30s').length === 1, 'visible engagement fires once');

  first.window.scrollY = 900;
  first.listeners.scroll();
  assert(first.requests.filter(item => item.payload.event === 'scroll_50').length === 1, '50 percent scroll fires once');
  assert(first.requests.filter(item => item.payload.event === 'scroll_90').length === 1, '90 percent scroll fires once');

  const second = makeContext(storage);
  assert(second.requests.some(item => item.payload.event === 'second_page'), 'second page event fires');
  assert(second.requests[0].payload.session_id === first.requests[0].payload.session_id, 'session ID persists');

  const laterSession = makeContext({}, '0', storage);
  assert(laterSession.requests[0].payload.visitor_id === first.requests[0].payload.visitor_id, 'visitor ID persists across sessions');
  assert(laterSession.requests[0].payload.session_id !== first.requests[0].payload.session_id, 'new tab gets a new session ID');
  assert(makeContext({}, '1').requests.length === 0, 'Do Not Track disables collection');
  console.log('Tracker tests passed.');
}

run().catch(error => {
  console.error(error.message);
  process.exit(1);
});
