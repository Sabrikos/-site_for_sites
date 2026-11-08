const test = require('node:test');
const assert = require('node:assert/strict');
const { create } = require('../assets/js/service-cart.js');
function fakeStorage(value) { let data = value; return { getItem() { return data; }, setItem(_key, value) { data = value; }, value() { return data; } }; }
const catalog = [{tariff_id:'1',service:'Лендинг',tariff:'Старт',price:15000},{tariff_id:'2',service:'Лендинг',tariff:'Бизнес',price:22000}];
test('legacy cart uses current catalog, removes stale entries and duplicate ids', () => {
 const storage = fakeStorage(JSON.stringify([{id:1,price:1},{tariff_id:1},{id:99},{id:2}]));
 const cart = create(storage,catalog); const items = cart.load();
 assert.deepEqual(items.map(x => x.price), [15000,22000]);
 assert.deepEqual(JSON.parse(storage.value()), ['1','2']);
 assert.equal(cart.total(items),37000);
 assert.equal(cart.save([items[1]])[0].tariff_id,'2');
 assert.deepEqual(cart.save([]),[]);
});
