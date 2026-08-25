import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { JSDOM } from 'jsdom';
import { createServiceOrderLockController } from '../src/content/service-order-lock';
import { DEFAULT_BLOCK_RULE_SET } from '../src/shared/block-rules';

/**
 * El overlay se inyecta en paginas de terceros con sus propias hojas de
 * estilo. Estas pruebas fijan el aislamiento: si alguien vuelve a montarlo en
 * el DOM de la pagina, la CSS del sitio puede descolocarlo, que es justo lo
 * que paso con el icono del candado en sysprovincia2.
 */
describe('overlay de bloqueo', () => {
  let dom: JSDOM;
  let controller: ReturnType<typeof createServiceOrderLockController> | null = null;

  beforeEach(() => {
    // Lunes 24/08/2026 22:47 Lima: fuera del horario permitido por defecto.
    dom = new JSDOM('<!doctype html><html><head><style>svg { position: absolute; top: 0; }</style></head><body></body></html>', {
      url: 'https://sysnewos.shalomcontrol.com/service-order',
    });

    const globals = globalThis as Record<string, unknown>;
    globals.window = dom.window;
    globals.document = dom.window.document;
    globals.MutationObserver = dom.window.MutationObserver;
    globals.HTMLElement = dom.window.HTMLElement;
    globals.history = dom.window.history;
  });

  afterEach(() => {
    controller?.destroy();
    controller = null;
    const globals = globalThis as Record<string, unknown>;
    delete globals.window;
    delete globals.document;
    delete globals.MutationObserver;
    delete globals.HTMLElement;
    delete globals.history;
  });

  async function mountLocked() {
    controller = createServiceOrderLockController({
      getManualLock: async () => false,
      setManualLock: async () => undefined,
      getRuleSet: async () => DEFAULT_BLOCK_RULE_SET,
    });

    await controller.initialize();
    return dom.window.document.getElementById('codered-service-order-lock-overlay');
  }

  it('monta la tarjeta dentro de un shadow root, fuera del alcance de la pagina', async () => {
    const overlay = await mountLocked();

    expect(overlay).not.toBeNull();
    expect(overlay?.shadowRoot).not.toBeNull();
    // Nada del contenido queda expuesto en el DOM de la pagina.
    expect(overlay?.querySelector('.codered-service-order-lock-card')).toBeNull();
    expect(overlay?.shadowRoot?.querySelector('.codered-service-order-lock-card')).not.toBeNull();
  });

  it('mantiene el icono dentro de su contenedor', async () => {
    const overlay = await mountLocked();
    const icon = overlay?.shadowRoot?.querySelector('.codered-service-order-lock-icon');

    expect(icon?.querySelector('svg')).not.toBeNull();
  });

  it('no deja hojas de estilo sueltas en el head de la pagina', async () => {
    await mountLocked();

    expect(dom.window.document.getElementById('codered-service-order-lock-styles')).toBeNull();
  });
});
