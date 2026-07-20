# ADR 0038: DNI local-first y PeruDevs como respaldo

## Decisión

`dni_records` es la fuente primaria. PeruDevs solo se consulta si no hay registro local ni marca negativa vigente. El proveedor usa GET con `document` y `key`; la API key se cifra en ajustes y nunca se registra.

Los resultados válidos pueden persistirse. La edad se calcula desde la fecha de nacimiento y no se almacena. Los registros PeruDevs antiguos se devuelven inmediatamente y se refrescan mediante cola con un lock basado en SHA-256.

## Consecuencias

Se reduce consumo externo, se conserva disponibilidad local y las credenciales quedan separadas de Sanctum. El contrato externo se cubre con fixtures y no se consulta en la suite.
