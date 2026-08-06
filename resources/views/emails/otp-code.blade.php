@component('mail::message')
# Código de Verificación OTP

Hola {{ $request->requester_name ?? 'Usuario' }},

Tu código de verificación de **6 dígitos** es:

@component('mail::panel')
## 123456

**No compartas este código con nadie.**
@endcomponent

### Información importante:

- **Email:** {{ $emailMasked }}
- **Código de seguimiento:** `{{ $trackingCode }}`
- **Solicitud:** {{ $request->application_name ?? 'Token de acceso' }}
- **Vence en:** {{ $expiresInMinutes }} minutos
- **Intentos permitidos:** {{ $maxAttempts }}
- **Reenvíos permitidos:** {{ $maxResends }}

---

### Instrucciones:

1. Copia el código de 6 dígitos arriba
2. Ingresa el código en el formulario de verificación
3. Si expira, solicita un nuevo código

**Seguridad:**
- Este código vence en {{ $expiresInMinutes }} minutos
- Solo puedes intentar {{ $maxAttempts }} veces
- Puedes solicitar reenvío {{ $maxResends }} veces
- Si no solicitaste este código, ignora este email

@component('mail::subcopy')
Este es un email automático de CodeRED Platform. No respondas a este correo.

Si tienes problemas, contacta con soporte en {{ config('app.url') }}/soporte
@endcomponent

@endcomponent
