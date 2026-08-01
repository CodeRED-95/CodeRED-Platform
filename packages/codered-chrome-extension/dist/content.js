var CodeREDShalomContent=(function(y){"use strict";function k(e){return e.status==="active"||e.status==="moved"||e.status==="temporarily_closed"}function p(e){return String(e??"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase().replace(/[^a-z0-9]+/g," ").replace(/centro\s+de\s+operaciones/g,"co").replace(/\b(c)\s+(o)\b/g,"co").replace(/\s+/g," ").trim()}function F(e){if(!e)return!1;try{const t=new URL(e);return t.protocol==="http:"||t.protocol==="https:"}catch{return!1}}function G(e){if(F(e.mapUrl))return e.mapUrl;if(e.latitude!==null&&e.longitude!==null)return`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${e.latitude},${e.longitude}`)}`;const t=[e.name,e.address,e.department,e.province,e.district].filter(Boolean).join(" ");return`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(t)}`}function W(e,t,o=20){const r=p(t),i=r.split(" ").filter(Boolean);return r===""?e.filter(k).slice(0,o).map(s=>({agency:s,score:1})):e.filter(k).map(s=>({agency:s,score:Q(s,r,i)})).filter(s=>s.score>0).sort((s,u)=>u.score-s.score||String(s.agency.name).localeCompare(u.agency.name)).slice(0,o)}function Q(e,t,o){const r=p(e.code),i=p(e.name),s=p(e.oldName),u=p(e.shortName),h=p([e.department,e.province,e.district,e.address,e.reference,e.ubigeoId,e.category,e.sendsCategory,e.receivesCategory].filter(Boolean).join(" "));return r!==""&&r===t?100:i===t||u===t?90:i.startsWith(t)||u.startsWith(t)?80:s===t||v(s,o)?70:v(i,o)||v(u,o)?60:v(h,o)?40:0}function v(e,t){return t.length>0&&t.every(o=>e.includes(o))}function I(e=document){const o=Array.from(e.querySelectorAll('select[id*="osProDestino"]')).filter(i=>i instanceof HTMLSelectElement).filter(X);if(o.length===0)return null;if(o.length===1)return o[0];const r=o.filter(i=>!i.disabled&&_(i));return r.length===1?r[0]:{reason:"multiple-active-selects",count:r.length||o.length}}function K(e,t,o){const r=o==="AUTO"?"TERRESTRE":o,i=r==="TERRESTRE"?t.terrestrialText:t.airText;if(!i)return{success:!1,reason:"missing-channel-text",message:`La agencia no tiene texto Chosen para ${r}.`,channel:r};const s=I(e);if(!s)return{success:!1,reason:"no-destination-select",message:"No hay un campo de destino disponible en la pantalla actual.",channel:r};if(!(s instanceof HTMLSelectElement))return{success:!1,reason:"multiple-active-selects",message:"Hay multiples campos de destino activos; no se selecciono ninguno.",channel:r};const u=J(s,i,t);return Y(u)?u.reason==="option-not-found"?{success:!1,reason:"option-not-found",message:`La agencia esta registrada en CodeRED Platform, pero no esta disponible en el selector actual de Shalom Control (${r}).`,channel:r}:{success:!1,reason:"multiple-matching-options",message:`Hay multiples opciones coincidentes para ${r}; no se cambio el selector.`,channel:r}:(s.value=u.value,u.selected=!0,s.dispatchEvent(new Event("input",{bubbles:!0})),s.dispatchEvent(new Event("change",{bubbles:!0})),ee(s),Z(s),s.value!==u.value?{success:!1,reason:"option-not-found",message:"Shalom Control no confirmo el cambio del selector.",channel:r}:{success:!0,value:u.value,channel:r})}function Y(e){return"reason"in e}function J(e,t,o){const r=Array.from(e.options),i=r.filter(f=>f.text.trim()===t.trim());if(i.length===1)return i[0];if(i.length>1)return{reason:"multiple-matching-options"};const s=p(t),u=r.filter(f=>p(f.text)===s);if(u.length===1)return u[0];if(u.length>1)return{reason:"multiple-matching-options"};const h=p(o.externalId);if(h){const f=r.filter(R=>p(R.text).includes(h));if(f.length===1)return f[0];if(f.length>1)return{reason:"multiple-matching-options"}}return{reason:"option-not-found"}}function X(e){return!e.disabled&&!e.hidden&&e.getAttribute("aria-hidden")!=="true"&&_(e)}function _(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const o=t.getAttribute("style")?.replace(/\s+/g,"").toLowerCase()??"";if(o.includes("display:none")||o.includes("visibility:hidden"))return!1}return!0}function Z(e){const t=e.ownerDocument.defaultView?.jQuery;typeof t=="function"&&t(e).trigger("chosen:updated")}function ee(e){const t=`${e.id}_chosen`,r=e.ownerDocument.getElementById(t)?.querySelector(".chosen-single span, .chosen-container span"),i=e.selectedOptions.item(0);r&&i&&(r.textContent=i.text)}const z='button, a, [role="tab"], [onclick], .mdl-tabs__tab',te=["active","is-active","selected"];function ne(e=document){const o=Array.from(e.querySelectorAll(z)).filter(i=>i instanceof HTMLElement).map(i=>({element:i,channel:B(i),active:oe(i)})).filter(i=>i.channel!==null);return o.find(i=>i.active)?.channel??o[0]?.channel??"TERRESTRE"}function M(e,t){const o=Array.from(e.querySelectorAll(z)).filter(r=>r instanceof HTMLElement);for(const r of o)!B(r)||r.dataset.coderedChannelBound==="true"||(r.dataset.coderedChannelBound="true",r.addEventListener("click",()=>window.setTimeout(()=>t(ne(e)),0)))}function B(e){const t=p([e.getAttribute("title"),e.getAttribute("aria-label"),e.getAttribute("onclick"),e.textContent].filter(Boolean).join(" "));return t.includes("terrestre")?"TERRESTRE":t.includes("aereo")?"AEREO":null}function oe(e){return e.getAttribute("aria-selected")==="true"||te.some(t=>e.classList.contains(t))}const re=["shalomcontrol.com","shalom.pe"];function ie(e,t){const o=w(e);if(!o||!re.some(s=>D(o,s)))return!1;const i=[].map(w).filter(Boolean);return i.length===0?!0:i.some(s=>D(o,s))}function ae(e,t){return D(e,t)}function D(e,t){const o=w(e),r=w(t);return o===r||o.endsWith(`.${r}`)}function w(e){return String(e??"").trim().toLowerCase().replace(/\.$/,"")}const d="mi-buscador-contenedor",E="codered-search-input",C="codered-shalom-search-results",x="codered-shalom-search-status",se=["shalom.pe","shalomcontrol.com"],le=new Set(["agencies","agencyCache","catalog","catalogVersion","syncMetadata"]);function O(e={}){let t=[],o="TERRESTRE",r=null,i=null,s=!1;async function u(){if(console.log("[CodeRED Shalom] Content script iniciado"),console.log(`[CodeRED Shalom] URL actual: ${window.location.href}`),!ce())return;await h(o);const n=A();console.log("[CodeRED Shalom] Resultado de inyección",{reason:n.reason}),j(),ge()}async function h(n=o){o=n;try{t=await be(),console.log(`[CodeRED Shalom] Catálogo local cargado: ${t.length} agencias`),he()}catch(a){console.error("[CodeRED Shalom] Falló la carga del catálogo",$(a)),t=[]}}function f(){const n=window.location.hostname.toLowerCase(),a=(e.allowedDomains??se).map(c=>c.trim().toLowerCase()).filter(Boolean);return ie(n)?a.length===0||a.some(c=>ae(n,c))?(console.log("[CodeRED Shalom] Dominio permitido"),!0):(console.warn("[CodeRED Shalom] Inyección omitida",{reason:"domain-not-allowed",hostname:n,allowedDomains:a}),!1):(console.warn("[CodeRED Shalom] Inyección omitida",{reason:"unsupported-page",hostname:n}),!1)}function R(){console.log("[CodeRED Shalom] Buscando punto de inyección");const n=[".mdl-layout__header-row","header .mdl-layout__header-row",".mdl-layout__header","header",'[role="banner"]',".navbar",".topbar",".header"];for(const a of n){const c=Array.from(document.querySelectorAll(a)).filter(m=>m instanceof HTMLElement).find(m=>de(m)&&!m.closest(`#${d}`));if(c)return console.log(`[CodeRED Shalom] Target encontrado con selector: ${a}`),{element:c,selector:a}}return console.log("[CodeRED Shalom] Target todavía no disponible"),null}function N(){const n=document.createElement("div");return n.id=d,n.className="codered-shalom-search",n.innerHTML=`
      <style>
        #${d}.codered-shalom-search {
          align-items: center !important;
          box-sizing: border-box !important;
          display: flex !important;
          flex-shrink: 0 !important;
          gap: 8px !important;
          margin: 0 16px !important;
          min-width: 280px !important;
          opacity: 1 !important;
          position: relative !important;
          visibility: visible !important;
          z-index: 1200 !important;
        }
        #${d} #${E} {
          background: #ffffff !important;
          border: 1px solid rgba(15, 23, 42, 0.28) !important;
          border-radius: 6px !important;
          box-sizing: border-box !important;
          color: #111827 !important;
          display: block !important;
          font-size: 13px !important;
          height: 34px !important;
          min-width: 220px !important;
          outline: none !important;
          padding: 7px 10px !important;
          visibility: visible !important;
          width: 100% !important;
        }
        #${d} .${C} {
          background: #ffffff !important;
          border: 1px solid rgba(15, 23, 42, 0.18) !important;
          border-radius: 6px !important;
          box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18) !important;
          color: #111827 !important;
          display: none;
          left: 0;
          list-style: none !important;
          margin: 6px 0 0 !important;
          max-height: 320px !important;
          overflow-y: auto !important;
          padding: 4px !important;
          position: absolute !important;
          right: 0;
          top: 100%;
          z-index: 1201 !important;
        }
        #${d} .${C} li { margin: 0 !important; padding: 0 !important; }
        #${d} .codered-shalom-result,
        #${d} .codered-shalom-empty {
          background: transparent !important;
          border: 0 !important;
          border-radius: 4px !important;
          box-sizing: border-box !important;
          color: #111827 !important;
          display: block !important;
          font-size: 13px !important;
          padding: 8px 10px !important;
          text-align: left !important;
          width: 100% !important;
        }
        #${d} .codered-shalom-result { cursor: pointer !important; }
        #${d} .codered-shalom-result:hover { background: #f3f4f6 !important; }
        #${d} .${x} {
          color: #4b5563 !important;
          display: inline-block !important;
          font-size: 12px !important;
          max-width: 140px !important;
          overflow: hidden !important;
          text-overflow: ellipsis !important;
          white-space: nowrap !important;
        }
        @media (prefers-color-scheme: dark) {
          #${d} #${E},
          #${d} .${C} { background: #111827 !important; color: #f9fafb !important; border-color: rgba(249, 250, 251, 0.28) !important; }
          #${d} .codered-shalom-result,
          #${d} .codered-shalom-empty { color: #f9fafb !important; }
          #${d} .codered-shalom-result:hover { background: #1f2937 !important; }
          #${d} .${x} { color: #d1d5db !important; }
        }
      </style>
      <input id="${E}" type="search" placeholder="Buscar agencia Shalom..." autocomplete="off" />
      <span class="${x}" aria-live="polite"></span>
      <ul class="${C}" role="listbox"></ul>
    `,n}function L(n){if(n.dataset.coderedSearchBound==="true")return;n.dataset.coderedSearchBound="true";const a=n.querySelector(`#${E}`),l=n.querySelector(`.${C}`),c=n.querySelector(`.${x}`);if(!a||!l||!c)return;let m=null;a.addEventListener("input",()=>{m&&window.clearTimeout(m),m=window.setTimeout(()=>q(a,l,c),150)}),a.addEventListener("focus",()=>{q(a,l,c),l.style.display="block"});const g=new window.AbortController;n.dataset.coderedAbortController="true",document.addEventListener("click",S=>{n.contains(S.target)||(l.style.display="none")},{signal:g.signal})}function A(){if(!document.body)return{success:!1,reason:"body-not-ready"};if(!f())return{success:!1,reason:"unsupported-page"};const n=document.getElementById(d);if(n?.isConnected)return L(n),M(document,U),console.log("[CodeRED Shalom] Buscador ya estaba inyectado"),{success:!0,reason:"already-mounted",element:n};const a=R();if(!a)return{success:!1,reason:"target-not-found"};const l=N(),c=a.element.querySelector(".mdl-layout-spacer");return c?c.before(l):a.element.appendChild(l),L(l),M(document,U),console.log("[CodeRED Shalom] Buscador inyectado"),{success:!0,reason:"mounted",element:l}}function j(){if(r)return;const n=document.documentElement??document.body;n&&(r=new MutationObserver(()=>{i&&window.clearTimeout(i),i=window.setTimeout(()=>{i=null,document.getElementById(d)?.isConnected||console.log("[CodeRED Shalom] El header cambió; reinyectando"),A()},100)}),r.observe(n,{childList:!0,subtree:!0}))}function me(){i&&window.clearTimeout(i),i=null,r?.disconnect(),r=null}function pe(){return h(o).then(()=>A())}function U(n){o=n,console.log(`[CodeRED Shalom] Segmento activo: ${o}`)}function q(n,a,l){a.innerHTML="",a.style.display="block";const c=n.value.trim();if(t.length===0){P(a,"No hay agencias sincronizadas. Abre la configuración y pulsa Sincronizar ahora");return}if(c.length<2){l.textContent="";return}const m=I(document);l.textContent=m instanceof HTMLSelectElement?`Canal: ${o}`:"No hay selector de destino.";const g=W(t,c,8);if(g.length===0){P(a,"No se encontraron agencias.");return}for(const{agency:S}of g)a.appendChild(fe(S,n,a,l))}function fe(n,a,l,c){const m=document.createElement("button");m.className="codered-shalom-result",m.type="button";const g=[n.name,n.code].filter(Boolean).join(" - "),S=[n.department,n.province,n.district].filter(Boolean).join(" / ");m.innerHTML=`<strong>${H(g)}</strong><br><small>${H(S)}</small>`,m.addEventListener("click",()=>{const T=K(document,n,o);T.success?(a.value="",l.innerHTML="",l.style.display="none",c.textContent="Agencia seleccionada",window.setTimeout(()=>c.textContent="",2e3)):c.textContent=T.message});const b=document.createElement("a");b.href=G(n),b.target="_blank",b.rel="noopener noreferrer",b.textContent="Ver mapa",b.addEventListener("click",T=>T.stopPropagation());const V=document.createElement("li");return V.append(m,b),V}function P(n,a){const l=document.createElement("li");l.className="codered-shalom-empty",l.textContent=a,n.appendChild(l)}function he(){const n=document.getElementById(E);n?.value&&n.dispatchEvent(new Event("input",{bubbles:!0}))}function ge(){s||typeof chrome>"u"||typeof chrome.storage?.onChanged?.addListener!="function"||(s=!0,chrome.storage.onChanged.addListener((n,a)=>{a==="local"&&Object.keys(n).some(l=>le.has(l))&&h(o)}))}async function be(){if(e.requestCatalog)return e.requestCatalog();if(typeof chrome>"u"||typeof chrome.runtime?.sendMessage!="function")return console.error("[CodeRED Shalom] chrome.runtime.sendMessage no está disponible."),[];const n=await chrome.runtime.sendMessage({type:"CATALOG_GET"});return Array.isArray(n?.agencies)?n.agencies:[]}return{cargarDatos:h,createSearchContainer:N,bindSearchEvents:L,findInjectionTarget:R,injectSearchIfPossible:A,initializeContentScript:u,isSupportedShalomPage:f,mount:pe,startInjectionObserver:j,stopInjectionObserver:me}}function ce(){return typeof document>"u"||typeof window>"u"||!document.body?!1:typeof chrome>"u"?(console.error("[CodeRED Shalom] chrome no está disponible para content.js"),!1):typeof chrome.storage>"u"?(console.error("[CodeRED Shalom] chrome.storage no está disponible para content.js"),!1):!0}function de(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const o=window.getComputedStyle(t);if(o.display==="none"||o.visibility==="hidden"||o.opacity==="0")return!1}return!0}function $(e){return e instanceof Error?{name:e.name,message:e.message,stack:e.stack}:{message:String(e)}}function H(e){return e.replace(/[&<>"]/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"})[t]??t)}function ue(){if(typeof document>"u")return;const e=O(),t=()=>{e.initializeContentScript().catch(o=>{console.error("[CodeRED Shalom] Error de inicialización:",$(o))})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",t,{once:!0}):t()}return ue(),y.createShalomContentController=O,y.serializeSafeError=$,Object.defineProperty(y,Symbol.toStringTag,{value:"Module"}),y})({});
