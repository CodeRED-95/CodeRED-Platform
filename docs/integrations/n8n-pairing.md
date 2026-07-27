# Pairing n8n

1. En CodeRED abra Integraciones > n8n y genere un Pair Code.
2. En n8n cree una credencial CodeREDApi con URL de CodeRED, nombre, URL pública, entorno y Pair Code.
3. Ejecute CodeRED > Pair Instance o use Connect my account cuando n8n lo exponga para community credentials.
4. CodeRED responde una sola vez con `integration_uuid` y `shared_secret`. Guárdelos en la credencial cifrada y borre el Pair Code.

Endpoint definitivo: `POST /api/v1/integrations/n8n/pair`.
