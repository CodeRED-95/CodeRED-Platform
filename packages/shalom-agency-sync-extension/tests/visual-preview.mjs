// Servidor de pruebas visuales aisladas. No forma parte de dist ni llama a Platform.
import http from 'node:http';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../dist/',import.meta.url));
const fixture = `const mode = new URL(location.href).searchParams.get('state');
const plan={preview_id:'00000000-0000-4000-8000-000000000001',native_count:501,matched_count:499,activate_count:1,deactivate_count:2,origin:'ORIGEN DE PRUEBA',expires_at:new Date(Date.now()+600000).toISOString(),can_apply:mode!=='blocked',blocked_reasons:mode==='blocked'?['Prueba de bloqueo: se desactivaría más del 10 % del catálogo activo. No se permiten modificaciones.']:[],changes:[{id:1,name:'AGENCIA DE PRUEBA A',location:'LIMA, LIMA, LIMA',from:'inactive',to:'active'},{id:2,name:'AGENCIA DE PRUEBA B',location:'TACNA, TACNA, TACNA',from:'active',to:'inactive'},{id:3,name:'AGENCIA DE PRUEBA C',location:'AREQUIPA, AREQUIPA, AREQUIPA',from:'active',to:'inactive'}],review:[{label:'AGENCIA NO IDENTIFICADA DE PRUEBA',reason:'No existe en Platform'}]};
const receipt={preview_id:plan.preview_id,applied_at:new Date().toISOString(),user_id:999,activate_count:1,deactivate_count:2,review_count:1,origin:plan.origin};
let state={user:{id:999,name:'Administrador de prueba visual',email:'simulacion@example.test'},snapshot:{total:501,origin_name:plan.origin,captured_at:new Date().toISOString()},auto:mode==='auto',plan:mode==='success'?null:plan,receipt:mode==='success'?receipt:null};
window.chrome={runtime:{id:'visual-test-only',sendMessage:async(message)=>({ok:true,data:message.type==='state'?state:undefined})},storage:{onChanged:{addListener:()=>{}}}};
document.addEventListener('DOMContentLoaded',()=>{const note=document.createElement('p');note.className='notice warning';note.textContent='PRUEBA VISUAL — DATOS SIMULADOS. Sin conexión a Platform; no cambia agencias reales.';document.querySelector('main').prepend(note);});`;
const types={'.html':'text/html; charset=utf-8','.css':'text/css','.js':'text/javascript','.mjs':'text/javascript','.png':'image/png','.woff2':'font/woff2'};
http.createServer(async(req,res)=>{
  const pathname=new URL(req.url,'http://127.0.0.1:4176').pathname;
  if(pathname==='/fixture.js'){res.writeHead(200,{'Content-Type':'text/javascript'});res.end(fixture);return;}
  const file=path.resolve(root,'.'+(pathname==='/'?'/dashboard.html':pathname));
  if(!file.startsWith(root)){res.writeHead(403);res.end();return;}
  try{let content=await readFile(file);if(file.endsWith('dashboard.html')) content=content.toString().replace('<script type="module" src="dashboard.js">','<script type="module" src="/fixture.js"></script><script type="module" src="dashboard.js">');res.writeHead(200,{'Content-Type':types[path.extname(file)]??'application/octet-stream','Content-Security-Policy':"default-src 'self'; connect-src 'none'; object-src 'none'"});res.end(content);}
  catch{res.writeHead(404);res.end();}
}).listen(4176,'127.0.0.1',()=>console.log('Prueba visual aislada: http://127.0.0.1:4176/?state=blocked (auto, success)'));
