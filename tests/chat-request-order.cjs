// Exercise the actual widget request functions with delayed HTTP responses.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../web-chat.js'), 'utf8');
const functions = source.slice(source.indexOf('  let requestQueue ='), source.indexOf('  async function openChat'));
const calls = [];
const context = vm.createContext({
  URLSearchParams,
  fetch: (_url, options) => new Promise(resolve => calls.push({options, resolve})),
  csrf: 'test', lastId: 0, textarea: {}, state: {},
  addMessage: message => { context.lastId = message.id; }, updateStatus() {},
});
vm.runInContext(functions + '\n globalThis.send = request;', context);
const tick = () => new Promise(resolve => setImmediate(resolve));
const response = (status, id) => ({ok: true, json: async () => ({status, messages: [{id}]})});
(async () => {
  const poll = context.send('poll');
  const handoff = context.send('message', {choice: 'human'});
  await tick();
  assert.equal(calls.length, 1, 'handoff waits for the older poll');
  calls[0].resolve(response('bot', 1));
  await poll; await tick();
  assert.equal(calls.length, 2);
  assert.equal(calls[1].options.body.get('after_id'), '1');
  calls[1].resolve(response('waiting_human', 2));
  await handoff;
  assert.equal(context.state.status, 'waiting_human');
  const failed = context.send('poll');
  const caught = assert.rejects(failed, /offline/);
  const next = context.send('poll');
  await tick();
  calls[2].resolve({ok: false, json: async () => ({error: 'offline'})});
  await caught; await tick();
  calls[3].resolve(response('human', 3));
  await next;
  assert.equal(context.state.status, 'human', 'failed request does not block the queue');
  console.log('PASS: delayed poll cannot overwrite handoff; queue recovers after errors');
})().catch(error => { console.error(error); process.exitCode = 1; });
