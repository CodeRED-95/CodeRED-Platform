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
  ExternalLink,
  Eye,
  LogOut,
  Settings,
  CircleHelp,
  AlertTriangle
} from 'lucide-react';
import AuthScreen from './AuthScreen.jsx';
import AccountPanel from './AccountPanel.jsx';
import { buildDeclaracionPdf } from './pdf/buildDeclaracionPdf.js';

// Mínimo de filas en blanco en la tabla "DECLARO ENVIAR LO SIGUIENTE" —
// antes era 10 fijo (una tabla casi vacía incluso con 1-2 bienes reales);
// 3 coincide con las filas iniciales del formulario y evita el vacío
// excesivo sin dejar de dar espacio para completar a mano si hace falta.
const MIN_TABLE_ROWS = 3;
// Espera tras la última tecla antes de regenerar la vista previa — evita
// reconstruir el PDF en cada pulsación.
const PREVIEW_DEBOUNCE_MS = 500;
// Espera tras la última tecla antes de volver a buscar agencias en CodeRED
// Platform — evita una petición por cada carácter tecleado.
const AGENCIAS_SEARCH_DEBOUNCE_MS = 350;

const createInitialForm = () => ({
  remitenteDni: '',
  remitenteNombre: '',
  remitenteTelefono: '',
  destinatarioNombre: '',
  destinatarioDni: '',
  destinatarioTelefono: '',
  sedeDestino: '',
  // Referencia estable a la agencia elegida en CodeRED Platform (internal_id
  // de app/Modules/Agencies/Models/Agency.php) — sedeDestino es solo el
  // texto que se imprime en el PDF; agencyId evita depender únicamente del
  // texto para identificar cuál agencia fue realmente seleccionada.
  agencyId: null,
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
  const [agenciasLoading, setAgenciasLoading] = useState(false);
  const [darkMode, setDarkMode] = useState(true);
  const [includeDniPhoto, setIncludeDniPhoto] = useState(false);
  const [dniPhoto, setDniPhoto] = useState(null);
  const [agencias, setAgencias] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [agenciasRetryToken, setAgenciasRetryToken] = useState(0);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(true);
  const [previewError, setPreviewError] = useState('');
  const [isMobilePreviewOpen, setIsMobilePreviewOpen] = useState(false);
  const [downloadError, setDownloadError] = useState('');
  const [dniLookupStatus, setDniLookupStatus] = useState({
    remitente: { status: 'idle', message: '' },
    destinatario: { status: 'idle', message: '' }
  });
  const [dniLookupEnabled, setDniLookupEnabled] = useState({ remitente: false, destinatario: false });
  const previewUrlRef = useRef(null);
  const isGeneratingRef = useRef(false);
  const [form, setForm] = useState(createInitialForm);
  const documentItems = useMemo(() => Array.from(
    { length: Math.max(MIN_TABLE_ROWS, form.items.length) },
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

  // Buscador de sedes: CodeRED Platform es la única fuente de verdad para
  // agencias (antes se leía un JSON estático publicado en un Gist de
  // GitHub, ajeno por completo a CodeRED). La búsqueda ocurre en el
  // servidor (proxy /api/agencias -> CodeRED GET /api/v1/agencias, ability
  // agencias:consultar) con debounce, en vez de cargar miles de agencias en
  // el cliente y filtrar ahí. Solo se consulta mientras el modal está
  // abierto, para no disparar peticiones innecesarias en cada tecleo del
  // resto del formulario.
  useEffect(() => {
    if (!isModalOpen) return undefined;
    const controller = new AbortController();
    setAgenciasLoading(true);
    const timer = window.setTimeout(async () => {
      try {
        const url = new URL('/api/agencias', window.location.origin);
        if (searchTerm.trim()) url.searchParams.set('search', searchTerm.trim());
        const response = await fetch(url, { signal: controller.signal });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'No se pudo obtener el listado de agencias.');
        setAgencias(Array.isArray(payload.data) ? payload.data : []);
        setAgenciasFetchError(null);
      } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('Error cargando agencias:', error);
        setAgenciasFetchError('No se pudo obtener el listado de agencias de CodeRED Platform. Inténtalo nuevamente.');
        setAgencias([]);
      } finally {
        if (!controller.signal.aborted) setAgenciasLoading(false);
      }
    }, AGENCIAS_SEARCH_DEBOUNCE_MS);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [isModalOpen, searchTerm, agenciasRetryToken]);

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
        // Id estable por intento: si el navegador o un proxy intermedio
        // reintenta esta misma solicitud a nivel de transporte, el backend
        // reconoce el X-Request-Id repetido y no vuelve a descontar una
        // consulta (ver dniIdempotencyCache en server/app-backend.js).
        const requestId = crypto.randomUUID();
        const response = await fetch(`/api/dni/${dni}`, { signal: controller.signal, headers: { 'X-Request-Id': requestId } });
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

  // Vista previa = el mismo PDF que se descarga. Se reconstruye (con
  // debounce) cada vez que cambian los datos que afectan al documento, en
  // vez de mantener una maqueta HTML aparte que podía desincronizarse del
  // archivo real.
  useEffect(() => {
    let cancelled = false;
    setPreviewLoading(true);
    const timer = window.setTimeout(async () => {
      try {
        const doc = await buildDeclaracionPdf({ form, documentItems, includeDniPhoto, dniPhoto });
        if (cancelled) return;
        const blobUrl = doc.output('bloburl').toString();
        if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
        previewUrlRef.current = blobUrl;
        setPreviewUrl(blobUrl);
        setPreviewError('');
      } catch (error) {
        if (cancelled) return;
        console.error('Error al generar la vista previa:', error);
        setPreviewError('No se pudo generar la vista previa. Verifica los datos ingresados.');
      } finally {
        if (!cancelled) setPreviewLoading(false);
      }
    }, PREVIEW_DEBOUNCE_MS);

    return () => {
      cancelled = true;
      window.clearTimeout(timer);
    };
  }, [form, documentItems, includeDniPhoto, dniPhoto]);

  // Revoca el último blob URL al desmontar, para no dejar memoria retenida.
  useEffect(() => () => {
    if (previewUrlRef.current) URL.revokeObjectURL(previewUrlRef.current);
  }, []);

  const logout = useCallback(async () => {
    await fetch('/api/auth/logout', { method: 'POST' });
    setCurrentUser(null);
    setIsAccountOpen(false);
  }, []);

  const generarPDF = useCallback(async () => {
    if (isGeneratingRef.current) return;
    isGeneratingRef.current = true;
    setLoading(true);
    setDownloadError('');
    try {
      const doc = await buildDeclaracionPdf({ form, documentItems, includeDniPhoto, dniPhoto });
      doc.save(`DJ_SHALOM_${form.remitenteDni || 'MANUAL'}.pdf`);
    } catch (error) {
      console.error('Error al generar el PDF:', error);
      setDownloadError('Ocurrió un error al generar el documento. Verifica los datos e intenta nuevamente.');
    } finally {
      setLoading(false);
      isGeneratingRef.current = false;
    }
  }, [form, documentItems, includeDniPhoto, dniPhoto]);

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
              onClick={() => setIsMobilePreviewOpen(true)}
              className="flex items-center gap-1.5 px-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors text-xs font-bold lg:hidden"
              title="Vista previa"
              aria-label="Ver vista previa del documento"
            >
              <Eye size={16} />
            </button>
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

        <footer className="p-4 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 transition-colors space-y-2">
          {downloadError && (
            <div className="flex items-start gap-2 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-xs font-medium p-2.5" role="alert">
              <AlertTriangle size={16} className="flex-shrink-0 mt-0.5" />
              <span>{downloadError}</span>
            </div>
          )}
          <button type="button" onClick={generarPDF} disabled={loading} className="w-full bg-[#e31837] text-white py-4 rounded-xl font-black flex items-center justify-center gap-3 shadow-lg shadow-red-200 active:scale-95 transition-all disabled:opacity-70 disabled:cursor-not-allowed">
            {loading ? <div className="w-6 h-6 border-3 border-white/30 border-t-white rounded-full animate-spin" /> : <Download size={20} />}
            {loading ? 'GENERANDO...' : 'GENERAR PDF OFICIAL'}
          </button>
        </footer>
      </aside>

      {/* PREVISUALIZACIÓN REAL DEL PDF (DERECHA) */}
      <section className="relative flex-1 bg-slate-200/30 dark:bg-slate-950 hidden lg:flex lg:flex-col overflow-hidden h-screen transition-colors">
        <div className="flex-shrink-0 flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 backdrop-blur">
          <div className="flex items-center gap-2 text-slate-600 dark:text-slate-300">
            <Eye size={16} />
            <span className="text-xs font-bold uppercase tracking-wide">Vista previa del PDF</span>
          </div>
          <button
            type="button"
            onClick={() => previewUrl && window.open(previewUrl, '_blank', 'noopener,noreferrer')}
            disabled={!previewUrl}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            title="Abrir en una pestaña nueva"
          >
            <ExternalLink size={14} /> Ampliar en pestaña nueva
          </button>
        </div>

        <div className="relative flex-1 overflow-hidden">
          {previewError ? (
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-8 text-center">
              <AlertTriangle size={28} className="text-red-500" />
              <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">{previewError}</p>
            </div>
          ) : previewUrl ? (
            <iframe
              key={previewUrl}
              src={previewUrl}
              title="Vista previa de la Declaración Jurada"
              className="absolute inset-0 w-full h-full border-0 bg-slate-100 dark:bg-slate-900"
            />
          ) : null}

          {previewLoading && (
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/70 dark:bg-slate-950/70 backdrop-blur-sm">
              <div className="w-8 h-8 border-3 border-slate-300 dark:border-slate-700 border-t-[#e31837] rounded-full animate-spin" />
              <p className="text-xs font-bold text-slate-600 dark:text-slate-300">Generando vista previa...</p>
            </div>
          )}
        </div>
      </section>

      {/* VISTA PREVIA MÓVIL/TABLET (MODAL) */}
      {isMobilePreviewOpen && (
        <div className="fixed inset-0 z-[100] flex flex-col bg-slate-900/60 backdrop-blur-sm lg:hidden animate-in fade-in duration-200">
          <div className="flex-shrink-0 flex items-center justify-between gap-2 px-4 py-3 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
            <div className="flex items-center gap-2 text-slate-700 dark:text-slate-200">
              <Eye size={16} />
              <span className="text-xs font-bold uppercase tracking-wide">Vista previa</span>
            </div>
            <div className="flex items-center gap-1.5">
              <button
                type="button"
                onClick={() => previewUrl && window.open(previewUrl, '_blank', 'noopener,noreferrer')}
                disabled={!previewUrl}
                className="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed"
                title="Abrir en una pestaña nueva"
                aria-label="Abrir vista previa en una pestaña nueva"
              >
                <ExternalLink size={18} />
              </button>
              <button
                type="button"
                onClick={() => setIsMobilePreviewOpen(false)}
                className="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                title="Cerrar"
                aria-label="Cerrar vista previa"
              >
                <X size={18} />
              </button>
            </div>
          </div>

          <div className="relative flex-1 bg-white dark:bg-slate-950 overflow-hidden">
            {previewError ? (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-8 text-center">
                <AlertTriangle size={28} className="text-red-500" />
                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">{previewError}</p>
              </div>
            ) : previewUrl ? (
              <iframe
                key={previewUrl}
                src={previewUrl}
                title="Vista previa de la Declaración Jurada"
                className="absolute inset-0 w-full h-full border-0 bg-slate-100 dark:bg-slate-900"
              />
            ) : null}

            {previewLoading && (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/70 dark:bg-slate-950/70 backdrop-blur-sm">
                <div className="w-8 h-8 border-3 border-slate-300 dark:border-slate-700 border-t-[#e31837] rounded-full animate-spin" />
                <p className="text-xs font-bold text-slate-600 dark:text-slate-300">Generando vista previa...</p>
              </div>
            )}
          </div>
        </div>
      )}

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
                <div className="p-8 text-center space-y-3">
                  <p className="text-red-500 text-sm font-medium">{agenciasFetchError}</p>
                  <button
                    type="button"
                    onClick={() => setAgenciasRetryToken(token => token + 1)}
                    className="px-4 py-2 rounded-lg text-xs font-bold uppercase bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                  >
                    Reintentar
                  </button>
                </div>
              ) : agencias.length > 0 ? (
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-2">
                  {agencias.map((agencia) => {
                    const displayLabel = [
                      agencia.departamento?.trim(),
                      agencia.provincia?.trim(),
                      agencia.distrito?.trim(),
                      agencia.agencia?.trim()
                    ].filter(Boolean).join(' / ');

                    return (
                      <div
                        key={agencia.agencyId}
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
                            setForm({ ...form, agencyId: agencia.agencyId, sedeDestino: displayLabel.toUpperCase() });
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
