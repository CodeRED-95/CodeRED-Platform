# ADR 0010: importación masiva RENIEC fuera de HTTP

Estado: aceptado.

Se adopta archivo previamente colocado en servidor, worker Redis exclusivo, lectura streaming, staging PostgreSQL mediante COPY, merge SQL y checkpoints por byte offset. Livewire solo controla registros y nunca transfiere el padrón. Esta decisión evita memoria proporcional al archivo, millones de Jobs/consultas y bloqueo de las colas normales. UNLOGGED staging acepta pérdida recuperable porque archivo, checksum y checkpoint permanecen como fuente de reanudación.
