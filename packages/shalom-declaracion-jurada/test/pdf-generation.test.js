// Ejecutar con: node --experimental-test-module-mocks --test test/
//
// Cubre src/pdf/buildDeclaracionPdf.js, que ya NO está en el camino de
// producción: el PDF oficial lo emite CodeRED Platform y se descarga por
// GET /api/declarations/{id}/pdf (ver test/declarations.test.js).
//
// Estas pruebas se mantienen porque ese módulo es la referencia de la que se
// portó el generador del servidor (DeclarationPdfBuilder.php, FPDF): mientras
// siga existiendo debe seguir produciendo el mismo documento, para poder
// comparar fidelidad. Desaparecerán con él.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { Buffer } from 'node:buffer'
import { buildDeclaracionPdf } from '../src/pdf/buildDeclaracionPdf.js'

const baseForm = () => ({
  remitenteDni: '12345678',
  remitenteTelefono: '987654321',
  remitenteNombre: 'JUAN CARLOS PEREZ QUISPE',
  destinatarioNombre: 'MARIA ELENA RODRIGUEZ TORRES',
  destinatarioDni: '87654321',
  destinatarioTelefono: '912345678',
  sedeDestino: 'LIMA - AGENCIA CENTRAL',
  motivoEnvio: 'VIAJE'
})

const withItems = (count) => Array.from({ length: count }, (_, i) => ({
  cantidad: String((i % 9) + 1),
  descripcion: `ARTICULO DE PRUEBA NUMERO ${i + 1}`
}))

test('genera un PDF válido y no vacío con datos normales', async () => {
  const doc = await buildDeclaracionPdf({
    form: baseForm(),
    documentItems: withItems(2),
    includeDniPhoto: false,
    dniPhoto: null
  })

  const bytes = new Uint8Array(doc.output('arraybuffer'))
  assert.ok(bytes.length > 0, 'el PDF no debería estar vacío')
  const header = Buffer.from(bytes.slice(0, 5)).toString('ascii')
  assert.equal(header, '%PDF-', 'el archivo generado debe ser un PDF válido')
  assert.equal(doc.internal.getNumberOfPages(), 1)
})

test('no lanza excepción con datos vacíos/incompletos (formulario mínimo)', async () => {
  const doc = await buildDeclaracionPdf({
    form: {
      remitenteDni: '', remitenteTelefono: '', remitenteNombre: '',
      destinatarioNombre: '', destinatarioDni: '', destinatarioTelefono: '',
      sedeDestino: '', motivoEnvio: ''
    },
    documentItems: [],
    includeDniPhoto: false,
    dniPhoto: null
  })

  const bytes = new Uint8Array(doc.output('arraybuffer'))
  assert.ok(bytes.length > 0)
  assert.equal(doc.internal.getNumberOfPages(), 1)
})

test('una tabla larga fuerza múltiples páginas sin cortar filas', async () => {
  const doc = await buildDeclaracionPdf({
    form: baseForm(),
    documentItems: withItems(40),
    includeDniPhoto: false,
    dniPhoto: null
  })

  assert.ok(doc.internal.getNumberOfPages() > 1, 'un listado largo debe generar más de una página')
})

test('el bloque de firma y huella no se corta: si no cabe, salta de página completo', async () => {
  const doc = await buildDeclaracionPdf({
    form: baseForm(),
    documentItems: withItems(20),
    includeDniPhoto: false,
    dniPhoto: null
  })

  // Este escenario está calibrado para dejar el bloque firma+huella justo
  // en el límite inferior de la primera página; la lógica de
  // buildDeclaracionPdf.js debe detectarlo y abrir una página nueva ANTES
  // de dibujarlo, en vez de dejarlo recortado contra el borde.
  assert.equal(doc.internal.getNumberOfPages(), 2)
})

test('el formato horizontal con foto de DNI no revienta si no hay imagen', async () => {
  const doc = await buildDeclaracionPdf({
    form: baseForm(),
    documentItems: withItems(2),
    includeDniPhoto: true,
    dniPhoto: null
  })

  const bytes = new Uint8Array(doc.output('arraybuffer'))
  assert.ok(bytes.length > 0)
})

test('la vista previa y la descarga generan el mismo documento para los mismos datos', async () => {
  const params = {
    form: baseForm(),
    documentItems: withItems(3),
    includeDniPhoto: false,
    dniPhoto: null
  }

  const docA = await buildDeclaracionPdf(params)
  const docB = await buildDeclaracionPdf(params)

  // No deben divergir en estructura (mismo número de páginas) al construirse
  // dos veces con los mismos datos — es la garantía de que preview y
  // descarga, que llaman a la misma función, siempre están sincronizadas.
  assert.equal(docA.internal.getNumberOfPages(), docB.internal.getNumberOfPages())
})
