import {test} from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const code = await readFile(new URL('../src/background.js', import.meta.url), 'utf8');
const dashboard = {id:'sync-id',url:'chrome-extension://sync-id/dashboard.html',tab:{id:5}};
const source = {id:'sync-id',url:'https://sysnewos.shalomcontrol.com/service-order/items',tab:{id:7}};
const snapshot = {source_url:source.url,channel:'terrestrial',origin_name:'ORIGEN',filter:'',complete:true,end_observed:true,stable_reads:2,total:100,
  entries:Array.from({length:100},(_,index)=>({index,label:`LIMA, LIMA, LIMA - AG. AGENCIA ${index}`}))};

async function harness(route) {
  let listener; let stored = {}; const calls = [];
  const chrome = {runtime:{id:'sync-id',getURL:file=>`chrome-extension://sync-id/${file}`,onMessage:{addListener:fn=>{listener=fn;}}},
    storage:{session:{get:async()=>structuredClone(stored),set:async value=>{stored=structuredClone(value);},setAccessLevel:async()=>{}}},
    action:{onClicked:{addListener:()=>{}}}, tabs:{query:async()=>[{id:7}],create:()=>{},sendMessage:async()=>({ok:true})}};
  vm.runInNewContext(code, {chrome,Date,AbortSignal,Promise,Error,Array,Object,JSON,fetch:async(url,options)=>{
    const path = url.replace('https://platform.codered.lat/api/v1',''); calls.push({path,...options});
    const result = await route(path, options);
    return {ok:result.status < 400,status:result.status,json:async()=>result.body};
  }});
  const send = (message,sender=dashboard)=>new Promise(resolve=>listener(message,sender,resolve));
  return {send,calls,stored:()=>stored.state};
}
const auth = () => ({access_token:'ACCESS_SECRET',refresh_token:'REFRESH_SECRET',expires_at:new Date(Date.now()+3600000).toISOString()});
const login = {type:'login',email:'admin@example.test',password:'TEST_PASSWORD'};
const standard = (path) => ({status:200,body:{data:path==='/auth/login'||path==='/auth/refresh'?auth():path.endsWith('/status')?{user:{id:1,name:'Admin'}}:path.endsWith('/preview')?{preview_id:'PREVIEW',can_apply:true,changes:[{id:1,to:'active'}]}:{preview_id:'PREVIEW',activate_count:1,deactivate_count:0}}});

test('login usa Platform y no expone tokens ni guarda contraseña', async () => {
  const h=await harness(standard);
  assert.equal((await h.send(login)).ok,true);
  const publicState=(await h.send({type:'state'})).data;
  assert.equal(publicState.user.id,1);
  assert.equal(publicState.auth,undefined);
  assert(!JSON.stringify(h.stored()).includes('TEST_PASSWORD'));
  const body=JSON.parse(h.calls[0].body);
  assert.equal(body.application,'platform');
  assert.equal(h.calls[0].credentials,'omit');
  assert.equal(h.calls[0].redirect,'error');
});
test('bloquea páginas y emisores no autorizados', async () => {
  const h=await harness(standard);
  assert.equal((await h.send(login,source)).ok,false);
  assert.equal((await h.send({type:'state'},{id:'otro'})).ok,false);
  assert.equal((await h.send({type:'snapshot',snapshot},dashboard)).ok,false);
  assert.equal(h.calls.length,0);
});
test('captura sin login nunca aplica; modo automático compara y aplica con sesión', async () => {
  const h=await harness(standard);
  await h.send({type:'snapshot',snapshot},source);
  assert.equal(h.calls.length,0);
  await h.send(login);
  await h.send({type:'auto',enabled:true});
  assert.equal(h.stored().auto,true);
  assert.equal((await h.send({type:'snapshot',snapshot},source)).ok,true);
  assert(h.calls.some(c=>c.path.endsWith('/apply')));
  assert.equal(h.stored().receipt.activate_count,1);
  assert.equal(h.stored().plan,null);
});
test('comparación bloqueada nunca aplica automáticamente', async () => {
  const h=await harness(path=>path.endsWith('/preview')?{status:200,body:{data:{can_apply:false,changes:[]}}}:standard(path));
  await h.send(login); await h.send({type:'auto',enabled:true});
  await h.send({type:'snapshot',snapshot},source);
  assert(!h.calls.some(c=>c.path.endsWith('/apply')));
});
test('cierra sesión en servidor, borra tokens y apaga automatización', async () => {
  const h=await harness(standard); await h.send(login); await h.send({type:'auto',enabled:true});
  assert.equal((await h.send({type:'logout'})).ok,true);
  assert(h.calls.some(c=>c.path==='/auth/logout'));
  assert.equal(h.stored().auth,undefined);
  assert.equal(h.stored().auto,false);
});
test('una cuenta sin acceso administrativo se revoca y no queda autenticada', async () => {
  const h=await harness(path=>path.endsWith('/status')?{status:403,body:{message:'Sin permisos'}}:standard(path));
  assert.equal((await h.send(login)).ok,false);
  assert.equal(h.stored().auth,undefined);
  assert(h.calls.some(c=>c.path==='/auth/logout'));
});
test('renueva access token una sola vez después de 401', async () => {
  let statuses=0;
  const h=await harness(path=>path.endsWith('/status')&&++statuses===2?{status:401,body:{message:'Caducó'}}:standard(path));
  await h.send(login);
  assert.equal((await h.send({type:'validate'})).ok,true);
  assert.equal(h.calls.filter(c=>c.path==='/auth/refresh').length,1);
});
test('filtradas, aéreas y listas cortas se rechazan antes de llamar al servidor', async () => {
  const h=await harness(standard);
  for(const change of [{filter:'LIMA'},{channel:'aerial'},{entries:[]},{total:3}]) assert.equal((await h.send({type:'snapshot',snapshot:{...snapshot,...change}},source)).ok,false);
  assert.equal(h.calls.length,0);
});
test('apagar automatización no requiere conexión y cerrar sesión sin red la apaga', async () => {
  let offline = false;
  const h=await harness(path=>{if(offline) throw new Error('offline'); return standard(path);});
  await h.send(login); await h.send({type:'auto',enabled:true}); offline=true;
  assert.equal((await h.send({type:'auto',enabled:false})).ok,true);
  assert.equal(h.stored().auto,false);
  assert.equal((await h.send({type:'logout'})).ok,false);
  assert.equal(h.stored().auto,false);
  assert(h.stored().auth);
});
test('aplicación manual exige el mismo plan confirmado y permite consultar resultado tras timeout', async () => {
  let uncertain = false;
  const h=await harness(path=>{
    if(path.endsWith('/apply')&&uncertain) throw new Error('timeout');
    if(path.endsWith('/result')) return {status:200,body:{data:{receipt:{preview_id:'PREVIEW',activate_count:1}}}};
    return standard(path);
  });
  await h.send(login); await h.send({type:'snapshot',snapshot},source); await h.send({type:'compare'});
  assert.equal((await h.send({type:'apply',preview_id:'OTRO'})).ok,false);
  assert(!h.calls.some(c=>c.path.endsWith('/apply')));
  uncertain=true;
  assert.equal((await h.send({type:'apply',preview_id:'PREVIEW'})).ok,false);
  assert.equal(h.stored().pending,'PREVIEW');
  assert.equal(h.stored().auto,false);
  assert.equal((await h.send({type:'compare'})).ok,false);
  assert.equal((await h.send({type:'result'})).ok,true);
  assert.equal(h.stored().pending,null);
  assert.equal(h.calls.filter(c=>c.path.endsWith('/apply')).length,1);
});
