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

  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const layoutScale = isLandscape ? 0.84 : 1
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
  const headerHeight = 9 * layoutScale
  doc.setFillColor(...SHALOM_RED)
  doc.rect(margin, headerTop, colWidth, headerHeight, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(12 * layoutScale)
  doc.text('SHALOM', margin + 3, headerTop + (headerHeight / 2), { baseline: 'middle' })
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(7 * layoutScale)
  doc.text('DECLARACIÓN JURADA', margin + colWidth - 3, headerTop + (headerHeight / 2), { align: 'right', baseline: 'middle' })

  doc.setTextColor(0, 0, 0)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10 * layoutScale)
  const title = 'DECLARACIÓN JURADA SIMPLE PARA TRASLADO DE BIENES - USO PERSONAL'
  const titleLines = doc.splitTextToSize(title, colWidth - 4)
  const titleY = headerTop + headerHeight + (5 * layoutScale)
  titleLines.forEach((line, index) => doc.text(line, titleCenterX, titleY + (index * 4.2 * layoutScale), { align: 'center' }))

  let currentY = titleY + (titleLines.length * 4.2 * layoutScale) + (3.5 * layoutScale)

  // --- Sección 1: Remitente -------------------------------------------
  doc.setFontSize(9.5 * layoutScale)
  doc.setFont('helvetica', 'normal')
  const introRemitente = 'Por el presente documento de carácter, de declaración jurada YO'
  writeFullyJustifiedText(introRemitente, margin, currentY, colWidth)
  currentY += 6 * layoutScale

  doc.setFont('helvetica', 'bold')
  const nombreRemitente = (form.remitenteNombre || '____________________________________________________').toUpperCase()
  doc.text(nombreRemitente, titleCenterX, currentY, { align: 'center', maxWidth: colWidth })
  doc.line(margin, currentY + 1, margin + colWidth, currentY + 1)
  currentY += 6 * layoutScale

  writeFullyJustifiedSegments([
    { text: 'identificado con documento de identificación ', style: 'normal' },
    { text: '(DNI, CARNET DE EXTRANJERÍA)', style: 'bold' },
    { text: ` ${form.remitenteDni || '____________________'}`, style: 'normal' }
  ], margin, currentY, colWidth, 1.1)
  currentY += 6 * layoutScale
  doc.setFont('helvetica', 'normal')
  doc.text('con Teléfono', margin, currentY)
  doc.text(form.remitenteTelefono || '', margin + 28, currentY)
  doc.line(margin + 27, currentY + 1, margin + 78, currentY + 1)
  currentY += 11 * layoutScale

  // --- Sección 2: Destinatario ------------------------------------------
  doc.setFont('helvetica', 'bold')
  doc.text('DECLARO BAJO JURAMENTO', margin, currentY)
  currentY += 9 * layoutScale

  doc.setFont('helvetica', 'normal')
  const date = new Date()
  const formattedDate = date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })
  const destIntroLines = writeFullyJustifiedSegments([
    { text: `Fecha ${formattedDate} autorizo el traslado de mis bienes a través de la `, style: 'normal' },
    { text: 'EMPRESA DE TRANSPORTE SHALOM EMPRESARIAL S.A.C con RUC: 20512528458', style: 'bold' },
    { text: ', para el', style: 'normal' }
  ], margin, currentY, colWidth, 1.15)
  currentY += ((destIntroLines.length * 5) + 2) * layoutScale

  doc.setFont('helvetica', 'bold')
  const nombreDest = (form.destinatarioNombre || '____________________________________________________').toUpperCase()
  const srTexto = `Señor(a): ${nombreDest}`
  doc.text(srTexto, margin, currentY)
  const srPrefixWidth = doc.getTextWidth('Señor(a): ')
  const destNameWidth = doc.getTextWidth(nombreDest)
  doc.line(margin + srPrefixWidth, currentY + 0.5, margin + srPrefixWidth + destNameWidth, currentY + 0.5)
  currentY += 6 * layoutScale

  doc.setFont('helvetica', 'normal')
  doc.text('con DNI N°', margin, currentY)
  doc.text(form.destinatarioDni || '', margin + 28, currentY)
  doc.line(margin + 26, currentY + 1, margin + (colWidth * 0.5), currentY + 1)
  const recipientMidX = margin + (colWidth * 0.55)
  doc.text('con teléfono', recipientMidX, currentY)
  doc.text(form.destinatarioTelefono || '', recipientMidX + (28 * layoutScale), currentY)
  doc.line(recipientMidX + (26 * layoutScale), currentY + 1, margin + colWidth, currentY + 1)
  currentY += 6 * layoutScale
  doc.text('y para la oficina de', margin, currentY)
  doc.setFont('helvetica', 'bold')
  doc.text((form.sedeDestino || '').toUpperCase(), margin + 42, currentY, { maxWidth: colWidth - 42 })
  doc.line(margin + 40, currentY + 1, margin + colWidth, currentY + 1)
  currentY += 11 * layoutScale

  // --- Sección 3: Tabla de contenido -------------------------------------
  doc.setFont('helvetica', 'bold')
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
    headStyles: { fillColor: SHALOM_RED, textColor: [255, 255, 255], fontSize: 8.5 * layoutScale, fontStyle: 'bold', halign: 'center', cellPadding: 1.4 * layoutScale },
    styles: { fontSize: 8 * layoutScale, cellPadding: layoutScale, minCellHeight: 5.2 * layoutScale, textColor: [0, 0, 0], valign: 'middle', lineColor: [190, 190, 190], lineWidth: 0.2 },
    columnStyles: { 0: { cellWidth: 27 * layoutScale, halign: 'center' } },
    didParseCell: data => {
      if (data.section === 'body' && data.row.index === documentItems.length) data.cell.styles.fontStyle = 'bold'
    }
  })

  let finalY = doc.lastAutoTable.finalY + (7 * layoutScale)

  // --- Declaración jurada (texto legal) — sin cambios de contenido -------
  doc.setFontSize(8.5 * layoutScale)
  doc.setFont('helvetica', 'normal')
  const legalText = 'Así mismo, declaro bajo juramento que los presentes datos obedecen a la verdad, sometiéndome a las sanciones administrativas, civiles y penales que correspondan en caso de falsedad de los mismos, de acuerdo con lo regulado por la'
  const legalLines = writeFullyJustifiedText(legalText, margin, finalY, colWidth, 1.15)
  finalY += ((legalLines.length * 4) + 1) * layoutScale
  doc.setFont('helvetica', 'bold')
  doc.text('Ley N° 27444 - Ley del Procedimiento Administrativo General.', margin, finalY)
  finalY += 6 * layoutScale
  doc.setFont('helvetica', 'normal')
  const conformityText = 'Para mayor constancia y validez en cumplimiento de lo indicado, en señal de conformidad firmo esta declaración y coloco mi huella digital para los fines pertinentes.'
  const conformityLines = writeFullyJustifiedText(conformityText, margin, finalY, colWidth, 1.15)
  finalY += ((conformityLines.length * 4) + 8) * layoutScale

  // --- Firma y huella: mantenidas juntas, nunca cortadas entre páginas ---
  // Si lo que falta de página no alcanza para todo el bloque (fecha +
  // firma/nombres/documento + recuadro de huella), se abre una página
  // nueva ANTES de empezar a dibujarlo, en vez de dejar que una parte
  // quede recortada contra el borde inferior.
  const signatureBlockHeight = (7 + 18 + (2 * 7) + 8) * layoutScale
  if (finalY + signatureBlockHeight > bottomLimit) {
    doc.addPage()
    finalY = margin
  }
  let signatureY = finalY + (7 * layoutScale)

  doc.setFontSize(8.5 * layoutScale)
  doc.setFont('helvetica', 'normal')
  doc.line(margin + (colWidth * 0.4), signatureY, margin + (colWidth * 0.55), signatureY)
  doc.text(String(date.getDate()).padStart(2, '0'), margin + (colWidth * 0.475), signatureY - 1, { align: 'center' })
  doc.text('de', margin + (colWidth * 0.61), signatureY)
  doc.line(margin + (colWidth * 0.69), signatureY, margin + (colWidth * 0.83), signatureY)
  doc.text(date.toLocaleString('es-PE', { month: 'long' }), margin + (colWidth * 0.76), signatureY - 1, { align: 'center' })
  doc.text(`del ${date.getFullYear()}`, margin + (colWidth * 0.86), signatureY)

  const labels = ['Firma:', 'Nombres:', 'N° Documento:']
  labels.forEach((label, index) => {
    const y = signatureY + ((18 + (index * 7)) * layoutScale)
    doc.text(label, margin, y)
    const value = index === 1 ? form.remitenteNombre : index === 2 ? form.remitenteDni : ''
    doc.text((value || '').toUpperCase(), margin + (28 * layoutScale), y - 1, { maxWidth: colWidth * 0.5 })
    doc.line(margin + (27 * layoutScale), y + 1, margin + (colWidth * 0.6), y + 1)
  })

  const huellaWidth = 30 * layoutScale
  const huellaHeight = 35 * layoutScale
  const huellaX = margin + colWidth - huellaWidth - (5 * layoutScale)
  const huellaY = signatureY + (7 * layoutScale)
  doc.rect(huellaX, huellaY, huellaWidth, huellaHeight)
  doc.setFontSize(6.5 * layoutScale)
  doc.setFont('helvetica', 'italic')
  doc.text('Huella Digital', huellaX + (huellaWidth / 2), huellaY + huellaHeight + (3.5 * layoutScale), { align: 'center' })
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
