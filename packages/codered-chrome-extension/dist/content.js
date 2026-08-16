var CodeREDShalomContent=(function(_){"use strict";function Se(e){return e.status==="active"||e.status==="moved"||e.status==="temporarily_closed"}function Fe(e){return e.status==="moved"||e.hasMoved?{tone:"warning",message:"Esta agencia fue trasladada",details:[e.movedToAgencyName?`Destino: ${e.movedToAgencyName}`:null,e.movedToAddress?`Nueva direccion: ${e.movedToAddress}`:null].filter(t=>t!==null)}:e.status==="temporarily_closed"?{tone:"danger",message:"Esta agencia se encuentra cerrada temporalmente",details:e.observations?[e.observations]:[]}:null}function y(e){return String(e??"").normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase().replace(/[^a-z0-9]+/g," ").replace(/centro\s+de\s+operaciones/g,"co").replace(/\b(c)\s+(o)\b/g,"co").replace(/\s+/g," ").trim()}function We(e){if(!e)return!1;try{const t=new URL(e);return t.protocol==="http:"||t.protocol==="https:"}catch{return!1}}function Ge(e){if(We(e.mapUrl))return e.mapUrl;if(e.latitude!==null&&e.longitude!==null)return`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${e.latitude},${e.longitude}`)}`;const t=[e.name,e.address,e.department,e.province,e.district].filter(Boolean).join(" ");return`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(t)}`}function Ve(e,t,r=20){const n=y(t),c=n.split(" ").filter(Boolean);return n===""?e.filter(Se).slice(0,r).map(d=>({agency:d,score:1})):e.filter(Se).map(d=>({agency:d,score:Ke(d,n,c)})).filter(d=>d.score>0).sort((d,p)=>p.score-d.score||String(d.agency.name).localeCompare(p.agency.name)).slice(0,r)}function Ke(e,t,r){const n=y(e.code),c=y(e.name),d=y(e.oldName),p=y(e.shortName),m=y([e.department,e.province,e.district,e.address,e.reference,e.ubigeoId,e.category,e.sendsCategory,e.receivesCategory].filter(Boolean).join(" "));return n!==""&&n===t?100:c===t||p===t?90:c.startsWith(t)||p.startsWith(t)?80:d===t||Y(d,r)?70:Y(c,r)||Y(p,r)?60:Y(m,r)?40:0}function Y(e,t){return t.length>0&&t.every(r=>e.includes(r))}function re(e,t){return t==="TERRESTRE"?e.terrestrialText??"":t==="AEREO"?e.airText??"":""}function Ye(e=document,t="TERRESTRE"){const n=Array.from(e.querySelectorAll('select[id*="osProDestino"]')).filter(m=>m instanceof HTMLSelectElement).filter(m=>!m.disabled&&!m.hidden&&m.getAttribute("aria-hidden")!=="true"&&Qe(m));if(t===null){const m=n.filter(h=>{const x=h.ownerDocument.getElementById(`${h.id}_chosen`);return x instanceof HTMLElement?P(x):P(h)});return m.length===0?null:m.length===1?m[0]:{reason:"multiple-active-selects",count:m.length}}const c=n.filter(m=>{const h=m.ownerDocument.getElementById(`${m.id}_chosen`);return h instanceof HTMLElement&&P(h)&&Ae(h,t)});if(c.length===1)return c[0];if(c.length>1)return{reason:"multiple-active-selects",count:c.length};const d=n.filter(m=>Ae(m,t));if(d.length===1)return d[0];if(d.length>1)return{reason:"multiple-active-selects",count:d.length};const p=n.filter(m=>{const h=m.ownerDocument.getElementById(`${m.id}_chosen`);return h instanceof HTMLElement?P(h):P(m)});return p.length===0?null:p.length===1?p[0]:{reason:"multiple-active-selects",count:p.length}}function Ze(e,t,r){const n=r==="AUTO"?null:r,c=n?[n]:["TERRESTRE","AEREO"],d=Ye(e,n);if(!d)return{success:!1,reason:"no-destination-select",message:"No se encontró el selector de destino activo de Shalom.",channel:n??void 0};if(!(d instanceof HTMLSelectElement))return{success:!1,reason:"multiple-active-selects",message:"Se encontraron varios selectores de destino activos y no se realizó ningún cambio.",channel:n??void 0};for(const x of c){const D=re(t,x);if(!D)continue;const E=$e(d,D,t);if(Ce(E)){if(E.reason==="option-not-found")continue;return{success:!1,reason:"multiple-matching-options",message:`Hay múltiples opciones coincidentes para ${x==="AEREO"?"Aéreo":"Terrestre"}; no se cambió el selector.`,channel:x}}return d.value=E.value,E.selected=!0,d.dispatchEvent(new Event("input",{bubbles:!0})),d.dispatchEvent(new Event("change",{bubbles:!0})),Le(d),ke(d),d.value!==E.value?{success:!1,reason:"option-not-found",message:"Shalom Control no confirmó el cambio del selector.",channel:x}:{success:!0,value:E.value,channel:x}}const p=c[0],m=re(t,p);if(!m)return{success:!1,reason:"missing-channel-text",message:`La agencia no tiene identificador Chosen para el canal ${p==="AEREO"?"Aéreo":"Terrestre"}.`,channel:p};const h=$e(d,m,t);return Ce(h)?h.reason==="option-not-found"?{success:!1,reason:"option-not-found",message:`La agencia está registrada, pero no está disponible en el selector actual de Shalom (${p==="AEREO"?"Aéreo":"Terrestre"}).`,channel:p}:{success:!1,reason:"multiple-matching-options",message:`Hay múltiples opciones coincidentes para ${p==="AEREO"?"Aéreo":"Terrestre"}; no se cambió el selector.`,channel:p}:(d.value=h.value,h.selected=!0,d.dispatchEvent(new Event("input",{bubbles:!0})),d.dispatchEvent(new Event("change",{bubbles:!0})),Le(d),ke(d),d.value!==h.value?{success:!1,reason:"option-not-found",message:"Shalom Control no confirmó el cambio del selector.",channel:p}:{success:!0,value:h.value,channel:p})}function Ce(e){return"reason"in e}function $e(e,t,r){const n=Array.from(e.options),c=n.filter(h=>h.text.trim()===t.trim());if(c.length===1)return c[0];if(c.length>1)return{reason:"multiple-matching-options"};const d=y(t),p=n.filter(h=>y(h.text)===d);if(p.length===1)return p[0];if(p.length>1)return{reason:"multiple-matching-options"};const m=y(r.externalId);if(m){const h=n.filter(x=>y(x.text).includes(m));if(h.length===1)return h[0];if(h.length>1)return{reason:"multiple-matching-options"}}return{reason:"option-not-found"}}function Ae(e,t){for(let r=e;r;r=r.parentElement){const n=y([r.id,r.className,r.getAttribute("data-channel"),r.getAttribute("aria-label"),r.getAttribute("title")].filter(Boolean).join(" "));if(t==="TERRESTRE"&&(n.includes("terrestre")||n.includes("camion"))||t==="AEREO"&&(n.includes("aereo")||n.includes("avion")))return!0}return!1}function Qe(e){for(let t=e.parentElement;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const r=t.getAttribute("style")?.replace(/\s+/g,"").toLowerCase()??"";if(r.includes("display:none")||r.includes("visibility:hidden"))return!1}return!0}function P(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const r=t.getAttribute("style")?.replace(/\s+/g,"").toLowerCase()??"";if(r.includes("display:none")||r.includes("visibility:hidden"))return!1}return e.getClientRects().length>0||e.offsetWidth>0||e.offsetHeight>0||e.getAttribute("data-visible")==="true"||!e.hasAttribute("style")}function ke(e){const t=e.ownerDocument.defaultView?.jQuery;typeof t=="function"&&t(e).trigger("chosen:updated")}function Le(e){const t=`${e.id}_chosen`,n=e.ownerDocument.getElementById(t)?.querySelector(".chosen-single span, .chosen-container span"),c=e.selectedOptions.item(0);n&&c&&(n.textContent=c.text)}const Xe=['button[title*="Terrestre" i]','button[title*="Aéreo" i]','button[title*="Aereo" i]','[onclick*="TERRESTRE" i]','[onclick*="AEREO" i]','[aria-label*="Terrestre" i]','[aria-label*="Aéreo" i]','[aria-label*="Aereo" i]',".mdl-tabs__tab",'[role="tab"]',"button","a"],Je=["active","is-active","selected","mdl-button--colored","mdl-tabs__tab--active"];function Z(e=document){const r=Te(e).map(c=>({element:c,channel:Re(c),active:et(c)})).filter(c=>c.channel!==null),n=r.find(c=>c.active);return n?{channel:n.channel,reason:"detected",candidates:r.length}:r.length>1?{channel:null,reason:"ambiguous",candidates:r.length}:{channel:null,reason:"pending",candidates:r.length}}function ie(e,t){const r=Te(e);for(const n of r)!Re(n)||n.dataset.coderedChannelBound==="true"||(n.dataset.coderedChannelBound="true",n.addEventListener("click",()=>{window.setTimeout(()=>{const d=Z(e);d.channel&&t(d.channel)},0)}))}function Te(e){const t=new Set;for(const r of Xe)for(const n of Array.from(e.querySelectorAll(r)))n instanceof HTMLElement&&t.add(n);return Array.from(t)}function Re(e){const t=y([e.getAttribute("title"),e.getAttribute("aria-label"),e.getAttribute("onclick"),e.textContent,e.className,e.querySelector("i, svg, img")?.getAttribute("title"),e.querySelector("i, svg, img")?.getAttribute("aria-label"),e.querySelector("i, svg, img")?.getAttribute("class")].filter(Boolean).join(" "));return t.includes("terrestre")||t.includes("camion")||t.includes("truck")?"TERRESTRE":t.includes("aereo")||t.includes("avion")||t.includes("plane")||t.includes("flight")?"AEREO":null}function et(e){return e.getAttribute("aria-selected")==="true"||Je.some(t=>e.classList.contains(t))}const tt={SERVICE_ORDER_LOCK:"codered_service_order_lock"},nt="America/Lima",_e=480,ae=1205;function De(e=new Date){const r=new Intl.DateTimeFormat("en-GB",{timeZone:nt,hour12:!1,year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit"}).formatToParts(e),n=Object.fromEntries(r.map(c=>[c.type,c.value]));return{year:Number(n.year),month:Number(n.month),day:Number(n.day),hour:Number(n.hour),minute:Number(n.minute),second:Number(n.second)}}function ot(e=new Date){const{hour:t,minute:r}=De(e),n=t*60+r;return n>=_e&&n<ae}function rt(e=new Date,t=!1){const r=!ot(e),n=r||t,c=r?it(e):null,d=c?Math.max(0,c.getTime()-e.getTime()):0;return{lockedBySchedule:r,locked:n,reason:r&&t?"schedule+manual":r?"schedule":t?"manual":"unlocked",nextAllowedAt:c,remainingMs:d,remainingLabel:Oe(d)}}function it(e=new Date){const t=De(e),r=t.hour*60+t.minute,n=new Date(e.getTime());return r<_e?(n.setHours(8,0,0,0),n):r>ae||r===ae&&t.second>0?(n.setDate(n.getDate()+1),n.setHours(8,0,0,0),n):(n.setHours(t.hour,t.minute,t.second,0),n)}function Oe(e){const t=Math.max(0,Math.floor(e/1e3)),r=String(Math.floor(t/3600)).padStart(2,"0"),n=String(Math.floor(t%3600/60)).padStart(2,"0"),c=String(t%60).padStart(2,"0");return`${r}:${n}:${c}`}const f="codered-service-order-lock-overlay",Be=tt.SERVICE_ORDER_LOCK,at="data-codered-service-order-locked",st="sysnewos.shalomcontrol.com",ct="/service-order";function dt(e){let t=!1,r=!1,n=null,c=null,d=null,p=null,m=!1,h=!1;const x=new Set;async function D(){r||(r=!0,t=await e.getManualLock(),E(),me(),T())}function E(){m||typeof chrome>"u"||typeof chrome.storage?.onChanged?.addListener!="function"||(m=!0,chrome.storage.onChanged.addListener((a,v)=>{v==="local"&&Be in a&&(t=!!a[Be].newValue,T())}))}function me(){B(),window.addEventListener("popstate",C,{passive:!0}),window.addEventListener("hashchange",C,{passive:!0}),d=new MutationObserver(C),d.observe(document.documentElement,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["class","style","hidden"]})}function B(){const a=window;if(a.__coderedServiceOrderHistoryPatched__)return;a.__coderedServiceOrderHistoryPatched__=!0;const v=history.pushState.bind(history),ye=history.replaceState.bind(history);history.pushState=(...F)=>{const W=v(...F);return C(),W},history.replaceState=(...F)=>{const W=ye(...F);return C(),W}}function C(){p&&window.clearTimeout(p),p=window.setTimeout(()=>{p=null,T()},25)}function ee(){return window.location.hostname.toLowerCase()===st&&he(window.location.pathname)===ct}function he(a){return a.toLowerCase().replace(/\/+$/,"")||"/"}function I(){const a=ee(),v=rt(new Date,t);return a?{visible:!0,locked:v.locked,lockedBySchedule:v.lockedBySchedule,manualLocked:t,reason:v.reason,remainingLabel:v.remainingLabel}:{visible:!1,locked:!1,lockedBySchedule:!1,manualLocked:!1,reason:"outside-scope",remainingLabel:""}}function T(){const a=I();if(ge(a),!a.visible){z(),$();return}if(a.locked){be(a),ne();return}z(),$()}function ge(a){for(const v of x)v(a)}function be(a){const v=document.getElementById(f);n=v??document.createElement("div"),n.id=f,n.setAttribute("role","dialog"),n.setAttribute("aria-modal","true"),n.setAttribute("aria-live","assertive"),n.setAttribute(at,"true"),n.tabIndex=-1,n.innerHTML=te(a),U(n),v||document.documentElement.appendChild(n),M(),xe(),R(a)}function te(a){const v=a.reason==="schedule+manual"?"Horario + bloqueo manual":a.reason==="manual"?"Bloqueo manual":"Fuera del horario permitido";return`
      <div class="codered-service-order-lock-card">
        <button class="codered-service-order-lock-close" type="button" aria-label="Cerrar aviso" title="Cerrar aviso" disabled>×</button>
        <div class="codered-service-order-lock-hero">
          <div class="codered-service-order-lock-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Zm3 4a1.75 1.75 0 0 1 .95 3.23V18a1 1 0 1 1-2 0v-.77A1.75 1.75 0 0 1 12 14Z"/>
            </svg>
          </div>
          <div class="codered-service-order-lock-hero-copy">
            <span class="codered-service-order-lock-badge">Bloqueo activo</span>
            <h2>Operaciones temporalmente bloqueadas</h2>
            <p>Las operaciones en este módulo se encuentran fuera del horario permitido.</p>
            <p class="codered-service-order-lock-emphasis">Podrás continuar a partir de las 08:00 h.</p>
          </div>
        </div>

        <div class="codered-service-order-lock-callout codered-service-order-lock-callout--warning">
          <strong>Estás a punto de desbloquear el módulo fuera del horario permitido (08:00 h – 20:05 h).</strong>
          <span>Esta acción puede afectar procesos y métricas del sistema. Úsalo solo si es estrictamente necesario.</span>
        </div>

        <div class="codered-service-order-lock-callout codered-service-order-lock-callout--info">
          <strong>Podrás continuar a partir de las 08:00 h o desactivar manualmente esta opción cuando ya no la necesites.</strong>
        </div>

        <dl class="codered-service-order-lock-details">
          <div><dt>Estado</dt><dd class="codered-service-order-lock-pill">BLOQUEADO</dd></div>
          <div><dt>Motivo</dt><dd>${lt(v)}</dd></div>
          <div><dt>Horario permitido</dt><dd>08:00 h - 20:05 h</dd></div>
          <div><dt>Disponible nuevamente en</dt><dd id="codered-service-order-lock-countdown" class="codered-service-order-lock-countdown">${a.remainingLabel}</dd></div>
        </dl>

        <div class="codered-service-order-lock-footnote">
          <span class="codered-service-order-lock-footnote-icon" aria-hidden="true">i</span>
          <p>El sistema seguirá bloqueado automáticamente si coincide con el horario restringido.</p>
        </div>
      </div>
    `}function U(a){a.style.position="fixed",a.style.top="0",a.style.left="0",a.style.right="0",a.style.bottom="0",a.style.zIndex="2147483647",a.style.background="rgba(255, 255, 255, 0.68)",a.style.backdropFilter="blur(2px)",a.style.display="grid",a.style.placeItems="center",a.style.pointerEvents="auto",a.style.fontFamily='Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',a.style.color="#1f2937",a.style.padding="24px",a.style.overflow="hidden",a.style.isolation="isolate",ve()}function ve(){if(document.getElementById("codered-service-order-lock-styles"))return;const a=document.createElement("style");a.id="codered-service-order-lock-styles",a.textContent=`
      #${f} {
        font-synthesis: none;
      }

      #${f} .codered-service-order-lock-card {
        width: min(520px, calc(100vw - 48px));
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(247, 250, 255, 0.98) 100%);
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
        padding: 28px 28px 24px;
        color: #1f2937;
        position: relative;
      }

      #${f} .codered-service-order-lock-close {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 28px;
        height: 28px;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #64748b;
        font-size: 26px;
        line-height: 1;
        cursor: default;
      }

      #${f} .codered-service-order-lock-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 4px;
        border-radius: 18px 18px 0 0;
        background: linear-gradient(90deg, #2563eb 0%, #60a5fa 100%);
      }

      #${f} .codered-service-order-lock-hero {
        display: grid;
        grid-template-columns: 84px minmax(0, 1fr);
        gap: 18px;
        align-items: center;
      }

      #${f} .codered-service-order-lock-icon {
        width: 84px;
        height: 84px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, #eaf2ff 0%, #dbeafe 100%);
        color: #2563eb;
        box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.12);
      }

      #${f} .codered-service-order-lock-icon svg {
        width: 36px;
        height: 36px;
        fill: currentColor;
      }

      #${f} .codered-service-order-lock-hero-copy {
        min-width: 0;
      }

      #${f} .codered-service-order-lock-badge {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 12px;
        border-radius: 999px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        margin-bottom: 10px;
      }

      #${f} h2 {
        margin: 0;
        font-size: 22px;
        line-height: 1.15;
        color: #0f172a;
      }

      #${f} p {
        margin: 10px 0 0;
        font-size: 15px;
        line-height: 1.5;
        color: #475569;
      }

      #${f} .codered-service-order-lock-emphasis {
        color: #2563eb;
        font-weight: 700;
      }

      #${f} .codered-service-order-lock-details {
        margin: 20px 0 0;
        padding: 18px 0 0;
        border-top: 1px solid #eef2f7;
        display: grid;
        gap: 14px;
      }

      #${f} .codered-service-order-lock-details > div {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: center;
      }

      #${f} dt {
        margin: 0;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
      }

      #${f} dd {
        margin: 0;
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
        text-align: right;
      }

      #${f} .codered-service-order-lock-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        background: #fee2e2;
        color: #dc2626;
        box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.08);
      }

      #${f} .codered-service-order-lock-countdown {
        color: #2563eb;
        font-size: 16px;
      }

      #${f} .codered-service-order-lock-callout {
        margin-top: 14px;
        border-radius: 12px;
        border: 1px solid transparent;
        padding: 12px 14px;
        display: grid;
        gap: 4px;
      }

      #${f} .codered-service-order-lock-callout strong,
      #${f} .codered-service-order-lock-callout span {
        display: block;
      }

      #${f} .codered-service-order-lock-callout--warning {
        border-color: #f6d59f;
        background: #fff7ed;
        color: #9a3412;
      }

      #${f} .codered-service-order-lock-callout--warning strong {
        font-size: 14px;
        color: #9a3412;
      }

      #${f} .codered-service-order-lock-callout--warning span {
        font-size: 13px;
        color: #dc2626;
      }

      #${f} .codered-service-order-lock-callout--info {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #2563eb;
      }

      #${f} .codered-service-order-lock-callout--info strong {
        font-size: 13px;
        color: #2563eb;
      }

      #${f} .codered-service-order-lock-footnote {
        margin-top: 16px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #eef2ff;
        color: #4f46e5;
      }

      #${f} .codered-service-order-lock-footnote-icon {
        display: inline-grid;
        place-items: center;
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        border-radius: 999px;
        background: #2563eb;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
      }

      #${f} .codered-service-order-lock-footnote p {
        margin: 0;
        font-size: 13px;
        line-height: 1.45;
        color: #4338ca;
      }

      @media (max-width: 560px) {
        #${f} {
          padding: 18px;
        }

        #${f} .codered-service-order-lock-card {
          width: min(100%, 520px);
          padding: 22px 18px 18px;
        }

        #${f} .codered-service-order-lock-hero {
          grid-template-columns: 64px minmax(0, 1fr);
          gap: 14px;
        }

        #${f} .codered-service-order-lock-icon {
          width: 64px;
          height: 64px;
        }

        #${f} h2 {
          font-size: 20px;
        }

        #${f} p {
          font-size: 14px;
        }
      }
    `,document.head.appendChild(a)}function xe(){h||(h=!0,n?.addEventListener("keydown",A,!0),n?.addEventListener("click",A,!0),document.addEventListener("keydown",A,!0),document.addEventListener("keypress",A,!0),document.addEventListener("keyup",A,!0),document.addEventListener("click",A,!0),document.addEventListener("focusin",A,!0))}function M(){n?.focus()}function ne(){c||(c=window.setInterval(()=>{const a=I();if(!a.visible||!a.locked){T();return}R(a)},1e3))}function R(a){const v=document.getElementById("codered-service-order-lock-countdown");v&&(v.textContent=a.remainingLabel||Oe(0))}function $(){c&&window.clearInterval(c),c=null}function z(){n?.remove(),n=null,h=!1}function A(a){n?.isConnected&&(a.target instanceof HTMLElement&&a.target.closest(`#${f}`)||(a.preventDefault(),a.stopPropagation(),"stopImmediatePropagation"in a&&a.stopImmediatePropagation()))}function we(a){return x.add(a),a(I()),()=>x.delete(a)}async function Ee(a){t=a,await e.setManualLock(a),T()}function O(){return I()}return{initialize:D,getState:O,onStateChange:we,setManualLock:Ee,refresh:T,destroy(){$(),d?.disconnect(),d=null,z(),x.clear()}}}function lt(e){return e.replace(/[&<>"']/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[t]??t)}const ut="shalomcontrol.com",pt=["/listaordenservicio","/ordenservicio/listar","/service-order"];function ft(e,t){const r=X(e);if(!r||!r.endsWith(`.${ut}`))return!1;const n=[].map(X).filter(Boolean);return n.length===0?!0:n.some(c=>Ie(r,c))}function mt(e){const t=ce(e);return t?pt.some(r=>t===r):!1}function Q(e){return["/listaordenservicio","/service-order"].includes(ce(e))}function ht(e){return Q(e)?{mode:"neutral",search:!0,neutralChannel:!0,agencySelection:!1,channelDetection:!1}:{mode:"interactive",search:!0,neutralChannel:!1,agencySelection:!0,channelDetection:!0}}function se(e){return ce(e)==="/service-order"?{site:"sysnewos",module:"service-order",mode:"neutral"}:{site:"shalom",module:"legacy",mode:"interactive"}}function gt(e,t){return Ie(e,t)}function Ie(e,t){const r=X(e),n=X(t);return r===n||r.endsWith(`.${n}`)}function X(e){return String(e??"").trim().toLowerCase().replace(/\.$/,"")}function ce(e){const t=String(e??"").trim();if(!t)return"";const r=t.split("#")[0].split("?")[0].toLowerCase();return r.startsWith("/")?r.length>1?r.replace(/\/+$/,""):r:""}const l="mi-buscador-contenedor",H="codered-search-input",k="codered-results-panel",q="codered-results-grid",de="codered-channel-badge",j="codered-search-message",bt=50,vt=["shalomcontrol.com"],xt=new Set(["agencies","agencyCache","catalog","catalogVersion","syncMetadata","codered_agency_catalog","codered_catalog_version","codered_sync_metadata","codered_last_sync_at","codered_last_sync_status"]),Me="__coderedShalomHistoryPatched__";function ze(e={}){let t=[],r=null,n=!1,c=0,d=null,p=null,m=!1,h=!1,x=!1;const D=dt({getManualLock:async()=>typeof chrome>"u"||typeof chrome.storage?.local?.get!="function"?!1:(await chrome.storage.local.get(["codered_service_order_lock"])).codered_service_order_lock===!0,setManualLock:async o=>{typeof chrome>"u"||typeof chrome.storage?.local?.set!="function"||await chrome.storage.local.set({codered_service_order_lock:o})}}),E=new Set;async function me(){if(console.log("[CodeRED Shalom] Content script iniciado"),console.log(`[CodeRED Shalom] URL actual: ${window.location.href}`),!Ct()||!C())return;await G(!0),await B(),await D.initialize();const o=$();console.log("[CodeRED Shalom] Resultado de inyección",{reason:o.reason}),z(),ve(),Lt()}async function B(o){o&&(r=o);try{t=await Rt(),console.log(`[CodeRED Shalom] Catálogo local cargado: ${t.length} agencias`),kt()}catch(i){console.error("[CodeRED Shalom] Falló la carga del catálogo",fe(i)),t=[]}}function C(){const o=window.location.hostname.toLowerCase(),i=window.location.pathname,s=(e.allowedDomains??vt).map(u=>u.trim().toLowerCase()).filter(Boolean);return ft(o)?mt(i)?s.length===0||s.some(u=>gt(o,u))?(console.log("[CodeRED Shalom] Página compatible"),!0):(console.warn("[CodeRED Shalom] Inyección omitida",{reason:"domain-not-allowed",hostname:o,allowedDomains:s}),!1):(console.warn("[CodeRED Shalom] Inyección omitida",{reason:"unsupported-path",pathname:i}),!1):(console.warn("[CodeRED Shalom] Inyección omitida",{reason:"unsupported-page",hostname:o}),!1)}function ee(){if(se(window.location.pathname).mode==="neutral")return he();console.log("[CodeRED Shalom] Buscando punto de inyección");const i=[".mdl-layout__header-row","header .mdl-layout__header-row",".mdl-layout__header","header",'[role="banner"]',".navbar",".topbar",".header"];for(const s of i){const b=Array.from(document.querySelectorAll(s)).filter(g=>g instanceof HTMLElement).find(g=>pe(g)&&!g.closest(`#${l}`));if(b)return console.log(`[CodeRED Shalom] Target encontrado: ${s}`),{element:b,selector:s,mode:"interactive"}}return console.log("[CodeRED Shalom] Target todavía no disponible"),null}function he(){const o=Array.from(document.querySelectorAll("main.service-order-module, .service-order-module")).filter(i=>i instanceof HTMLElement&&pe(i));for(const i of o){const s=I(i);if(s)return console.log("[CodeRED][SYSNEWOS] Search anchor resolved"),s}return null}function I(o){const i=Array.from(o.children).filter(s=>s instanceof HTMLElement);for(const s of i){if(!pe(s)||!T(s))continue;const u=ge(s);if(u)return{element:o,parent:s,before:u,selector:be(s),mode:"neutral"}}return null}function T(o){const i=Array.from(o.children).filter(g=>g instanceof HTMLElement);if(i.length<2)return!1;const s=i.map(g=>qe(g.className)),u=s.some(g=>g.includes("flex-1")||g.includes("gap-2")),b=s.some(g=>g.includes("justify-end")||g.includes("items-center")||g.includes("gap-x-12"));return u&&b}function ge(o){const i=Array.from(o.children).filter(u=>u instanceof HTMLElement),s=i.filter(u=>{const b=qe(u.className);return b.includes("justify-end")||b.includes("items-center")||b.includes("gap-x-12")});return s.length>0?s[s.length-1]:i.length>1?i[i.length-1]:null}function be(o){const i=o.tagName.toLowerCase(),s=o.id?`#${o.id}`:"",u=o.classList.length?`.${Array.from(o.classList).join(".")}`:"";return`${i}${s}${u}`}function te(){const o=document.createElement("div");return o.id=l,o.className="codered-shalom-search",o.innerHTML=`
      <style>${St()}</style>
      <div class="codered-search-wrapper">
        <span class="codered-search-icon" aria-hidden="true">⌕</span>
        <input id="${H}" class="codered-search-input" type="search" placeholder="Buscar agencia Shalom..." autocomplete="off" />
        <span class="${de}" aria-live="polite"></span>
      </div>
      <div class="${k}" hidden>
        <div class="${j}" aria-live="polite"></div>
        <div class="${q}" role="listbox"></div>
      </div>
    `,ue(o),o}function U(o){if(o.dataset.coderedSearchBound==="true")return;o.dataset.coderedSearchBound="true";const i=o.querySelector(`#${H}`),s=o.querySelector(`.${k}`),u=o.querySelector(`.${q}`),b=o.querySelector(`.${j}`);if(!i||!s||!u||!b)return;Ee(o,s);let g=null;i.addEventListener("input",()=>{g&&window.clearTimeout(g),g=window.setTimeout(()=>a(i,s,u,b),150)}),i.addEventListener("focus",()=>a(i,s,u,b)),i.addEventListener("keydown",N=>{if(N.key==="Escape"&&J(o),N.key==="Enter"){const V=u.querySelector(".codered-agency-card");V&&V.click()}});const w=new window.AbortController;document.addEventListener("click",N=>{o.contains(N.target)||J(o)},{signal:w.signal})}function ve(){x||(x=!0,xe(),window.addEventListener("popstate",R,{passive:!0}),window.addEventListener("hashchange",R,{passive:!0}),document.addEventListener("visibilitychange",R,{passive:!0}))}function xe(){const o=window;if(o[Me])return;o[Me]=!0;const i=history.pushState.bind(history),s=history.replaceState.bind(history);history.pushState=(...u)=>{const b=i(...u);return ne(),b},history.replaceState=(...u)=>{const b=s(...u);return ne(),b}}let M=null;function ne(){M&&window.clearTimeout(M),M=window.setTimeout(()=>{M=null,R()},50)}function R(){if(!C())return;D.refresh();const o=document.getElementById(l);o&&!o.isConnected&&o.remove();const i=$();i.element&&le(i.element)}function $(){if(!document.body)return{success:!1,reason:"body-not-ready"};if(!C())return{success:!1,reason:"unsupported-page"};G();const o=document.getElementById(l);if(o?.isConnected)return U(o),ie(document,O),ue(o),console.log("[CodeRED Shalom] Buscador ya estaba inyectado"),{success:!0,reason:"already-mounted",element:o};const i=ee();if(!i)return{success:!1,reason:"target-not-found"};const s=te();return Et(i,s),U(s),ie(document,O),console.log("[CodeRED Shalom] Buscador inyectado"),le(s),{success:!0,reason:"mounted",element:s}}function z(){if(d)return;const o=document.documentElement??document.body;o&&(d=new MutationObserver(()=>{p&&window.clearTimeout(p),p=window.setTimeout(()=>{p=null,ie(document,O),G(),R(),document.getElementById(l)?.isConnected||console.log("[CodeRED Shalom] El header cambió; reinyectando");const s=$();s.element&&le(s.element)},100)}),d.observe(o,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["class","aria-selected","style","hidden"]}))}function A(){p&&window.clearTimeout(p),p=null,d?.disconnect(),d=null}function we(){return C()?G(!0).then(()=>B()).then(()=>$()):Promise.resolve({success:!1,reason:"unsupported-page"})}function Ee(o,i){if(h)return;h=!0;let s=null;window.addEventListener("resize",()=>{s&&window.clearTimeout(s),s=window.setTimeout(()=>{s=null,i.hidden||L(o,i)},100)})}function O(o){r=o,console.log(`[CodeRED Shalom] Canal activo detectado: ${r}`);const i=document.getElementById(l);if(!i)return;ue(i);const s=i.querySelector(`#${H}`);s&&(s.value=""),J(i)}function a(o,i,s,u){i.hidden=!1,i.style.left="auto",i.style.right="0",i.style.transform="none",s.innerHTML="",u.textContent="";const b=o.value.trim(),g=Q(window.location.pathname);r||(g?u.textContent="Canal no identificado. Buscando en todas las agencias.":u.textContent="Todavía estamos detectando el canal activo de Shalom. Espera unos segundos e intenta de nuevo.",L(o.closest(`#${l}`),i));const w=r,N=w?t.filter(K=>re(K,w)):t;if(t.length===0){u.textContent="No hay agencias sincronizadas. Abre la configuración de la extensión y pulsa Sincronizar ahora.",L(o.closest(`#${l}`),i);return}if(b.length<2){u.textContent=w?`Escribe al menos 2 caracteres para buscar en el canal ${je(w)}.`:"Escribe al menos 2 caracteres para buscar en todas las agencias.",L(o.closest(`#${l}`),i);return}const V=Ve(N,b,30).map(K=>K.agency);if(V.length===0){u.textContent=w?`No se encontraron agencias para ‘${b}’ en el canal ${je(w)}.`:`No se encontraron agencias para ‘${b}’.`,L(o.closest(`#${l}`),i);return}for(const K of V)s.appendChild(v(K));L(o.closest(`#${l}`),i)}function v(o){const i=document.createElement("button");return i.className="codered-agency-card tarjeta",i.type="button",i.setAttribute("role","option"),i.innerHTML=wt(o),i.addEventListener("click",()=>ye(o)),i.querySelector(".btn-mapa-mini")?.addEventListener("click",u=>u.stopPropagation()),i}function ye(o){if(se(window.location.pathname).mode==="neutral"){F(o);return}W(o)}function F(o){const s=document.getElementById(l)?.querySelector(`.${j}`);s&&(s.textContent=`Consulta: ${o.name} · ${[o.department,o.province,o.district].filter(Boolean).join(" / ")}`)}function W(o){G();const i=document.getElementById(l),s=i?.querySelector(`#${H}`),u=i?.querySelector(`.${j}`),b=ht(window.location.pathname);if(!b.agencySelection){u&&(u.textContent="Esta página de Shalom solo permite consultar agencias.");return}const g=r??(b.neutralChannel?"AUTO":null);if(!g){u&&(u.textContent="No fue posible determinar el canal activo de Shalom todavía.");return}const w=Ze(document,o,g);if(w.success){s&&(s.value=""),i&&J(i);return}if(w.reason==="option-not-found"){Tt("select-agency-unavailable","[CodeRED Shalom] La agencia seleccionada no está disponible actualmente en Shalom Control",{channel:g,agency:Ne(o)}),u&&(u.textContent="La agencia seleccionada no está disponible actualmente en Shalom Control.");return}oe("select-agency","[CodeRED Shalom] No se pudo seleccionar agencia",{reason:w.reason,channel:g,agency:Ne(o),detail:w.message}),u&&(u.textContent=w.message)}function kt(){const o=document.getElementById(H);o?.value&&o.dispatchEvent(new Event("input",{bubbles:!0}))}function Lt(){m||typeof chrome>"u"||typeof chrome.storage?.onChanged?.addListener!="function"||(m=!0,chrome.storage.onChanged.addListener((o,i)=>{i==="local"&&Object.keys(o).some(s=>xt.has(s))&&B(r??void 0)}))}async function G(o=!1){const i=Z(document);return i.channel?(i.channel!==r&&O(i.channel),n=!1,c=0,i.channel):Q(window.location.pathname)?(n=!1,c=0,null):((o||!n)&&(n=!0,c=0,oe("channel-pending","[CodeRED Shalom] Canal activo no confirmado todavía; esperando a que Shalom termine de cargar el DOM",{path:window.location.pathname,reason:i.reason,candidates:i.candidates}),Ue()),null)}function Ue(){if(Q(window.location.pathname)){n=!1,c=0;return}if(c>=10){n=!1,oe("channel-pending-timeout","[CodeRED Shalom] El canal activo sigue sin poder determinarse tras varios intentos; se detiene la espera automática",{path:window.location.pathname});return}c+=1,window.setTimeout(()=>{const o=Z(document);if(o.channel){n=!1,c=0,O(o.channel);return}if(o.reason==="ambiguous"){oe("channel-ambiguous","[CodeRED Shalom] La detección del canal sigue ambigua; se mantendrá la búsqueda en espera",{path:window.location.pathname,candidates:o.candidates}),n=!1;return}Ue()},200)}function oe(o,i,s){E.has(o)||(E.add(o),console.warn(i,s))}function Tt(o,i,s){E.has(o)||(E.add(o),console.info(i,s))}async function Rt(){if(e.requestCatalog)return e.requestCatalog();if(typeof chrome>"u"||typeof chrome.runtime?.sendMessage!="function")return console.error("[CodeRED Shalom] chrome.runtime.sendMessage no está disponible."),[];const o=await chrome.runtime.sendMessage({type:"CATALOG_GET"});return Array.isArray(o?.agencies)?o.agencies:[]}return{cargarDatos:B,createSearchContainer:te,bindSearchEvents:U,findInjectionTarget:ee,injectSearchIfPossible:$,initializeContentScript:me,isSupportedShalomPage:C,mount:we,startInjectionObserver:z,stopInjectionObserver:A}}function Ne(e){return{id:e.id,externalId:e.externalId,code:e.code,name:e.name}}function wt(e){const t=Fe(e),r=[e.terrestrialText?"Terrestre":null,e.airText?"Aéreo":null].filter(Boolean).join(" / "),n=[`<span class="codered-badge">${S(e.statusLabel)}</span>`,r?`<span class="codered-badge codered-badge-service">${S(r)}</span>`:"",e.category?`<span class="codered-badge">${S(e.category)}</span>`:'<span class="codered-badge codered-badge-muted">Sin categoría</span>',e.isOperationsCenter?'<span class="codered-badge codered-badge-co">Centro de Operaciones</span>':"",e.sendsCategory?`<span class="codered-badge">Envía: ${S(e.sendsCategory)}</span>`:"",e.receivesCategory?`<span class="codered-badge">Recibe: ${S(e.receivesCategory)}</span>`:""].filter(Boolean).join(""),c=[e.department,e.province,e.district].filter(Boolean).join(" / "),d=Ge(e);return`
    <span class="codered-card-head">
      <strong>${S(e.name)}</strong>
      <a class="btn-mapa-mini" href="${$t(d)}" target="_blank" rel="noopener noreferrer">MAPA</a>
    </span>
    <span class="codered-card-code">${S([e.code,e.oldName?`Antes: ${e.oldName}`:null].filter(Boolean).join(" · "))}</span>
    <span class="codered-badges">${n}</span>
    ${c?`<span class="ubicacion">${S(c)}</span>`:""}
    ${e.address?`<span class="direccion">${S(e.address)}</span>`:""}
    ${e.reference?`<span class="direccion">Ref: ${S(e.reference)}</span>`:""}
    ${t?`<span class="codered-notice codered-notice-${t.tone}">${S([t.message,...t.details].join(" · "))}</span>`:""}
  `}function Et(e,t){if(e.mode==="neutral"&&e.parent){e.parent.classList.add("codered-search-host"),e.parent.insertBefore(t,e.before??null),t.dataset.insertionReason="before-service-order-block",console.debug("[CodeRED][SYSNEWOS] Neutral search mounted");return}Pe(e.element,t)}function Pe(e,t){const r=He(e);r.parent.classList.add("codered-search-host"),r.parent.insertBefore(t,r.before),t.dataset.insertionReason=r.reason;const n=t.previousElementSibling,c=t.nextElementSibling;console.debug("[CodeRED] Posición del buscador",{previous:n,next:c}),r.reason==="before-navigation"&&c instanceof HTMLElement&&c.classList.contains("mdl-navigation")&&console.debug("[CodeRED] Buscador confirmado antes de .mdl-navigation")}function He(e){const t=e.querySelector(":scope > .mdl-navigation, :scope > nav.mdl-navigation");if(t)return console.debug("[CodeRED] Buscador insertado antes de .mdl-navigation"),{parent:e,before:t,reason:"before-navigation"};const r=e.querySelector(":scope > #demo-menu-lower-right");if(r)return console.debug("[CodeRED] Buscador insertado antes del menú"),{parent:e,before:r,reason:"before-menu"};const n=e.querySelector(":scope > .mdl-layout-spacer");return n?(console.debug("[CodeRED] Buscador insertado después del spacer"),{parent:e,before:n.nextElementSibling,reason:"after-spacer"}):(console.debug("[CodeRED] Fallback appendChild"),{parent:e,before:null,reason:"append"})}function le(e){const t=e.querySelector(`.${k}`);t&&!t.hidden&&L(e,t)}function L(e,t){if(!e)return;t.style.left="auto",t.style.right="0",t.style.transform="none",(window.requestAnimationFrame??(n=>window.setTimeout(()=>n(Date.now()),0)))(()=>{if(window.innerWidth<=720){t.style.transform="none";return}const n=t.getBoundingClientRect(),c=16;let d=0;n.left<c&&(d+=c-n.left),n.right>window.innerWidth-c&&(d-=n.right-(window.innerWidth-c));const p=d+bt;t.style.transform=p===0?"none":`translateX(${p}px)`})}function J(e){const t=e.querySelector(`.${k}`);t&&(t.hidden=!0)}function qe(e){return String(e??"").toLowerCase().replace(/\s+/g," ").trim()}function ue(e){const t=e.querySelector(`.${de}`);t&&(t.textContent=yt(e.ownerDocument))}function yt(e){if(se(window.location.pathname).mode==="neutral")return"🌐 Modo neutral";const t=Z(e).channel;return t?t==="AEREO"?"✈️ Aéreo":"🚚 Terrestre":"⌛ Canal pendiente"}function je(e){return e==="AEREO"?"Aéreo":"Terrestre"}function St(){return`
    #${l}.codered-shalom-search { position: relative !important; display: flex !important; align-items: center !important; flex: 0 0 auto !important; min-width: 0 !important; z-index: 1200 !important; margin-right: 24px !important; }
    #${l} .codered-search-wrapper { width: clamp(300px, 24vw, 420px) !important; min-width: 280px !important; max-width: 42vw !important; height: 40px !important; display: flex !important; align-items: center !important; gap: 8px !important; background: #242424 !important; border: 2px solid #ff414d !important; border-radius: 24px !important; overflow: hidden !important; box-shadow: 0 8px 18px rgba(0,0,0,.22) !important; }
    #${l} .codered-search-icon { color: #ff737b !important; font-size: 18px !important; padding-left: 14px !important; }
    #${l} .codered-search-input { width: 100% !important; min-width: 0 !important; border: 0 !important; outline: 0 !important; background: transparent !important; color: #fff !important; padding: 10px 6px !important; font-size: 14px !important; }
    #${l} .codered-search-input::placeholder { color: rgba(255,255,255,.65) !important; }
    #${l} .${de} { color: #fff !important; background: rgba(255,255,255,.1) !important; border-radius: 999px !important; padding: 4px 10px !important; margin-right: 8px !important; white-space: nowrap !important; font-size: 12px !important; }
    #${l} .${k} { position: absolute !important; top: calc(100% + 12px) !important; left: auto !important; right: 0 !important; transform: none; width: min(1000px, calc(100vw - 32px)) !important; max-height: 550px !important; overflow-y: auto !important; padding: 16px !important; background: #202020 !important; border: 1px solid #343434 !important; border-radius: 16px !important; box-shadow: 0 16px 50px rgba(0,0,0,.4) !important; color: #fff !important; }
    #${l} .${k}[hidden] { display: none !important; }
    #${l} .${j} { color: #f5f5f5 !important; font-size: 14px !important; padding: 4px 2px 10px !important; }
    #${l} .${q} { display: grid !important; grid-template-columns: repeat(3, minmax(0, 1fr)) !important; gap: 15px !important; }
    #${l} .codered-agency-card { min-height: 210px !important; padding: 18px !important; background: #252525 !important; border: 1px solid #454545 !important; border-radius: 14px !important; color: #fff !important; text-align: left !important; cursor: pointer !important; display: flex !important; flex-direction: column !important; gap: 10px !important; font: inherit !important; }
    #${l} .codered-agency-card:hover, #${l} .codered-agency-card:focus { border-color: #ff414d !important; box-shadow: 0 0 0 2px rgba(255,65,77,.22) !important; outline: 0 !important; }
    #${l} .codered-card-head { display: flex !important; align-items: flex-start !important; justify-content: space-between !important; gap: 12px !important; }
    #${l} .codered-card-head strong { color: #fff !important; font-size: 16px !important; line-height: 1.25 !important; }
    #${l} .btn-mapa-mini { color: #fff !important; background: #ff414d !important; border-radius: 999px !important; padding: 5px 9px !important; text-decoration: none !important; font-size: 11px !important; font-weight: 700 !important; }
    #${l} .codered-card-code, #${l} .ubicacion, #${l} .direccion { color: rgba(255,255,255,.78) !important; font-size: 12px !important; line-height: 1.35 !important; }
    #${l} .codered-badges { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; }
    #${l} .codered-badge { color: #fff !important; background: #383838 !important; border: 1px solid #555 !important; border-radius: 999px !important; padding: 4px 8px !important; font-size: 11px !important; }
    #${l} .codered-badge-service { border-color: #ff414d !important; }
    #${l} .codered-badge-co { background: #552328 !important; border-color: #ff414d !important; }
    #${l} .codered-badge-muted { color: rgba(255,255,255,.65) !important; }
    #${l} .codered-notice { border-radius: 8px !important; padding: 8px !important; font-size: 12px !important; line-height: 1.35 !important; }
    #${l} .codered-notice-warning { background: rgba(245,158,11,.16) !important; color: #fde68a !important; }
    #${l} .codered-notice-danger { background: rgba(239,68,68,.16) !important; color: #fecaca !important; }
    .codered-search-host { overflow: visible !important; }
    @media (max-width: 1200px) { #${l} .codered-search-wrapper { width: 320px !important; } }
    @media (max-width: 1100px) { #${l} .${k} { width: min(760px, calc(100vw - 24px)) !important; } #${l} .${q} { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; } }
    @media (max-width: 900px) { #${l} { margin-right: 12px !important; } #${l} .codered-search-wrapper { width: 280px !important; max-width: 55vw !important; } }
    @media (max-width: 720px) { #${l} { margin: 8px 0 !important; width: 100% !important; } #${l} .codered-search-wrapper { width: 100% !important; max-width: 100% !important; } #${l} .${k} { position: fixed !important; left: 12px !important; right: 12px !important; top: 70px !important; width: auto !important; max-height: calc(100vh - 90px) !important; transform: none !important; } #${l} .${q} { grid-template-columns: 1fr !important; } }
  `}function Ct(){return typeof document>"u"||typeof window>"u"||!document.body?!1:typeof chrome>"u"?(console.error("[CodeRED Shalom] chrome no está disponible para content.js"),!1):typeof chrome.storage>"u"?(console.error("[CodeRED Shalom] chrome.storage no está disponible para content.js"),!1):!0}function pe(e){for(let t=e;t;t=t.parentElement){if(t.hidden||t.getAttribute("aria-hidden")==="true")return!1;const r=window.getComputedStyle(t);if(r.display==="none"||r.visibility==="hidden"||r.opacity==="0")return!1}return!0}function fe(e){return e instanceof Error?{name:e.name,message:e.message,stack:e.stack}:{message:String(e)}}function S(e){return e.replace(/[&<>"]/g,t=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"})[t]??t)}function $t(e){return S(e).replace(/`/g,"&#96;")}function At(){if(typeof document>"u")return;const e=ze(),t=()=>{e.initializeContentScript().catch(r=>console.error("[CodeRED Shalom] Error de inicialización:",fe(r)))};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",t,{once:!0}):t()}return At(),_.createShalomContentController=ze,_.findSearchInsertionPoint=He,_.insertSearchContainer=Pe,_.positionResultsPanel=L,_.serializeSafeError=fe,Object.defineProperty(_,Symbol.toStringTag,{value:"Module"}),_})({});
