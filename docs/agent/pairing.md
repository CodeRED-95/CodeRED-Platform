# Pairing

Genere Pair Code en CodeRED Platform y envíelo al agente:

```bash
curl -H "Authorization: Bearer $CODERED_AGENT_LOCAL_API_TOKEN" -H "Content-Type: application/json" -d {"pair_code":"CRD-XXXXXX"} http://127.0.0.1:5680/v1/pair
```

La respuesta nunca contiene shared_secret.
