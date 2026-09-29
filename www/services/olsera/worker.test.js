const {test}=require('node:test');const assert=require('node:assert/strict');
const {cycle}=require('./worker');const {dateParts}=require('./browser');
test('only imports a claimed date; no notification sent by worker',async()=>{
 const calls=[];const api=async body=>{calls.push(body);return body.action==='claim'?{job:{date:'2026-09-28',lease:'abc'}}:{ok:true}};
 await cycle(api,async date=>({date,rows:[{}]}));assert.deepEqual(calls.map(c=>c.action),['claim','complete']);assert.equal(calls[1].lease,'abc');
});
test('download failure is retryable without complete',async()=>{
 const calls=[];await cycle(async body=>{calls.push(body);return body.action==='claim'?{job:{date:'2026-09-28',lease:'abc'}}:{ok:true}},async()=>{throw Error('secret')});
 assert.deepEqual(calls.map(c=>c.action),['claim','fail']);assert.ok(!calls[1].error.includes('secret'));
});
test('idle queue does not download',async()=>{assert.equal(await cycle(async()=>({job:null}),async()=>{throw Error('unexpected')}),false)});
test('calendar dates validated',()=>{assert.equal(dateParts('2026-09-28').shortText,'28 Sep 26');assert.throws(()=>dateParts('2026-02-30'));});
