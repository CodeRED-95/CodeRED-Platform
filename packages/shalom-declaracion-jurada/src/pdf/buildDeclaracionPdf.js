// OBSOLETO — no lo usa la aplicación. Ver README, «Declaraciones juradas: una
// sola API, dos clientes».
//
// El documento oficial lo emite ahora CodeRED Platform
// (app/Services/Declarations/DeclarationPdfBuilder.php, FPDF), de modo que la
// app React y CodeRED Mobile descargan exactamente el mismo archivo en vez de
// tener cada cliente su propia plantilla. Ningún módulo importa ya esta
// función y no aparece en el bundle de producción.
//
// Se conserva a propósito, con sus pruebas: es el original del que se portó el
// generador del servidor —mismas coordenadas y métricas Helvetica— y sirve
// para comparar fidelidad. Se eliminará, junto con las dependencias
// jspdf/jspdf-autotable, cuando el PDF del servidor lleve una versión completa
// en producción.
//
// Generación del PDF de Declaración Jurada (traslado de bienes Shalom).
//
// Extraído de App.jsx a un módulo independiente para que la vista previa y
// la descarga final compartan exactamente el mismo documento — antes cada
// una tenía su propia implementación (jsPDF por coordenadas vs. una
// maqueta en HTML/Tailwind) y podían divergir. Ahora solo existe esta
// función: quien la llama decide si el resultado se guarda (`doc.save()`)
// o se muestra (`doc.output('bloburl')`).
const SHALOM_RED = [227, 24, 55] // #e31837 — mismo rojo que ya usa el resto de la app.

/**
 * Escribe texto justificado (espaciado entre palabras ajustado al ancho).
 * La ÚLTIMA línea de cada bloque NUNCA se justifica — se alinea a la
 * izquierda con su espaciado natural, como en cualquier documento
 * tipografiado normal. Justificar la última línea (o un bloque de una
 * sola línea) es lo que producía el efecto de palabras separadas por
 * huecos enormes en la versión anterior.
 */
const createTextWriters = doc => {
  const writeFullyJustifiedText = (text, x, y, width, lineHeightFactor = 1.2) => {
    const lines = doc.splitTextToSize(text, width)
    const lineHeight = doc.getFontSize() * 0.3528 * lineHeightFactor
    lines.forEach((line, index) => {
      const lineY = y + (index * lineHeight)
      const isLastLine = index === lines.length - 1
      const words = line.trim().split(/\s+/)
      if (isLastLine || words.length < 2) {
        doc.text(line.trim(), x, lineY)
        return
      }
      const wordsWidth = words.reduce((total, word) => total + doc.getTextWidth(word), 0)
      const wordGap = Math.max(0, (width - wordsWidth) / (words.length - 1))
      let cursorX = x
      words.forEach(word => {
        doc.text(word, cursorX, lineY)
        cursorX += doc.getTextWidth(word) + wordGap
      })
    })
    return lines
  }

  const writeFullyJustifiedSegments = (segments, x, y, width, lineHeightFactor = 1.2) => {
    const tokens = segments.flatMap(({ text, style }) =>
      text.trim().split(/\s+/).map(token => ({ text: token, style }))
    )
    const lines = []
    let currentLine = []
    let currentWidth = 0
    doc.setFont('helvetica', 'normal')
    const minimumSpace = doc.getTextWidth(' ')

    tokens.forEach(token => {
      doc.setFont('helvetica', token.style)
      const tokenWidth = doc.getTextWidth(token.text)
      const nextWidth = currentWidth + (currentLine.length ? minimumSpace : 0) + tokenWidth

      if (currentLine.length && nextWidth > width) {
        lines.push(currentLine)
        currentLine = []
        currentWidth = 0
      }

      currentLine.push({ ...token, width: tokenWidth })
      currentWidth += (currentLine.length > 1 ? minimumSpace : 0) + tokenWidth
    })

    if (currentLine.length) lines.push(currentLine)

    const lineHeight = doc.getFontSize() * 0.3528 * lineHeightFactor
    lines.forEach((line, lineIndex) => {
      const lineY = y + (lineIndex * lineHeight)
      const isLastLine = lineIndex === lines.length - 1
      if (isLastLine || line.length < 2) {
        let cursorX = x
        line.forEach(token => {
          doc.setFont('helvetica', token.style)
          doc.text(token.text, cursorX, lineY)
          cursorX += token.width + minimumSpace
        })
        return
      }
      const wordsWidth = line.reduce((total, token) => total + token.width, 0)
      const wordGap = Math.max(0, (width - wordsWidth) / (line.length - 1))
      let cursorX = x
      line.forEach(token => {
        doc.setFont('helvetica', token.style)
        doc.text(token.text, cursorX, lineY)
        cursorX += token.width + wordGap
      })
    })

    doc.setFont('helvetica', 'normal')
    return lines
  }

  return { writeFullyJustifiedText, writeFullyJustifiedSegments }
}

// Espacio fijo (mm) entre la etiqueta y el valor. No se mide con
// `getTextWidth(label + ' ')`: el ancho de un espacio final después de un
// carácter acentuado (p. ej. "Teléfono ") no se calcula de forma fiable en
// jsPDF/Helvetica y el valor terminaba pegado a la etiqueta sin separación
// visible ("con Teléfono987654321"). Un espacio fijo es robusto sin
// importar el contenido de la etiqueta.
const LABEL_VALUE_GAP_MM = 2.2

/**
 * Escribe una fila "etiqueta + valor" con línea inferior bajo el valor.
 * La línea se calcula a partir del ancho REAL de la etiqueta (medido con
 * getTextWidth), no de un desplazamiento fijo — así el campo siempre queda
 * alineado bajo el valor sin importar el tamaño de fuente. `lineEndX`
 * decide hasta dónde llega la línea (por defecto, justo al final del valor
 * medido con `minLineWidth` como ancho mínimo visible).
 */
const writeLabeledField = (doc, { label, value, x, y, minLineWidth = 0, lineEndX = null, valueMaxWidth = null, bold = false }) => {
  doc.setFont('helvetica', 'normal')
  doc.text(label, x, y)
  const labelWidth = doc.getTextWidth(label) + LABEL_VALUE_GAP_MM
  const valueX = x + labelWidth
  doc.setFont('helvetica', bold ? 'bold' : 'normal')
  if (valueMaxWidth) {
    doc.text(value || '', valueX, y, { maxWidth: valueMaxWidth })
  } else {
    doc.text(value || '', valueX, y)
  }
  const valueWidth = doc.getTextWidth(value || '')
  const computedEnd = lineEndX ?? (valueX + Math.max(valueWidth, minLineWidth))
  doc.line(valueX - 1, y + 1.4, computedEnd, y + 1.4)
  doc.setFont('helvetica', 'normal')
  return valueX
}

/**
 * @param {object} params
 * @param {object} params.form - Estado del formulario (remitente/destinatario/envío).
 * @param {Array<{cantidad:string,descripcion:string}>} params.documentItems - Filas de la tabla, ya con el mínimo de filas aplicado por quien llama.
 * @param {boolean} params.includeDniPhoto - Si true, usa formato horizontal con la foto del DNI en la mitad derecha.
 * @param {string|null} params.dniPhoto - Data URL de la foto (o null).
 * @returns {Promise<import('jspdf').jsPDF>} El documento ya construido — el caller decide `.save()` o `.output(...)`.
 */
export const buildDeclaracionPdf = async ({ form, documentItems, includeDniPhoto, dniPhoto }) => {
  const [{ jsPDF }, { default: autoTable }] = await Promise.all([
    import('jspdf'),
    import('jspdf-autotable')
  ])

  const isLandscape = includeDniPhoto
  const doc = new jsPDF({ orientation: isLandscape ? 'l' : 'p', unit: 'mm', format: 'a4' })

  // A4 real: 210×297mm en vertical. Márgenes dentro del rango 10–15mm
  // pedido; se mantienen angostos para que el contenido use prácticamente
  // todo el ancho útil de la hoja (el desperdicio que había era vertical,
  // no horizontal: se corrige con tamaños/espaciados más generosos más
  // abajo, no con márgenes menores).
  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  // El modo horizontal (con foto de DNI) tiene solo la mitad del ancho de
  // página como columna Y la mitad de la altura de una A4 vertical
  // (210mm vs 297mm) como presupuesto — por eso su factor de escala es
  // más chico que el del modo vertical, no solo proporcional al ancho.
  const layoutScale = isLandscape ? 0.73 : 1
  const margin = isLandscape ? 10 : 12
  const colWidth = isLandscape ? (pageWidth / 2) - (margin * 2) : pageWidth - (margin * 2)
  const titleCenterX = margin + (colWidth / 2)
  const bottomLimit = pageHeight - margin

  const { writeFullyJustifiedText, writeFullyJustifiedSegments } = createTextWriters(doc)

  // --- Encabezado institucional --------------------------------------
  // Banda de color con la marca (mismo rojo #e31837 que usa el resto de
  // la app) en vez del título suelto en negro que había antes — da
  // identidad visual sin depender de un archivo de logo (no existe
  // ninguno en el proyecto).
  const headerTop = isLandscape ? 6 : 8
  const headerHeight = 10 * layoutScale
  doc.setFillColor(...SHALOM_RED)
  doc.rect(margin, headerTop, colWidth, headerHeight, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(13 * layoutScale)
  doc.text('SHALOM', margin + 3, headerTop + (headerHeight / 2), { baseline: 'middle' })
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(7.5 * layoutScale)
  doc.text('DECLARACIÓN JURADA', margin + colWidth - 3, headerTop + (headerHeight / 2), { align: 'right', baseline: 'middle' })

  doc.setTextColor(0, 0, 0)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(11 * layoutScale)
  const title = 'DECLARACIÓN JURADA SIMPLE PARA TRASLADO DE BIENES - USO PERSONAL'
  const titleLines = doc.splitTextToSize(title, colWidth - 4)
  const titleY = headerTop + headerHeight + (6 * layoutScale)
  titleLines.forEach((line, index) => doc.text(line, titleCenterX, titleY + (index * 4.8 * layoutScale), { align: 'center' }))

  let currentY = titleY + (titleLines.length * 4.8 * layoutScale) + (4.5 * layoutScale)

  // --- Sección 1: Remitente -------------------------------------------
  doc.setFontSize(10.3 * layoutScale)
  doc.setFont('helvetica', 'normal')
  const introRemitente = 'Por el presente documento de carácter, de declaración jurada'
  writeFullyJustifiedText(introRemitente, margin, currentY, colWidth, 1.25)
  currentY += 7.5 * layoutScale

  // "YO" siempre al filo izquierdo; el nombre va en LA MISMA fila,
  // centrado respecto al ancho útil de la hoja (no del espacio restante
  // tras "YO"), y su línea inferior corre desde justo después de "YO"
  // hasta el margen derecho. La misma estructura se reutiliza para
  // "Señor(a):" más abajo, por consistencia visual.
  doc.setFont('helvetica', 'normal')
  doc.text('YO', margin, currentY)
  const yoWidth = doc.getTextWidth('YO')
  doc.setFont('helvetica', 'bold')
  const nombreRemitente = (form.remitenteNombre || '____________________________________________________').toUpperCase()
  doc.text(nombreRemitente, titleCenterX, currentY, { align: 'center', maxWidth: colWidth - yoWidth - 6 })
  doc.line(margin + yoWidth + 3, currentY + 1.4, margin + colWidth, currentY + 1.4)
  currentY += 7.5 * layoutScale

  doc.setFont('helvetica', 'normal')
  writeFullyJustifiedSegments([
    { text: 'identificado con documento de identificación ', style: 'normal' },
    { text: '(DNI, CARNET DE EXTRANJERÍA)', style: 'bold' },
    { text: ` ${form.remitenteDni || '____________________'}`, style: 'normal' }
  ], margin, currentY, colWidth, 1.2)
  currentY += 7.5 * layoutScale

  writeLabeledField(doc, {
    label: 'con Teléfono',
    value: form.remitenteTelefono,
    x: margin,
    y: currentY,
    minLineWidth: 34 * layoutScale
  })
  currentY += 14 * layoutScale

  // --- Sección 2: Destinatario ------------------------------------------
  doc.setFont('helvetica', 'bold')
  doc.text('DECLARO BAJO JURAMENTO', margin, currentY)
  currentY += 11 * layoutScale

  doc.setFont('helvetica', 'normal')
  const date = new Date()
  const formattedDate = date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })
  const destIntroLines = writeFullyJustifiedSegments([
    { text: `Fecha ${formattedDate} autorizo el traslado de mis bienes a través de la `, style: 'normal' },
    { text: 'EMPRESA DE TRANSPORTE SHALOM EMPRESARIAL S.A.C con RUC: 20512528458', style: 'bold' },
    { text: ', para el', style: 'normal' }
  ], margin, currentY, colWidth, 1.25)
  currentY += ((destIntroLines.length * 5.6) + 3) * layoutScale

  // "Señor(a):" al filo izquierdo — misma estructura que "YO" arriba —
  // con el nombre del destinatario centrado en la misma fila.
  doc.setFont('helvetica', 'normal')
  doc.text('Señor(a):', margin, currentY)
  const srPrefixWidth = doc.getTextWidth('Señor(a):')
  doc.setFont('helvetica', 'bold')
  const nombreDest = (form.destinatarioNombre || '____________________________________________________').toUpperCase()
  doc.text(nombreDest, titleCenterX, currentY, { align: 'center', maxWidth: colWidth - srPrefixWidth - 6 })
  doc.line(margin + srPrefixWidth + 3, currentY + 1.4, margin + colWidth, currentY + 1.4)
  currentY += 7.5 * layoutScale

  doc.setFontSize(10.3 * layoutScale)
  writeLabeledField(doc, {
    label: 'con DNI N°',
    value: form.destinatarioDni,
    x: margin,
    y: currentY,
    lineEndX: margin + (colWidth * 0.48)
  })
  const recipientMidX = margin + (colWidth * 0.54)
  writeLabeledField(doc, {
    label: 'con teléfono',
    value: form.destinatarioTelefono,
    x: recipientMidX,
    y: currentY,
    lineEndX: margin + colWidth
  })
  currentY += 7.5 * layoutScale

  writeLabeledField(doc, {
    label: 'y para la oficina de',
    value: (form.sedeDestino || '').toUpperCase(),
    x: margin,
    y: currentY,
    lineEndX: margin + colWidth,
    valueMaxWidth: colWidth - doc.getTextWidth('y para la oficina de') - LABEL_VALUE_GAP_MM,
    bold: true
  })
  currentY += 14 * layoutScale

  // --- Sección 3: Tabla de contenido -------------------------------------
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10.3 * layoutScale)
  doc.text('DECLARO ENVIAR LO SIGUIENTE:', margin, currentY - 2)

  autoTable(doc, {
    startY: currentY,
    margin: { left: margin, right: pageWidth - margin - colWidth },
    tableWidth: colWidth,
    head: [['CANT.', 'DESCRIPCIÓN DE LOS BIENES']],
    body: [
      ...documentItems.map(i => [i.cantidad, i.descripcion]),
      ['', `MOTIVO DEL ENVÍO: ${(form.motivoEnvio || '').toUpperCase()}`]
    ],
    theme: 'grid',
    headStyles: { fillColor: SHALOM_RED, textColor: [255, 255, 255], fontSize: 9.3 * layoutScale, fontStyle: 'bold', halign: 'center', cellPadding: 1.9 * layoutScale },
    styles: { fontSize: 8.8 * layoutScale, cellPadding: 1.6 * layoutScale, minCellHeight: 6.6 * layoutScale, textColor: [0, 0, 0], valign: 'middle', lineColor: [190, 190, 190], lineWidth: 0.2 },
    columnStyles: { 0: { cellWidth: 27 * layoutScale, halign: 'center' } },
    didParseCell: data => {
      if (data.section === 'body' && data.row.index === documentItems.length) data.cell.styles.fontStyle = 'bold'
    }
  })

  let finalY = doc.lastAutoTable.finalY + (9 * layoutScale)

  // --- Declaración jurada (texto legal) — sin cambios de contenido -------
  doc.setFontSize(9.3 * layoutScale)
  doc.setFont('helvetica', 'normal')
  const legalText = 'Así mismo, declaro bajo juramento que los presentes datos obedecen a la verdad, sometiéndome a las sanciones administrativas, civiles y penales que correspondan en caso de falsedad de los mismos, de acuerdo con lo regulado por la'
  const legalLines = writeFullyJustifiedText(legalText, margin, finalY, colWidth, 1.25)
  finalY += ((legalLines.length * 4.6) + 2) * layoutScale
  doc.setFont('helvetica', 'bold')
  doc.text('Ley N° 27444 - Ley del Procedimiento Administrativo General.', margin, finalY)
  finalY += 7 * layoutScale
  doc.setFont('helvetica', 'normal')
  const conformityText = 'Para mayor constancia y validez en cumplimiento de lo indicado, en señal de conformidad firmo esta declaración y coloco mi huella digital para los fines pertinentes.'
  const conformityLines = writeFullyJustifiedText(conformityText, margin, finalY, colWidth, 1.25)
  finalY += ((conformityLines.length * 4.6) + 11) * layoutScale

  // --- Firma y huella: mantenidas juntas, nunca cortadas entre páginas ---
  // Si lo que falta de página no alcanza para todo el bloque (fecha +
  // firma/nombres/documento + recuadro de huella), se abre una página
  // nueva ANTES de empezar a dibujarlo, en vez de dejar que una parte
  // quede recortada contra el borde inferior.
  const signatureBlockHeight = (8 + 20 + (2 * 8) + 11) * layoutScale
  if (finalY + signatureBlockHeight > bottomLimit) {
    doc.addPage()
    finalY = margin
  }
  let signatureY = finalY + (8 * layoutScale)

  doc.setFontSize(9.3 * layoutScale)
  doc.setFont('helvetica', 'normal')
  doc.line(margin + (colWidth * 0.4), signatureY, margin + (colWidth * 0.55), signatureY)
  doc.text(String(date.getDate()).padStart(2, '0'), margin + (colWidth * 0.475), signatureY - 1, { align: 'center' })
  doc.text('de', margin + (colWidth * 0.61), signatureY)
  doc.line(margin + (colWidth * 0.69), signatureY, margin + (colWidth * 0.83), signatureY)
  doc.text(date.toLocaleString('es-PE', { month: 'long' }), margin + (colWidth * 0.76), signatureY - 1, { align: 'center' })
  doc.text(`del ${date.getFullYear()}`, margin + (colWidth * 0.86), signatureY)

  // El recuadro de huella se calcula ANTES de las líneas de firma para que
  // "Nombres:"/"N° Documento:" sepan hasta dónde pueden extenderse sin
  // invadirlo (antes usaban un ancho fijo — un nombre muy largo como
  // "MARIA DE LOS ANGELES FERNANDEZ DE LA CRUZ MONTALVO" no cabía, se
  // envolvía a una segunda línea y quedaba encima de la línea inferior y
  // pegado a la fila siguiente).
  const huellaWidth = 34 * layoutScale
  const huellaHeight = 40 * layoutScale
  const huellaX = margin + colWidth - huellaWidth - (5 * layoutScale)
  const huellaY = signatureY + (8 * layoutScale)

  const signatureBaseFontSize = 9.3 * layoutScale
  const labelValueX = margin + (30 * layoutScale)
  const labelValueMaxWidth = huellaX - labelValueX - (6 * layoutScale)

  // Si el valor no cabe en una sola línea al tamaño normal, se reduce el
  // tamaño de fuente progresivamente (nunca se envuelve a una segunda
  // línea, que rompería el espaciado fijo entre filas de esta sección).
  const fitSingleLineFontSize = (text, maxWidth, baseFontSize, minFontSize = 6.5) => {
    doc.setFontSize(baseFontSize)
    let size = baseFontSize
    while (size > minFontSize && doc.getTextWidth(text) > maxWidth) {
      size -= 0.3
      doc.setFontSize(size)
    }
    return size
  }

  const labels = ['Firma:', 'Nombres:', 'N° Documento:']
  labels.forEach((label, index) => {
    const y = signatureY + ((20 + (index * 8)) * layoutScale)
    doc.setFontSize(signatureBaseFontSize)
    doc.text(label, margin, y)
    const value = ((index === 1 ? form.remitenteNombre : index === 2 ? form.remitenteDni : '') || '').toUpperCase()
    if (value) {
      fitSingleLineFontSize(value, labelValueMaxWidth, signatureBaseFontSize)
      doc.text(value, labelValueX, y - 1)
    }
    doc.setFontSize(signatureBaseFontSize)
    doc.line(labelValueX - 1, y + 1, labelValueX + labelValueMaxWidth, y + 1)
  })

  doc.rect(huellaX, huellaY, huellaWidth, huellaHeight)
  doc.setFontSize(6.5 * layoutScale)
  doc.setFont('helvetica', 'italic')
  doc.text('Huella Digital', huellaX + (huellaWidth / 2), huellaY + huellaHeight + (4 * layoutScale), { align: 'center' })
  doc.setFont('helvetica', 'normal')

  // --- Foto del DNI (formato horizontal) ---------------------------------
  if (includeDniPhoto && dniPhoto) {
    try {
      const imgProps = doc.getImageProperties(dniPhoto)
      const photoAreaX = pageWidth / 2 + 8
      const maxImgW = pageWidth / 2 - 16
      const maxImgH = pageHeight - 24
      const scale = Math.min(maxImgW / imgProps.width, maxImgH / imgProps.height)
      const imgW = imgProps.width * scale
      const imgH = imgProps.height * scale
      const imgX = photoAreaX + ((maxImgW - imgW) / 2)
      const imgY = (pageHeight - imgH) / 2
      doc.addImage(dniPhoto, imgProps.fileType, imgX, imgY, imgW, imgH)
    } catch (error) {
      console.error('Error al añadir la foto del DNI al PDF:', error)
    }
  }

  // --- Pie de página: fecha de generación + numeración -------------------
  // Discreto (gris, tamaño pequeño), no compite con el documento. Datos
  // reales (fecha/hora de generación, número de página) — nada inventado.
  const pageCount = doc.internal.getNumberOfPages()
  const generatedAt = date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
    doc.setPage(pageNumber)
    doc.setFontSize(6.5)
    doc.setTextColor(140, 140, 140)
    doc.setFont('helvetica', 'normal')
    doc.text(`Generado el ${generatedAt} · Declaración Jurada Shalom`, margin, pageHeight - 5)
    doc.text(`Página ${pageNumber} de ${pageCount}`, pageWidth - margin, pageHeight - 5, { align: 'right' })
    doc.setTextColor(0, 0, 0)
  }

  return doc
}
