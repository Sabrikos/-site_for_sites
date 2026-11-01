const test = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function setup() {
  const timers = new Map(), handlers = {}, windowHandlers = {};
  let nextId = 1, observer, frame, now = 100000;
  const classes = new Set();
  const values = {};
  const media = { matches: false, addEventListener(type, fn) { this.changed = fn; } };
  const fine = { matches: true, addEventListener(type, fn) { this.changed = fn; } };
  const image = { complete: true, naturalWidth: 1086 };
  const alien = {
    dataset: {},
    classList: { add: x => classes.add(x), remove: (...x) => x.forEach(y => classes.delete(y)) },
    style: { setProperty: (k, v) => { values[k] = v; }, removeProperty: k => { delete values[k]; } },
    querySelector: () => image,
    getBoundingClientRect: () => ({ left: 0, top: 0, width: 300, height: 400 })
  };
  const document = { hidden: false, documentElement: { addEventListener() {} }, querySelector: () => alien, addEventListener: (event, fn) => { handlers[event] = fn; } };
  const window = { matchMedia: true, addEventListener: (event, fn) => { windowHandlers[event] = fn; } };
  class IntersectionObserver { constructor(fn) { observer = fn; } observe() {} }
  window.IntersectionObserver = IntersectionObserver;
  const context = { window, document, IntersectionObserver, matchMedia: query => query.includes('reduced-motion') ? media : fine,
    getComputedStyle: () => ({ display: 'block' }),
    setTimeout: (fn, ms) => { const id = nextId++; timers.set(id, { fn, ms }); return id; },
    clearTimeout: id => timers.delete(id),
    requestAnimationFrame: fn => { frame = fn; return 1; }, cancelAnimationFrame: () => { frame = null; },
    Date: { now: () => now }, Math };
  vm.runInNewContext(readFileSync(path.join(__dirname, '..', 'contact-alien-v2.js'), 'utf8'), context);
  return { classes, values, timers, handlers, windowHandlers, media, alien,
    advance: ms => { now += ms; if(frame) { const fn=frame;frame=null;fn(); } },
    visible: yes => observer([{ isIntersecting: yes }]),
    move: (x, y) => { handlers.pointermove({ clientX: x, clientY: y, pointerType: 'mouse' }); if (frame) { const fn = frame; frame = null; fn(); } },
    runTimer: predicate => { const entry = [...timers].find(([, item]) => predicate(item.ms)); assert.ok(entry); timers.delete(entry[0]); entry[1].fn(); } };
}

test('visible character blinks, waves and reacts to a nearby pointer', () => {
  const a = setup(); a.visible(true);
  assert.equal(a.classes.has('is-ready'), true);
  a.runTimer(ms => ms >= 3800 && ms < 9000);
  assert.equal(a.classes.has('is-blinking'), true);
  a.runTimer(ms => ms === 180);
  assert.equal(a.classes.has('is-blinking'), false);
  a.runTimer(ms => ms >= 9500);
  assert.equal(a.alien.dataset.gesture, 'wave');
  a.advance(2700);
  assert.equal(a.alien.dataset.gesture, 'idle');
  a.move(250, 180);
  a.runTimer(ms => ms === 450);
  assert.equal(a.alien.dataset.gesture, 'reach');
  assert.ok(a.values['--alien-eye-x']);
});

test('reduced motion and leaving viewport cancel timers and temporary poses', () => {
  const a = setup(); a.visible(true); a.move(250, 180);
  a.media.matches = true; a.media.changed();
  assert.equal(a.timers.size, 0);
  assert.equal(a.alien.dataset.gesture, 'idle');
  assert.equal(a.values['--alien-eye-x'], undefined);
  a.media.matches = false; a.media.changed();
  assert.ok(a.timers.size > 0);
  a.visible(false);
  assert.equal(a.timers.size, 0);
  a.visible(true); a.windowHandlers.pagehide();
  assert.equal(a.timers.size, 0);
  a.windowHandlers.pageshow({ persisted: true });
  assert.ok(a.timers.size > 0);
});
