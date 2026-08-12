import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { 
  Trash2, 
  Download, 
  Search, 
  User, 
  MapPin, 
  Package, 
  ShieldCheck,
  X,
  Sun,
  Moon,
  Image as ImageIcon,
  Layout,
  ZoomIn,
  ZoomOut,
  RotateCcw,
  LogOut,
  Settings,
  CircleHelp
} from 'lucide-react';
import AuthScreen from './AuthScreen.jsx';
import AccountPanel from './AccountPanel.jsx';

const createInitialForm = () => ({
  remitenteDni: '',
  remitenteNombre: '',
  remitenteTelefono: '',
  destinatarioNombre: '',
  destinatarioDni: '',
  destinatarioTelefono: '',
  sedeDestino: '',
  motivoEnvio: '',
  items: Array.from({ length: 3 }, () => ({ cantidad: '', descripcion: '' }))
});

const DniLookupStatus = ({ lookup }) => {
  if (lookup.status === 'idle') return null;

  const color = lookup.status === 'error'
    ? 'text-red-600 dark:text-red-400'
    : lookup.status === 'success'
      ? 'text-emerald-600 dark:text-emerald-400'
      : 'text-slate-500 dark:text-slate-400';

  return (
    <p className={`text-[10px] flex items-center gap-1.5 ${color}`} role="status" aria-live="polite">
      {lookup.status === 'loading' && <span className="size-3 rounded-full border-2 border-current border-t-transparent animate-spin" />}
      {lookup.message}
    </p>
  );
};

const DniLookupToggle = ({ enabled, onChange, label }) => (
  <div className="flex items-center gap-1.5 normal-case">
    <button type="button" role="switch" aria-checked={enabled} aria-label={`Consulta automática para ${label}`} onClick={() => onChange(!enabled)} className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${enabled ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`}>
      <span className={`absolute left-0.5 top-0.5 size-4 rounded-full bg-white shadow transition-transform ${enabled ? 'translate-x-4' : 'translate-x-0'}`}/>
    </button>
    <span className="relative flex items-center normal-case group">
      <CircleHelp size={14} className="text-slate-400"/>
      <span className="pointer-events-none absolute bottom-full right-0 z-30 mb-2 hidden w-64 rounded-lg bg-slate-950 p-2 text-[10px] font-medium leading-tight text-white shadow-lg group-hover:block">Si está activo, al completar un DNI de 8 dígitos se consultará el nombre y se gastará 1 punto. El carnet de extranjería de 9 dígitos se completa manualmente y no consume puntos.</span>
    </span>
  </div>
);

const App = () => {
  const [currentUser, setCurrentUser] = useState(null);
  const [sessionLoading, setSessionLoading] = useState(true);
  const [isAccountOpen, setIsAccountOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [agenciasFetchError, setAgenciasFetchError] = useState(null);
  const [agenciasLoading, setAgenciasLoading] = useState(true);
  const [darkMode, setDarkMode] = useState(true);
  const [includeDniPhoto, setIncludeDniPhoto] = useState(false);
  const [dniPhoto, setDniPhoto] = useState(null);
  const [agencias, setAgencias] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [previewZoom, setPreviewZoom] = useState(1);
  const [isPreviewDragging, setIsPreviewDragging] = useState(false);
  const [dniLookupStatus, setDniLookupStatus] = useState({
    remitente: { status: 'idle', message: '' },
    destinatario: { status: 'idle', message: '' }
  });
  const [dniLookupEnabled, setDniLookupEnabled] = useState({ remitente: false, destinatario: false });
  const previewViewportRef = useRef(null);
  const previewDragRef = useRef(null);
  const [form, setForm] = useState(createInitialForm);
  const isLandscape = includeDniPhoto;
  const documentItems = useMemo(() => Array.from(
    { length: Math.max(10, form.items.length) },
    (_, index) => form.items[index] || { cantidad: '', descripcion: '' }
  ), [form.items]);

  // Sincronizar el tema oscuro con el elemento raíz (html)
  useEffect(() => {
    if (darkMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [darkMode]);

  // Cargar lista de agencias desde el Gist
  useEffect(() => {
    const controller = new AbortController();

    const fetchAgencias = async () => {
      try {
        setAgenciasLoading(true);
        const response = await fetch('https://gist.githubusercontent.com/CodeRED-95/acfb5aaccf90743075a8143511b48ae7/raw/agencias_terrestre.json', {
          signal: controller.signal
        });
        if (!response.ok) {
          throw new Error(`Error HTTP ${response.status}`);
        }
        const data = await response.json();
        const normalizedAgencias = Array.isArray(data)
          ? data.map(agencia => typeof agencia === 'string' ? { agencia } : agencia)
              .filter(agencia => agencia && typeof agencia === 'object')
          : [];
        setAgencias(normalizedAgencias);
        setAgenciasFetchError(null);
      } catch (error) {
        if (error.name === 'AbortError') return;
        console.error("Error cargando agencias:", error);
        setAgenciasFetchError("No se pudieron cargar las agencias. Verifica tu conexión e inténtalo nuevamente.");
        setAgencias([]);
      } finally {
        if (!controller.signal.aborted) setAgenciasLoading(false);
      }
    };
    fetchAgencias();

    return () => controller.abort();
  }, []);

  useEffect(() => {
    fetch('/api/auth/session')
      .then(response => response.json())
      .then(payload => setCurrentUser(payload.user))
      .catch(() => setCurrentUser(null))
      .finally(() => setSessionLoading(false));
  }, []);

  const scheduleDniLookup = useCallback((dni, dniField, nameField, statusKey, enabled) => {
    if (!enabled || dni.length !== 8) {
      setDniLookupStatus(prev => prev[statusKey].status === 'idle'
        ? prev
        : { ...prev, [statusKey]: { status: 'idle', message: '' } }
      );
      return undefined;
    }

    const controller = new AbortController();
    const timeout = window.setTimeout(async () => {
      setDniLookupStatus(prev => ({
        ...prev,
        [statusKey]: { status: 'loading', message: 'Consultando DNI...' }
      }));

      try {
        const response = await fetch(`/api/dni/${dni}`, { signal: controller.signal });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'No se encontró el DNI.');

        setForm(prev => prev[dniField] === dni
          ? { ...prev, [nameField]: payload.nombreCompleto.toUpperCase() }
          : prev
        );
        setCurrentUser(prev => prev ? {
          ...prev,
          credits: payload.credits,
          queriesUsed: payload.queriesUsed,
          creditsExpiresAt: payload.creditsExpiresAt,
          creditBatches: payload.creditBatches
        } : prev);
        setDniLookupStatus(prev => ({
          ...prev,
          [statusKey]: { status: 'success', message: 'Nombre completado automáticamente.' }
        }));
      } catch (error) {
        if (error.name === 'AbortError') return;
        setDniLookupStatus(prev => ({
          ...prev,
          [statusKey]: { status: 'error', message: error.message }
        }));
      }
    }, 450);

    return () => {
      window.clearTimeout(timeout);
      controller.abort();
    };
  }, []);

  useEffect(() => scheduleDniLookup(
    form.remitenteDni,
    'remitenteDni',
    'remitenteNombre',
    'remitente',
    dniLookupEnabled.remitente
  ), [dniLookupEnabled.remitente, form.remitenteDni, scheduleDniLookup]);

  useEffect(() => scheduleDniLookup(
    form.destinatarioDni,
    'destinatarioDni',
    'destinatarioNombre',
    'destinatario',
    dniLookupEnabled.destinatario
  ), [dniLookupEnabled.destinatario, form.destinatarioDni, scheduleDniLookup]);

  const resetForm = useCallback(() => {
    if (window.confirm("¿Deseas limpiar todos los campos del formulario?")) {
      setForm(createInitialForm());
      setDniPhoto(null);
      setSearchTerm('');
    }
  }, []);

  const handleNumericInput = useCallback((field, value, maxLength) => {
    const onlyNums = value.replace(/\D/g, '');
    if (onlyNums.length <= maxLength) {
      setForm(prev => ({ ...prev, [field]: onlyNums }));
    }
  }, []);

  const handlePhotoUpload = useCallback((e) => {
    const file = e.target.files[0];
    if (!file) return;
    const supportedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!supportedTypes.includes(file.type)) {
      window.alert('Selecciona una imagen JPG, PNG o WebP.');
      e.target.value = '';
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      window.alert('La imagen no debe superar los 10 MB.');
      e.target.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = () => setDniPhoto(reader.result);
    reader.onerror = () => window.alert('No se pudo leer la imagen seleccionada.');
    reader.readAsDataURL(file);
  }, []);

  const handleItemChange = useCallback((index, field, value) => {
    const normalizedValue = field === 'cantidad'
      ? value.replace(/\D/g, '').slice(0, 3)
      : value.toUpperCase();
    setForm(prev => ({
      ...prev,
      items: prev.items.map((item, itemIndex) =>
        itemIndex === index ? { ...item, [field]: normalizedValue } : item
      )
    }));
  }, []);

  const filteredAgencias = useMemo(() => {
    return agencias.filter(agencia => {
    if (!agencia) return false;
    const searchData = [
      agencia.agencia,
      agencia.departamento,
      agencia.provincia,
      agencia.distrito,
      agencia.direccion,
    ].filter(Boolean).join(' ').toLowerCase(); // Unir todos los campos relevantes para la búsqueda
    return searchData.includes(searchTerm.toLowerCase());
  });
  }, [agencias, searchTerm]);

  const addItem = useCallback(() => {
    setForm(prev => prev.items.length >= 10
      ? prev
      : { ...prev, items: [...prev.items, { cantidad: '', descripcion: '' }] }
    );
  }, []);

  const removeItem = useCallback((index) => {
    if (form.items.length > 1) {
      setForm(prev => ({ ...prev, items: prev.items.filter((_, i) => i !== index) }));
    }
  }, [form.items.length]);

  const handleDniToggle = useCallback((val) => {
    setIncludeDniPhoto(val);
  }, []);

  const handlePreviewPointerDown = useCallback((event) => {
    if (event.button !== 0) return;
    const viewport = previewViewportRef.current;
    if (!viewport) return;

    event.preventDefault();
    previewDragRef.current = {
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      scrollLeft: viewport.scrollLeft,
      scrollTop: viewport.scrollTop
    };
    viewport.setPointerCapture(event.pointerId);
    setIsPreviewDragging(true);
  }, []);

  const handlePreviewPointerMove = useCallback((event) => {
    const viewport = previewViewportRef.current;
    const drag = previewDragRef.current;
    if (!viewport || !drag || drag.pointerId !== event.pointerId) return;

    viewport.scrollLeft = drag.scrollLeft - (event.clientX - drag.startX);
    viewport.scrollTop = drag.scrollTop - (event.clientY - drag.startY);
  }, []);

  const stopPreviewDragging = useCallback((event) => {
    const viewport = previewViewportRef.current;
    const drag = previewDragRef.current;
    if (!drag || drag.pointerId !== event.pointerId) return;

    if (viewport?.hasPointerCapture(event.pointerId)) {
      viewport.releasePointerCapture(event.pointerId);
    }
    previewDragRef.current = null;
    setIsPreviewDragging(false);
  }, []);

  const logout = useCallback(async () => {
    await fetch('/api/auth/logout', { method: 'POST' });
    setCurrentUser(null);
    setIsAccountOpen(false);
  }, []);

  const generarPDF = useCallback(async () => {
    setLoading(true);
    try {
      const [{ jsPDF }, { default: autoTable }] = await Promise.all([
        import('jspdf'),
        import('jspdf-autotable')
      ]);
      const doc = new jsPDF({
        orientation: isLandscape ? 'l' : 'p',
        unit: 'mm',
        format: 'a4'
      });

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const layoutScale = isLandscape ? 0.84 : 1;
      const margin = isLandscape ? 10 : 12;

      // Calcular anchos basados en si es horizontal y si lleva foto
      const colWidth = isLandscape ? (pageWidth / 2) - (margin * 2) : pageWidth - (margin * 2);

      const writeFullyJustifiedText = (text, x, y, width, lineHeightFactor = 1.2) => {
        const lines = doc.splitTextToSize(text, width);
        const lineHeight = doc.getFontSize() * 0.3528 * lineHeightFactor;

        lines.forEach((line, index) => {
          const words = line.trim().split(/\s+/);
          const wordsWidth = words.reduce((total, word) => total + doc.getTextWidth(word), 0);
          const wordGap = words.length > 1 ? Math.max(0, (width - wordsWidth) / (words.length - 1)) : 0;
          let cursorX = x;

          words.forEach(word => {
            doc.text(word, cursorX, y + (index * lineHeight));
            cursorX += doc.getTextWidth(word) + wordGap;
          });
        });

        return lines;
      };

      const writeFullyJustifiedSegments = (segments, x, y, width, lineHeightFactor = 1.2) => {
        const tokens = segments.flatMap(({ text, style }) =>
          text.trim().split(/\s+/).map(token => ({ text: token, style }))
        );
        const lines = [];
        let currentLine = [];
        let currentWidth = 0;
        doc.setFont('helvetica', 'normal');
        const minimumSpace = doc.getTextWidth(' ');

        tokens.forEach(token => {
          doc.setFont('helvetica', token.style);
          const tokenWidth = doc.getTextWidth(token.text);
          const nextWidth = currentWidth + (currentLine.length ? minimumSpace : 0) + tokenWidth;

          if (currentLine.length && nextWidth > width) {
            lines.push(currentLine);
            currentLine = [];
            currentWidth = 0;
          }

          currentLine.push({ ...token, width: tokenWidth });
          currentWidth += (currentLine.length > 1 ? minimumSpace : 0) + tokenWidth;
        });

        if (currentLine.length) lines.push(currentLine);

        const lineHeight = doc.getFontSize() * 0.3528 * lineHeightFactor;
        lines.forEach((line, lineIndex) => {
          const wordsWidth = line.reduce((total, token) => total + token.width, 0);
          const wordGap = line.length > 1
            ? Math.max(0, (width - wordsWidth) / (line.length - 1))
            : 0;
          let cursorX = x;

          line.forEach(token => {
            doc.setFont('helvetica', token.style);
            doc.text(token.text, cursorX, y + (lineIndex * lineHeight));
            cursorX += token.width + wordGap;
          });
        });

        doc.setFont('helvetica', 'normal');
        return lines;
      };

      doc.setTextColor(0, 0, 0);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(10.5 * layoutScale);
      const title = 'DECLARACIÓN JURADA SIMPLE PARA TRASLADO DE BIENES - USO PERSONAL';
      const titleCenterX = margin + (colWidth / 2);
      const titleY = isLandscape ? 11 : 13;
      doc.text(title, titleCenterX, titleY, { align: 'center' });
      doc.line(titleCenterX - (doc.getTextWidth(title) / 2), titleY + 0.7, titleCenterX + (doc.getTextWidth(title) / 2), titleY + 0.7);

      let currentY = isLandscape ? 16 : 20;

      // Sección 1: Remitente
      doc.setFontSize(9.5 * layoutScale);
      doc.setFont("helvetica", "normal");
      const introRemitente = "Por el presente documento de carácter, de declaración jurada YO";
      writeFullyJustifiedText(introRemitente, margin, currentY, colWidth);
      currentY += 6 * layoutScale;

      doc.setFont("helvetica", "bold");
      const nombreRemitente = (form.remitenteNombre || '____________________________________________________').toUpperCase();
      doc.text(nombreRemitente, titleCenterX, currentY, { align: 'center', maxWidth: colWidth });
      doc.line(margin, currentY + 1, margin + colWidth, currentY + 1);
      currentY += 6 * layoutScale;

      writeFullyJustifiedSegments([
        { text: 'identificado con documento de identificación ', style: 'normal' },
        { text: '(DNI, CARNET DE EXTRANJERÍA)', style: 'bold' },
        { text: ` ${form.remitenteDni || '____________________'}`, style: 'normal' }
      ], margin, currentY, colWidth, 1.1);
      currentY += 6 * layoutScale;
      doc.setFont('helvetica', 'normal');
      doc.text('con Teléfono', margin, currentY);
      doc.text(form.remitenteTelefono || '', margin + 28, currentY);
      doc.line(margin + 27, currentY + 1, margin + 78, currentY + 1);
      currentY += 11 * layoutScale;

      // Sección 2: Destinatario
      doc.setFont("helvetica", "bold");
      doc.text("DECLARO BAJO JURAMENTO", margin, currentY);
      currentY += 9 * layoutScale;

      doc.setFont("helvetica", "normal");
      const date = new Date();
      const formattedDate = date.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      });
      const destIntroLines = writeFullyJustifiedSegments([
        { text: `Fecha ${formattedDate} autorizo el traslado de mis bienes a través de la `, style: 'normal' },
        { text: 'EMPRESA DE TRANSPORTE SHALOM EMPRESARIAL S.A.C con RUC: 20512528458', style: 'bold' },
        { text: ', para el', style: 'normal' }
      ], margin, currentY, colWidth, 1.15);
      currentY += ((destIntroLines.length * 5) + 2) * layoutScale;

      doc.setFont("helvetica", "bold");
      const nombreDest = (form.destinatarioNombre || '____________________________________________________').toUpperCase();
      const srTexto = `Señor(a): ${nombreDest}`;
      doc.text(srTexto, margin, currentY);
      const srPrefixWidth = doc.getTextWidth("Señor(a): ");
      const destNameWidth = doc.getTextWidth(nombreDest);
      doc.line(margin + srPrefixWidth, currentY + 0.5, margin + srPrefixWidth + destNameWidth, currentY + 0.5);
      currentY += 6 * layoutScale;

      doc.setFont("helvetica", "normal");
      doc.text('con DNI N°', margin, currentY);
      doc.text(form.destinatarioDni || '', margin + 28, currentY);
      doc.line(margin + 26, currentY + 1, margin + (colWidth * 0.5), currentY + 1);
      const recipientMidX = margin + (colWidth * 0.55);
      doc.text('con teléfono', recipientMidX, currentY);
      doc.text(form.destinatarioTelefono || '', recipientMidX + (28 * layoutScale), currentY);
      doc.line(recipientMidX + (26 * layoutScale), currentY + 1, margin + colWidth, currentY + 1);
      currentY += 6 * layoutScale;
      doc.text('y para la oficina de', margin, currentY);
      doc.setFont('helvetica', 'bold');
      doc.text((form.sedeDestino || '').toUpperCase(), margin + 42, currentY, { maxWidth: colWidth - 42 });
      doc.line(margin + 40, currentY + 1, margin + colWidth, currentY + 1);
      currentY += 11 * layoutScale;

      // Sección 3: Tabla de Contenido
      doc.setFont("helvetica", "bold");
      doc.text("DECLARO ENVIAR LO SIGUIENTE:", margin, currentY - 2);
      
      autoTable(doc, {
        startY: currentY,
        margin: { left: margin },
        tableWidth: colWidth,
        head: [['CANT.', 'DESCRIPCIÓN DE LOS BIENES']],
        body: [
          ...documentItems.map(i => [i.cantidad, i.descripcion]),
          ['', `MOTIVO DEL ENVÍO: ${(form.motivoEnvio || '').toUpperCase()}`]
        ],
        theme: 'grid',
        headStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0], fontSize: 8.5 * layoutScale, fontStyle: 'bold', halign: 'center', cellPadding: layoutScale },
        styles: { fontSize: 8 * layoutScale, cellPadding: layoutScale, minCellHeight: 5.2 * layoutScale, textColor: [0, 0, 0], valign: 'middle', lineColor: [0, 0, 0], lineWidth: 0.2 },
        columnStyles: { 0: { cellWidth: 27 * layoutScale, halign: 'center' } },
        didParseCell: data => {
          if (data.section === 'body' && data.row.index === documentItems.length) data.cell.styles.fontStyle = 'bold';
        }
      });

      let finalY = doc.lastAutoTable.finalY + (7 * layoutScale);

      // Declaración Jurada (Texto Legal)
      doc.setFontSize(8.5 * layoutScale);
      doc.setFont("helvetica", "normal");
      const legalText = 'Así mismo, declaro bajo juramento que los presentes datos obedecen a la verdad, sometiéndome a las sanciones administrativas, civiles y penales que correspondan en caso de falsedad de los mismos, de acuerdo con lo regulado por la';
      const legalLines = writeFullyJustifiedText(legalText, margin, finalY, colWidth, 1.15);
      finalY += ((legalLines.length * 4) + 1) * layoutScale;
      doc.setFont('helvetica', 'bold');
      doc.text('Ley N° 27444 - Ley del Procedimiento Administrativo General.', margin, finalY);
      finalY += 6 * layoutScale;
      doc.setFont('helvetica', 'normal');
      const conformityText = 'Para mayor constancia y validez en cumplimiento de lo indicado, en señal de conformidad firmo esta declaración y coloco mi huella digital para los fines pertinentes.';
      const conformityLines = writeFullyJustifiedText(conformityText, margin, finalY, colWidth, 1.15);
      finalY += ((conformityLines.length * 4) + 8) * layoutScale;

      // SECCIÓN DE FIRMA Y HUELLA (Dinámica para evitar solapamiento)
      let signatureY = finalY + (7 * layoutScale);

      doc.setFontSize(8.5 * layoutScale);
      doc.setFont("helvetica", "normal");
      doc.line(margin + (colWidth * 0.4), signatureY, margin + (colWidth * 0.55), signatureY);
      doc.text(String(date.getDate()).padStart(2, '0'), margin + (colWidth * 0.475), signatureY - 1, { align: 'center' });
      doc.text('de', margin + (colWidth * 0.61), signatureY);
      doc.line(margin + (colWidth * 0.69), signatureY, margin + (colWidth * 0.83), signatureY);
      doc.text(date.toLocaleString('es-PE', { month: 'long' }), margin + (colWidth * 0.76), signatureY - 1, { align: 'center' });
      doc.text(`del ${date.getFullYear()}`, margin + (colWidth * 0.86), signatureY);

      const labels = ['Firma:', 'Nombres:', 'N° Documento:'];
      labels.forEach((label, index) => {
        const y = signatureY + ((18 + (index * 7)) * layoutScale);
        doc.text(label, margin, y);
        const value = index === 1 ? form.remitenteNombre : index === 2 ? form.remitenteDni : '';
        doc.text((value || '').toUpperCase(), margin + (28 * layoutScale), y - 1, { maxWidth: colWidth * 0.5 });
        doc.line(margin + (27 * layoutScale), y + 1, margin + (colWidth * 0.6), y + 1);
      });

      const huellaWidth = 30 * layoutScale;
      const huellaHeight = 35 * layoutScale;
      const huellaX = margin + colWidth - huellaWidth - (5 * layoutScale);
      doc.rect(huellaX, signatureY + (7 * layoutScale), huellaWidth, huellaHeight);

      // --- INCLUSIÓN DE FOTO DNI ---
      if (includeDniPhoto) {
        if (dniPhoto) {
          try {
            const imgProps = doc.getImageProperties(dniPhoto);
            const photoAreaX = pageWidth / 2 + 8;
            const maxImgW = pageWidth / 2 - 16;
            const maxImgH = pageHeight - 24;
            const scale = Math.min(maxImgW / imgProps.width, maxImgH / imgProps.height);
            const imgW = imgProps.width * scale;
            const imgH = imgProps.height * scale;
            const imgX = photoAreaX + ((maxImgW - imgW) / 2);
            const imgY = (pageHeight - imgH) / 2;
            doc.addImage(dniPhoto, imgProps.fileType, imgX, imgY, imgW, imgH);
          } catch (e) { console.error("Error al añadir imagen", e); }
        }
      }

      doc.save(`DJ_SHALOM_${form.remitenteDni || 'MANUAL'}.pdf`);
    } catch (error) {
      console.error("Error al generar el PDF:", error);
      alert("Ocurrió un error al generar el PDF. Por favor, revisa la consola para más detalles.");
    } finally {
      setLoading(false);
    }
  }, [form, documentItems, includeDniPhoto, isLandscape, dniPhoto]);

  if (sessionLoading) {
    return <div className="min-h-screen grid place-items-center bg-slate-950 text-white font-bold">Cargando...</div>;
  }

  if (!currentUser) {
    return <AuthScreen onAuthenticated={setCurrentUser} />;
  }

  return (
    <div className={`min-h-screen flex flex-col lg:flex-row overflow-x-hidden transition-colors duration-300 ${darkMode ? 'bg-slate-950 dark' : 'bg-slate-50'}`}>
      {/* PANEL DE CONTROL (IZQUIERDA) */}
      <aside className="lg:w-[600px] w-full bg-white dark:bg-slate-900 lg:h-screen flex flex-col shadow-xl z-20 border-r border-slate-200 dark:border-slate-800 transition-colors">
        <header className="bg-[#e31837] text-white p-4 shadow-md flex-shrink-0 flex justify-between items-center">
          <div className="flex items-center gap-3">
            <div className="bg-white p-1 rounded-lg">
              <ShieldCheck className="text-[#e31837]" size={24} />
            </div>
            <div>
              <h1 className="font-black text-lg tracking-tighter leading-none">SHALOM</h1>
              <p className="text-[9px] uppercase font-bold opacity-80 tracking-widest leading-none mt-1">Declaración Jurada</p>
            </div>
          </div>
          <div className="flex gap-1.5">
            <button
              type="button"
              onClick={() => setIsAccountOpen(true)}
              className="flex items-center gap-1.5 px-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors text-xs font-bold"
              title="Mi cuenta"
            >
              <Settings size={16} /> {currentUser.credits}
            </button>
            <button 
              type="button"
              onClick={resetForm} 
              className="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
              title="Limpiar Formulario"
              aria-label="Limpiar formulario"
            >
              <Trash2 size={18} />
            </button>
            <button 
              type="button"
              onClick={() => setDarkMode(!darkMode)} 
              className="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
              title={darkMode ? "Modo Claro" : "Modo Oscuro"}
              aria-label={darkMode ? "Activar modo claro" : "Activar modo oscuro"}
            >
              {darkMode ? <Sun size={18} /> : <Moon size={18} />}
            </button>
            <button type="button" onClick={logout} className="p-2 bg-white/10 hover:bg-white/20 rounded-lg" title="Cerrar sesión" aria-label="Cerrar sesión">
              <LogOut size={18} />
            </button>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-4 space-y-5 bg-slate-50/50 dark:bg-slate-950/50 transition-colors">
          {/* Configuración */}
          <section className="space-y-2.5">
            <h2 className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase flex items-center gap-2 ml-1">
              <Layout size={14} className="text-red-500" /> Formato de Documento
            </h2>
            <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700 dark:text-slate-200">Adjuntar Foto DNI</span>
                <button
                  type="button"
                  onClick={() => handleDniToggle(!includeDniPhoto)}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none ${
                    includeDniPhoto ? 'bg-red-600' : 'bg-slate-300 dark:bg-slate-700'
                  }`}
                  role="switch"
                  aria-checked={includeDniPhoto}
                  aria-label="Adjuntar foto del DNI"
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      includeDniPhoto ? 'translate-x-6' : 'translate-x-1'
                    }`}
                  />
                </button>
              </div>
              <p className="text-[10px] text-slate-400 mt-2 italic">
                {includeDniPhoto ? '* La foto se añadirá en una segunda página.' : '* Documento A4 vertical, como el formato de referencia.'}
              </p>
            </div>
          </section>

          {/* Remitente */}
          <section className={`space-y-2.5 ${!includeDniPhoto ? 'opacity-100' : ''}`}>
            <h2 className="text-[11px] font-bold text-black dark:text-white uppercase flex items-center justify-between gap-2 ml-1">
              <span className="flex items-center gap-2"><User size={14} className="text-red-500" /> Remitente</span>
              <DniLookupToggle enabled={dniLookupEnabled.remitente} onChange={enabled => setDniLookupEnabled(prev => ({ ...prev, remitente: enabled }))} label="remitente"/>
            </h2>
            <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3 shadow-sm transition-colors">
              <div className="flex gap-3">
                {includeDniPhoto && <label className="flex-1 flex flex-col items-center justify-center h-28 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700 transition-all overflow-hidden relative">
                  {dniPhoto ? (
                    <img src={dniPhoto} className="w-full h-full object-cover opacity-50" alt="Vista previa del DNI" />
                  ) : (
                    <div className="flex flex-col items-center">
                      <ImageIcon size={24} className="text-slate-400 mb-1" />
                      <span className="text-[10px] text-slate-500 font-bold uppercase">Foto DNI</span>
                    </div>
                  )}
                  <input type="file" className="hidden" accept="image/jpeg,image/png,image/webp" onChange={handlePhotoUpload} />
                  {dniPhoto && <div className="absolute inset-0 flex items-center justify-center bg-black/20 text-white font-black text-[10px]">CAMBIAR FOTO</div>}
                </label>}
                <div className="flex-1 space-y-3">
              <div className="flex gap-2">
                <input
                  type="tel"
                  placeholder="DNI o C.E."
                  className="flex-1 p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-red-500/40 text-black dark:text-white outline-none transition-all shadow-sm"
                  value={form.remitenteDni}
                  onChange={(e) => handleNumericInput('remitenteDni', e.target.value, 9)}
                />
                <input
                  placeholder="Teléfono"
                  className="flex-1 p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-black dark:text-white outline-none transition-all shadow-sm"
                  value={form.remitenteTelefono}
                  onChange={(e) => handleNumericInput('remitenteTelefono', e.target.value, 9)}
                />
              </div>
                  <input
                    placeholder="Nombres Completos"
                    className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-black dark:text-white outline-none transition-all shadow-sm"
                    value={form.remitenteNombre}
                    onChange={(e) => setForm({ ...form, remitenteNombre: e.target.value.toUpperCase() })}
                  />
                  <DniLookupStatus lookup={dniLookupStatus.remitente} />
                </div>
              </div>
            </div>
          </section>

          {/* Destinatario */}
          <section className="space-y-3">
            <h2 className="text-[11px] font-bold text-black dark:text-white uppercase flex items-center justify-between gap-2 ml-1">
              <span className="flex items-center gap-2"><MapPin size={14} className="text-red-500" /> Destinatario</span>
              <DniLookupToggle enabled={dniLookupEnabled.destinatario} onChange={enabled => setDniLookupEnabled(prev => ({ ...prev, destinatario: enabled }))} label="destinatario"/>
            </h2>
            <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3 shadow-sm transition-colors">
              <input
                placeholder="Nombre de quien recibe"
                className="w-full p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg text-sm text-black dark:text-white outline-none"
                value={form.destinatarioNombre}
                onChange={(e) => setForm({ ...form, destinatarioNombre: e.target.value.toUpperCase() })}
              />
              <div className="flex gap-2">
                <input placeholder="DNI o C.E." className="flex-1 p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-black dark:text-white outline-none transition-all shadow-sm" value={form.destinatarioDni} onChange={(e) => handleNumericInput('destinatarioDni', e.target.value, 9)} />
                <input placeholder="Telf." className="flex-1 p-2.5 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl text-sm text-black dark:text-white outline-none transition-all shadow-sm" value={form.destinatarioTelefono} onChange={(e) => handleNumericInput('destinatarioTelefono', e.target.value, 9)} />
              </div>
              <DniLookupStatus lookup={dniLookupStatus.destinatario} />
              <button
                type="button"
                onClick={() => setIsModalOpen(true)}
                className="w-full p-2.5 bg-red-50/50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 rounded-lg text-sm font-bold text-red-700 dark:text-red-400 text-left flex justify-between items-center hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
              >
                <span className="truncate">{form.sedeDestino || "SELECCIONAR SEDE DESTINO"}</span>
                <Search size={16} className="text-red-400 dark:text-red-500 flex-shrink-0" />
              </button>
              <select
                className="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-black dark:text-white outline-none transition-all shadow-sm cursor-pointer"
                value={form.motivoEnvio}
                onChange={(e) => setForm({ ...form, motivoEnvio: e.target.value })}
              >
                <option value="">SELECCIONAR MOTIVO</option>
                {["VENTA", "ENCOMIENDA", "OLVIDO DE PERTENENCIAS", "VIAJE", "DEVOLUCION", "TRASPASO", "REGALO"].map(m => (
                  <option key={m} value={m}>{m}</option>
                ))}
              </select>
            </div>
          </section>

          {/* Items */}
          <section className="space-y-3">
            <div className="flex justify-between items-center ml-1">
              <h2 className="text-[11px] font-bold text-black dark:text-white uppercase flex items-center gap-2">
                <Package size={14} className="text-red-500" /> Detalle de Bienes
              </h2>
              <button type="button" onClick={addItem} className="text-[10px] font-black text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-lg border border-blue-100 dark:border-blue-900/30">+ AGREGAR</button>
            </div>
            {form.items.map((item, index) => (
              <div key={index} className="flex gap-2 p-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm relative group transition-all hover:shadow-md">
                <input type="text" inputMode="numeric" maxLength={3} aria-label={`Cantidad del bien ${index + 1}`} className="w-14 text-center font-bold text-black dark:text-white bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-red-500/20" value={item.cantidad} onChange={(e) => handleItemChange(index, 'cantidad', e.target.value)} />
                <input placeholder="Descripción de bien" className="flex-1 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-black dark:text-white rounded-lg text-[13px] p-2 outline-none focus:ring-2 focus:ring-red-500/20" value={item.descripcion} onChange={(e) => handleItemChange(index, 'descripcion', e.target.value)} />
                <button type="button" onClick={() => removeItem(index)} className="text-black dark:text-white hover:text-red-500 self-start p-1 transition-colors" aria-label={`Eliminar bien ${index + 1}`}><Trash2 size={16} /></button>
              </div>
            ))}
          </section>
        </main>

        <footer className="p-4 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 transition-colors">
          <button type="button" onClick={generarPDF} disabled={loading} className="w-full bg-[#e31837] text-white py-4 rounded-xl font-black flex items-center justify-center gap-3 shadow-lg shadow-red-200 active:scale-95 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
            {loading ? <div className="w-6 h-6 border-3 border-white/30 border-t-white rounded-full animate-spin" /> : <Download size={20} />}
            {loading ? 'GENERANDO...' : 'GENERAR PDF OFICIAL'}
          </button>
        </footer>
      </aside>

      {/* PREVISUALIZACIÓN EN TIEMPO REAL (DERECHA) */}
      <section className="relative flex-1 bg-slate-200/30 dark:bg-slate-950 hidden lg:block overflow-hidden h-screen transition-colors">
        <div className="absolute top-4 right-4 z-30 flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/95 dark:bg-slate-800/95 p-1.5 shadow-lg backdrop-blur">
          <button
            type="button"
            onClick={() => setPreviewZoom(value => Math.max(0.5, Number((value - 0.1).toFixed(1))))}
            disabled={previewZoom <= 0.5}
            className="p-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed"
            title="Alejar"
            aria-label="Alejar vista previa"
          >
            <ZoomOut size={17} />
          </button>
          <span className="w-12 text-center text-xs font-bold text-slate-700 dark:text-slate-200" aria-live="polite">
            {Math.round(previewZoom * 100)}%
          </span>
          <button
            type="button"
            onClick={() => setPreviewZoom(1)}
            className="p-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
            title="Restablecer zoom"
            aria-label="Restablecer zoom al 100%"
          >
            <RotateCcw size={16} />
          </button>
          <button
            type="button"
            onClick={() => setPreviewZoom(value => Math.min(1.6, Number((value + 0.1).toFixed(1))))}
            disabled={previewZoom >= 1.6}
            className="p-2 rounded-lg text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed"
            title="Acercar"
            aria-label="Acercar vista previa"
          >
            <ZoomIn size={17} />
          </button>
        </div>

        <div
          ref={previewViewportRef}
          onPointerDown={handlePreviewPointerDown}
          onPointerMove={handlePreviewPointerMove}
          onPointerUp={stopPreviewDragging}
          onPointerCancel={stopPreviewDragging}
          className={`absolute inset-0 overflow-auto p-8 select-none ${isPreviewDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
          style={{ touchAction: 'none' }}
        >
        <div
          className={`bg-white shadow-2xl flex shrink-0 mx-auto my-8 transition-all relative origin-top border border-slate-200 text-black ${includeDniPhoto ? 'w-[842px] min-h-[595px] flex-row p-4 scale-[0.78] xl:scale-[0.9]' : 'w-[595px] min-h-[842px] flex-col px-5 py-4 scale-[0.82] xl:scale-[0.96]'}`}
          style={{ zoom: previewZoom }}
        > {/* Contenedor del documento */}
          <div className={includeDniPhoto ? 'w-1/2 overflow-hidden' : 'w-full'}>
          <div className="flex flex-col origin-top-left" style={includeDniPhoto ? { width: '119.05%', transform: 'scale(0.84)' } : undefined}>
          {/* Header Preview */}
          <div className="flex justify-center items-center mb-1">
            <div className="text-center">
              <h3 className="font-bold text-[12px] uppercase text-black leading-tight underline">DECLARACIÓN JURADA SIMPLE PARA TRASLADO DE BIENES - USO PERSONAL</h3>
            </div>
          </div>

          {/* Content Preview */}
          <div className="space-y-3 text-[10px] leading-[1.15] text-black flex-1">
            <div>
              <p className="fully-justified">Por el presente documento de Carácter, de declaración jurada YO</p>
              <p className="h-4 border-b border-black text-center font-bold uppercase">{form.remitenteNombre}</p>
              <p className="fully-justified mt-1">
                identificado con documento de identificación <span className="font-bold">(DNI, CARNET DE EXTRANJERÍA)</span>
                <span className="inline-block min-w-36 border-b border-black text-center font-bold">{form.remitenteDni}</span>
              </p>
              <p>con Teléfono <span className="inline-block min-w-36 border-b border-black text-center">{form.remitenteTelefono}</span></p>
            </div>

            <div>
              <p className="font-bold text-[11px] mb-3">DECLARO BAJO JURAMENTO</p>
              <p className="fully-justified">
                Fecha <span className="inline-block w-28 border-b border-black text-center font-bold">{new Date().toLocaleDateString('es-PE')}</span> autorizo el traslado de mis bienes a través de la
                <span className="font-bold"> EMPRESA DE TRANSPORTE SHALOM EMPRESARIAL S.A.C con RUC: 20512528458</span>, para el
              </p>
              <p>Señor(a) <span className="inline-block w-[82%] border-b border-black text-center font-bold uppercase">{form.destinatarioNombre}</span></p>
              <div className="flex gap-5">
                <p className="flex-1">con DNI N° <span className="inline-block w-[65%] border-b border-black text-center">{form.destinatarioDni}</span></p>
                <p className="flex-1">con teléfono <span className="inline-block w-[65%] border-b border-black text-center">{form.destinatarioTelefono}</span></p>
              </div>
              <p>y para la oficina de <span className="inline-block w-[78%] border-b border-black text-center font-bold uppercase">{form.sedeDestino}</span></p>
            </div>

            <p className="font-bold text-[11px]">DECLARO ENVIAR LO SIGUIENTE:</p>
            <table className="w-full border-collapse border border-black text-[9px]">
              <thead>
                <tr>
                  <th className="border border-black px-1 h-5 w-[76px]">CANTIDAD</th>
                  <th className="border border-black px-1 h-5">DESCRIPCIÓN</th>
                </tr>
              </thead>
              <tbody>
                {documentItems.map((item, index) => (
                  <tr key={index}>
                    <td className="border border-black px-1 text-center h-4">{item.cantidad}</td>
                    <td className="border border-black px-1 h-4">{item.descripcion}</td>
                  </tr>
                ))}
                <tr>
                  <td className="border border-black h-5"></td>
                  <td className="border border-black px-1 h-5 font-bold">MOTIVO DEL ENVÍO: {form.motivoEnvio}</td>
                </tr>
              </tbody>
            </table>

            <div className="space-y-3 text-[9px]">
              <p className="fully-justified">
                Así mismo, declaro bajo juramento que los presentes datos obedecen a la verdad, sometiéndome a las sanciones administrativas, civiles y penales que correspondan en caso de falsedad de los mismos, de acuerdo con lo regulado por la <span className="font-bold">Ley N° 27444 - Ley del Procedimiento Administrativo General.</span>
              </p>
              <p className="fully-justified">
                Para mayor constancia y validez en cumplimiento de lo indicado, en señal de conformidad firmo esta declaración y coloco mi huella digital para los fines pertinentes.
              </p>
            </div>

            <div className="flex justify-center items-end gap-3 pt-2 text-[9px]">
              <span className="inline-block w-20 border-b border-black text-center">{String(new Date().getDate()).padStart(2, '0')}</span>
              <span>de</span>
              <span className="inline-block w-20 border-b border-black text-center">{new Date().toLocaleString('es-PE', { month: 'long' })}</span>
              <span>del {new Date().getFullYear()}</span>
            </div>

            <div className="flex justify-between items-end pt-1 text-[9px]">
              <div className="w-[55%] space-y-1.5">
                <p>Firma: <span className="inline-block w-[78%] border-b border-black"></span></p>
                <p>Nombres: <span className="inline-block w-[72%] border-b border-black text-center uppercase">{form.remitenteNombre}</span></p>
                <p>N° Documento: <span className="inline-block w-[61%] border-b border-black text-center">{form.remitenteDni}</span></p>
              </div>
              <div className="w-[86px] h-[100px] border border-black"></div>
            </div>
          </div>
          </div>
          </div>

          {includeDniPhoto && (
            <div className="w-1/2 flex items-center justify-center overflow-hidden">
              {dniPhoto && <img src={dniPhoto} className="max-w-full max-h-[92%] object-contain" alt="DNI adjunto" />}
            </div>
          )}
        </div>
        </div>
      </section>

      {/* MODAL BUSCADOR DE SEDES */}
      {isModalOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white dark:bg-slate-800 w-full max-w-7xl rounded-2xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden animate-in zoom-in-95 duration-200 border dark:border-slate-700 transition-colors" role="dialog" aria-modal="true" aria-labelledby="agencias-title">
            <div className="p-4 border-b dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900">
              <h3 id="agencias-title" className="font-bold text-slate-800 dark:text-white">Buscar Sede Shalom</h3>
              <button 
                type="button"
                onClick={() => setIsModalOpen(false)}
                className="p-1 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-full transition-colors"
                aria-label="Cerrar buscador de sedes"
              >
                <X size={20} className="text-slate-500 dark:text-slate-400" />
              </button>
            </div>
            
            <div className="p-4 border-b">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                <input
                  type="text"
                  placeholder="Escribe el nombre de la ciudad, sede o dirección..."
                  className="w-full pl-10 pr-4 py-2.5 bg-slate-100 dark:bg-slate-700 dark:text-white border-none rounded-xl text-sm focus:ring-2 focus:ring-red-500/20 outline-none"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  autoFocus
                />
              </div>
            </div>

            <div className="flex-1 overflow-y-auto p-4 bg-slate-50/30 dark:bg-slate-900/10">
              {agenciasLoading ? (
                <div className="p-8 text-center text-slate-500 dark:text-slate-300 text-sm" role="status">
                  Cargando sedes...
                </div>
              ) : agenciasFetchError ? (
                <div className="p-8 text-center text-red-500 text-sm font-medium">
                  {agenciasFetchError}
                </div>
              ) : filteredAgencias.length > 0 ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-2">
                  {filteredAgencias.map((agencia, idx) => {
                    const displayLabel = [
                      agencia.departamento?.trim(),
                      agencia.provincia?.trim(),
                      agencia.distrito?.trim(),
                      agencia.agencia?.trim()
                    ].filter(Boolean).join(' / ');

                    return (
                      <div
                        key={idx}
                        className="bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col justify-between hover:border-red-300 dark:hover:border-red-500 transition-all duration-200 group"
                      >
                        <div>
                          <h4 className="mb-1 border-l-4 border-red-600 pl-2 text-sm font-black uppercase leading-tight text-slate-900 dark:text-white">{agencia.agencia}</h4>
                          <p className="text-[11px] text-black dark:text-white mb-1 leading-relaxed">
                            {agencia.departamento}, {agencia.provincia}, {agencia.distrito}
                          </p>
                          <p className="text-[10px] text-black dark:text-slate-300 mb-3 leading-tight italic">{agencia.direccion}</p>
                        </div>
                        <button
                          onClick={() => {
                            setForm({ ...form, sedeDestino: displayLabel.toUpperCase() });
                            setIsModalOpen(false);
                            setSearchTerm('');
                          }}
                          className="mt-auto w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-xs font-bold transition-all uppercase shadow-sm active:scale-95"
                        >
                          Seleccionar
                        </button>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <div className="p-8 text-center text-slate-400 text-sm">
                  No se encontraron sedes con ese nombre.
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      {isAccountOpen && (
        <AccountPanel
          user={currentUser}
          onClose={() => setIsAccountOpen(false)}
          onUserChange={setCurrentUser}
        />
      )}
    </div>
  );
};

export default App;
