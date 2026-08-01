import{s as W}from"./agency-search.js";import{n as E,b as K}from"./format.js";function O(e=document){const o=Array.from(e.querySelectorAll('select[id*="osProDestino"]')).filter(i=>i instanceof HTMLSelectElement).filter(X);if(o.length===0)return null;if(o.length===1)return o[0];const r=o.filter(i=>!i.disabled&&z(i));return r.length===1?r[0]:{reason:"multiple-active-selects",count:r.length||o.length}}function Q(e,t,o){const r=o==="AUTO"?"TERRESTRE":o,i=r==="TERRESTRE"?t.terrestrialText:t.airText;if(!i)return{success:!1,reason:"missing-channel-text",message:`La agencia no tiene texto Chosen para ${r}.`,channel:r};const d=O(e);if(!d)return{success:!1,reason:"no-destination-select",message:"No hay un campo de destino disponible en la pantalla actual.",channel:r};if(!(d instanceof HTMLSelectElement))return{success:!1,reason:"multiple-active-selects",message:"Hay multiples campos de destino activos; no se selecciono ninguno.",channel:r};const m=J(d,i,t);return Y(m)?m.reason==="option-not-found"?{success:!1,reason:"option-not-found",message:`La agencia esta registrada en CodeRED Platform, pero no esta disponible en el selector actual de Shalom Control (${r}).`,channel:r}:{success:!1,reason:"multiple-matching-options",message:`Hay multiples opciones coincidentes para ${r}; no se cambio el selector.`,channel:r}:(d.value=m.value,m.selected=!0,d.dispatchEvent(new Event("input",{bubbles:!0})),d.dispatchEvent(new Event("change",{bubbles:!0})),ee(d),Z(d),d.value!==m.value?{success:!1,reason:"option-not-found",message:"Shalom Control no confirmo el cambio del selector.",channel:r}:{success:!0,value:m.value,channel:r})}function Y(e){return"reason"in e}function J(e,t,o){const r=Array.from(e.options),i=r.filter(p=>p.text.trim()===t.trim());if(i.length===1)return i[0];if(i.length>1)return{reason:"multiple-matching-options"};const d=E(t),m=r.filter(p=>E(p.text)===d);if(m.length===1)return m[0];if(m.length>1)return{reason:"multiple-matching-options"};const f=E(o.externalId);if(f){const p=r.filter(v=>E(v.text).includes(f));if(p.length===1)return p[0];if(p.length>1)return{reason:"multiple-matching-options"}}return{reason:"option-not-found"}}function X(e){return!e.disabled&&!e.hidden&&e.getAttribute("aria-hidden")!=="true"&&z(e)}function z(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const o=t.getAttribute("style")?.replace(/\s+/g,"").toLowerCase()??"";if(o.includes("display:none")||o.includes("visibility:hidden"))return!1}return!0}function Z(e){const t=e.ownerDocument.defaultView?.jQuery;typeof t=="function"&&t(e).trigger("chosen:updated")}function ee(e){const t=`${e.id}_chosen`,r=e.ownerDocument.getElementById(t)?.querySelector(".chosen-single span, .chosen-container span"),i=e.selectedOptions.item(0);r&&i&&(r.textContent=i.text)}const B='button, a, [role="tab"], [onclick], .mdl-tabs__tab',te=["active","is-active","selected"];function ne(e=document){const o=Array.from(e.querySelectorAll(B)).filter(i=>i instanceof HTMLElement).map(i=>({element:i,channel:H(i),active:oe(i)})).filter(i=>i.channel!==null);return o.find(i=>i.active)?.channel??o[0]?.channel??"TERRESTRE"}function I(e,t){const o=Array.from(e.querySelectorAll(B)).filter(r=>r instanceof HTMLElement);for(const r of o)!H(r)||r.dataset.coderedChannelBound==="true"||(r.dataset.coderedChannelBound="true",r.addEventListener("click",()=>window.setTimeout(()=>t(ne(e)),0)))}function H(e){const t=E([e.getAttribute("title"),e.getAttribute("aria-label"),e.getAttribute("onclick"),e.textContent].filter(Boolean).join(" "));return t.includes("terrestre")?"TERRESTRE":t.includes("aereo")?"AEREO":null}function oe(e){return e.getAttribute("aria-selected")==="true"||te.some(t=>e.classList.contains(t))}const re=["shalomcontrol.com","shalom.pe"];function ie(e,t){const o=A(e);if(!o||!re.some(d=>L(o,d)))return!1;const i=[].map(A).filter(Boolean);return i.length===0?!0:i.some(d=>L(o,d))}function ae(e,t){return L(e,t)}function L(e,t){const o=A(e),r=A(t);return o===r||o.endsWith(`.${r}`)}function A(e){return String(e??"").trim().toLowerCase().replace(/\.$/,"")}const c="mi-buscador-contenedor",y="codered-search-input",S="codered-shalom-search-results",C="codered-shalom-search-status",se=["shalom.pe","shalomcontrol.com"],le=new Set(["agencies","agencyCache","catalog","catalogVersion","syncMetadata"]);function ce(e={}){let t=[],o="TERRESTRE",r=null,i=null,d=!1;async function m(){console.log("[Shalom Pro] Content script iniciado"),console.log(`[Shalom Pro] URL actual: ${window.location.href}`),de()&&(await f(o),w(),R(),F())}async function f(n=o){o=n;try{t=await G(),console.log(`[Shalom Pro] Catálogo local cargado: ${t.length} agencias`),V()}catch(a){console.error("[Shalom Pro] Falló la carga del catálogo",q(a)),t=[]}}function p(){const n=window.location.hostname.toLowerCase(),a=(e.allowedDomains??se).map(l=>l.trim().toLowerCase()).filter(Boolean);return ie(n)?a.length===0||a.some(l=>ae(n,l))?(console.log("[Shalom Pro] Dominio permitido"),!0):(console.warn("[Shalom Pro] Inyección omitida",{reason:"domain-not-allowed",hostname:n,allowedDomains:a}),!1):(console.warn("[Shalom Pro] Inyección omitida",{reason:"unsupported-page",hostname:n}),!1)}function v(){console.log("[Shalom Pro] Buscando punto de inyección");const n=[".mdl-layout__header-row","header .mdl-layout__header-row",".mdl-layout__header","header",'[role="banner"]',".navbar",".topbar",".header"];for(const a of n){const l=Array.from(document.querySelectorAll(a)).filter(u=>u instanceof HTMLElement).find(u=>ue(u)&&!u.closest(`#${c}`));if(l)return console.log(`[Shalom Pro] Target encontrado con selector: ${a}`),{element:l,selector:a}}return console.log("[Shalom Pro] Target no encontrado"),null}function $(){const n=document.createElement("div");return n.id=c,n.className="codered-shalom-search",n.innerHTML=`
      <style>
        #${c}.codered-shalom-search {
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
        #${c} #${y} {
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
        #${c} .${S} {
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
        #${c} .${S} li { margin: 0 !important; padding: 0 !important; }
        #${c} .codered-shalom-result,
        #${c} .codered-shalom-empty {
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
        #${c} .codered-shalom-result { cursor: pointer !important; }
        #${c} .codered-shalom-result:hover { background: #f3f4f6 !important; }
        #${c} .${C} {
          color: #4b5563 !important;
          display: inline-block !important;
          font-size: 12px !important;
          max-width: 140px !important;
          overflow: hidden !important;
          text-overflow: ellipsis !important;
          white-space: nowrap !important;
        }
        @media (prefers-color-scheme: dark) {
          #${c} #${y},
          #${c} .${S} { background: #111827 !important; color: #f9fafb !important; border-color: rgba(249, 250, 251, 0.28) !important; }
          #${c} .codered-shalom-result,
          #${c} .codered-shalom-empty { color: #f9fafb !important; }
          #${c} .codered-shalom-result:hover { background: #1f2937 !important; }
          #${c} .${C} { color: #d1d5db !important; }
        }
      </style>
      <input id="${y}" type="search" placeholder="Buscar agencia Shalom..." autocomplete="off" />
      <span class="${C}" aria-live="polite"></span>
      <ul class="${S}" role="listbox"></ul>
    `,n}function T(n){if(n.dataset.coderedSearchBound==="true")return;n.dataset.coderedSearchBound="true";const a=n.querySelector(`#${y}`),s=n.querySelector(`.${S}`),l=n.querySelector(`.${C}`);if(!a||!s||!l)return;let u=null;a.addEventListener("input",()=>{u&&window.clearTimeout(u),u=window.setTimeout(()=>k(a,s,l),150)}),a.addEventListener("focus",()=>{k(a,s,l),s.style.display="block"});const h=new window.AbortController;n.dataset.coderedAbortController="true",document.addEventListener("click",b=>{n.contains(b.target)||(s.style.display="none")},{signal:h.signal})}function w(){if(!document.body)return{success:!1,reason:"body-not-ready"};if(!p())return{success:!1,reason:"unsupported-page"};const n=document.getElementById(c);if(n?.isConnected)return T(n),I(document,P),console.log("[Shalom Pro] Buscador ya estaba inyectado"),{success:!0,reason:"already-mounted",element:n};const a=v();if(!a)return{success:!1,reason:"target-not-found"};const s=$(),l=a.element.querySelector(".mdl-layout-spacer");return l?l.before(s):a.element.appendChild(s),T(s),I(document,P),console.log("[Shalom Pro] Buscador inyectado"),{success:!0,reason:"mounted",element:s}}function R(){if(r)return;const n=document.documentElement??document.body;n&&(r=new MutationObserver(()=>{i&&window.clearTimeout(i),i=window.setTimeout(()=>{i=null,document.getElementById(c)?.isConnected||console.log("[Shalom Pro] Buscador eliminado por navegación; reinyectando"),w()},100)}),r.observe(n,{childList:!0,subtree:!0}))}function j(){i&&window.clearTimeout(i),i=null,r?.disconnect(),r=null}function N(){return f(o).then(()=>w())}function P(n){o=n,console.log(`[Shalom Pro] Segmento activo: ${o}`)}function k(n,a,s){a.innerHTML="",a.style.display="block";const l=n.value.trim();if(t.length===0){D(a,"No hay agencias sincronizadas. Abre la configuración y pulsa Sincronizar ahora");return}if(l.length<2){s.textContent="";return}const u=O(document);s.textContent=u instanceof HTMLSelectElement?`Canal: ${o}`:"No hay selector de destino.";const h=W(t,l,8);if(h.length===0){D(a,"No se encontraron agencias.");return}for(const{agency:b}of h)a.appendChild(U(b,n,a,s))}function U(n,a,s,l){const u=document.createElement("button");u.className="codered-shalom-result",u.type="button";const h=[n.name,n.code].filter(Boolean).join(" - "),b=[n.department,n.province,n.district].filter(Boolean).join(" / ");u.innerHTML=`<strong>${M(h)}</strong><br><small>${M(b)}</small>`,u.addEventListener("click",()=>{const x=Q(document,n,o);x.success?(a.value="",s.innerHTML="",s.style.display="none",l.textContent="Agencia seleccionada",window.setTimeout(()=>l.textContent="",2e3)):l.textContent=x.message});const g=document.createElement("a");g.href=K(n),g.target="_blank",g.rel="noopener noreferrer",g.textContent="Ver mapa",g.addEventListener("click",x=>x.stopPropagation());const _=document.createElement("li");return _.append(u,g),_}function D(n,a){const s=document.createElement("li");s.className="codered-shalom-empty",s.textContent=a,n.appendChild(s)}function V(){const n=document.getElementById(y);n?.value&&n.dispatchEvent(new Event("input",{bubbles:!0}))}function F(){d||typeof chrome>"u"||typeof chrome.storage?.onChanged?.addListener!="function"||(d=!0,chrome.storage.onChanged.addListener((n,a)=>{a==="local"&&Object.keys(n).some(s=>le.has(s))&&f(o)}))}async function G(){if(e.requestCatalog)return e.requestCatalog();if(typeof chrome>"u"||typeof chrome.runtime?.sendMessage!="function")return console.error("[Shalom Pro] chrome.runtime.sendMessage no está disponible."),[];const n=await chrome.runtime.sendMessage({type:"CATALOG_GET"});return Array.isArray(n?.agencies)?n.agencies:[]}return{cargarDatos:f,createSearchContainer:$,bindSearchEvents:T,findInjectionTarget:v,injectSearchIfPossible:w,initializeContentScript:m,isSupportedShalomPage:p,mount:N,startInjectionObserver:R,stopInjectionObserver:j}}function de(){return typeof document>"u"||typeof window>"u"||!document.body?!1:typeof chrome>"u"?(console.error("[Shalom Pro] chrome no está disponible para content.js"),!1):typeof chrome.storage>"u"?(console.error("[Shalom Pro] chrome.storage no está disponible para content.js"),!1):!0}function ue(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const o=window.getComputedStyle(t);if(o.display==="none"||o.visibility==="hidden"||o.opacity==="0")return!1}return!0}function q(e){return e instanceof Error?{name:e.name,message:e.message,stack:e.stack}:{message:String(e)}}function M(e){return e.replace(/[&<>"]/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"})[t]??t)}function me(){if(typeof document>"u")return;const e=ce(),t=()=>{e.initializeContentScript().catch(o=>{console.error("[Shalom Pro] Falló la inicialización del content script",q(o))})};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",t,{once:!0}):t()}me();
