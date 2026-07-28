# Pairing n8n

1. En CodeRED abra Integraciones > n8n y genere un Pair Code.
2. En n8n cree una credencial CodeREDApi con URL de CodeRED, nombre, URL pública, entorno y Pair Code.
3. Ejecute CodeRED > Pair Instance o use Connect my account cuando n8n lo exponga para community credentials.
4. El nodo envía el Pair Code al `codered-agent`; el agente reclama el pairing ante Platform, cifra el `shared_secret` en `/data/integration.enc` y n8n recibe solo estado saneado.

Endpoint local definitivo para n8n: `POST {agentBaseUrl}/api/v1/pair`. El nodo no llama directamente al endpoint de pairing de Platform.
