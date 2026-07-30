# Workflow n8n — Generación de Certificados PDF Selcap

## Resumen

Este workflow recibe los datos de un certificado desde Selcap AV y genera un PDF
profesional usando Google Slides como plantilla. El PDF se envía de vuelta al servidor
vía un endpoint de callback.

## Prerequisitos

1. **Credenciales de Google** configuradas en n8n (OAuth2 o Service Account):
   - Google Slides API habilitada
   - Google Drive API habilitada
2. **Plantilla de Google Slides** con los placeholders definidos abajo
3. **Acceso HTTP** al servidor de Selcap para el callback

---

## Nodos del Workflow

### 1. Webhook Trigger

- **Tipo:** Webhook
- **Método:** POST
- **URL:** `https://tu-n8n.com/webhook/selcap-certificados`
- **Respuesta:** Inmediata (200 OK)

**Payload recibido (JSON):**
```json
{
  "attempt_id": 123,
  "certificate_id": 45,
  "folio": "00123",
  "student_name": "Juan Pérez",
  "student_rut": "12.345.678-9",
  "student_email": "juan@example.com",
  "course_title": "Regulación Emocional para Niños y Adolescentes con Autismo",
  "eval_title": "Evaluación Final",
  "score": 95,
  "date_range": "Del 05 y 08 de Mayo, 2025",
  "hours": 6,
  "address": "Av. Tobalaba 1621, Providencia, Santiago.",
  "submitted_at": "2025-05-08 15:30:00",
  "qr_url": "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=...",
  "verify_url": "https://aula.selcap.cl/verificar.php?id=123",
  "callback_url": "https://aula.selcap.cl/webhook-certificate-callback.php",
  "callback_secret": "selcap-cert-callback-2026"
}
```

---

### 2. Google Slides — Copiar Presentación

- **Tipo:** Google Slides node (o HTTP Request a Slides API)
- **Acción:** Copiar la plantilla
- **Template ID:** `<ID_DE_TU_PLANTILLA_EN_DRIVE>`
- **Nuevo nombre:** `Certificado - {{$json.student_name}} - {{$json.folio}}`

**API:**
```
POST https://slides.googleapis.com/v1/presentations/{presentationId}:copy
```

---

### 3. Google Slides — Reemplazar Textos (Batch Update)

- **Tipo:** Google Slides batch update
- **Acción:** `replaceAllText`

**Replacements:**
| Placeholder      | Campo del JSON      | Ejemplo                                    |
|------------------|---------------------|--------------------------------------------|
| `{{NOMBRE}}`     | `student_name`      | Juan Pérez                                 |
| `{{RUT}}`        | `student_rut`       | 12.345.678-9                               |
| `{{CURSO}}`      | `course_title`      | Regulación Emocional para Niños...         |
| `{{FECHA}}`      | `date_range`        | Del 05 y 08 de Mayo, 2025                  |
| `{{HORAS}}`      | `hours`             | 6 horas.                                   |
| `{{DIRECCION}}`  | `address`           | Av. Tobalaba 1621, Providencia, Santiago.  |
| `{{FOLIO}}`      | `folio`             | 00123                                      |

**Ejemplo API request body:**
```json
{
  "requests": [
    {
      "replaceAllText": {
        "containsText": { "text": "{{NOMBRE}}", "matchCase": true },
        "replaceText": "Juan Pérez"
      }
    },
    {
      "replaceAllText": {
        "containsText": { "text": "{{RUT}}", "matchCase": true },
        "replaceText": "12.345.678-9"
      }
    }
  ]
}
```

---

### 4. Google Slides — Insertar Imagen QR

- **Tipo:** Google Slides batch update
- **Acción:** `replaceAllShapesWithImage`

En la plantilla, crear un placeholder shape con el texto `{{QR}}`. Este nodo lo reemplaza:

```json
{
  "requests": [
    {
      "replaceAllShapesWithImage": {
        "imageUrl": "{{$json.qr_url}}",
        "containsText": { "text": "{{QR}}", "matchCase": true },
        "imageReplaceMethod": "CENTER_INSIDE"
      }
    }
  ]
}
```

---

### 5. Google Drive — Exportar como PDF

- **Tipo:** Google Drive node (o HTTP Request)
- **Acción:** Export file
- **File ID:** ID de la copia creada en el paso 2
- **MIME Type:** `application/pdf`

**API:**
```
GET https://www.googleapis.com/drive/v3/files/{fileId}/export?mimeType=application/pdf
```

---

### 6. HTTP Request — Enviar PDF al Callback de Selcap

- **Tipo:** HTTP Request
- **Método:** POST
- **URL:** `{{$json.callback_url}}` (viene en el payload original)
- **Content-Type:** `multipart/form-data`
- **Headers:**
  - `X-Callback-Secret`: `{{$json.callback_secret}}`
- **Body:**
  - `attempt_id`: `{{$json.attempt_id}}`
  - `n8n_execution_id`: `{{$execution.id}}`
  - `pdf_file`: (binary del PDF del paso anterior)

---

### 7. (Opcional) Google Drive — Eliminar Copia Temporal

- **Tipo:** Google Drive node
- **Acción:** Delete file
- **File ID:** ID de la copia del paso 2

Esto evita acumular copias temporales en Drive. Si prefieres mantenerlas como
respaldo, omite este nodo.

---

## Placeholders en la Plantilla de Google Slides

La plantilla debe contener estos textos exactos en las posiciones donde van los datos variables:

| Placeholder      | Ubicación en el certificado                  |
|------------------|----------------------------------------------|
| `{{NOMBRE}}`     | Después de "A don(ña):"                      |
| `{{RUT}}`        | En la línea R.U.T.                           |
| `{{CURSO}}`      | Nombre del curso (en itálicas/comillas)      |
| `{{FECHA}}`      | Fecha de realización                         |
| `{{HORAS}}`      | N° Total de Horas                            |
| `{{DIRECCION}}`  | Dirección del centro                         |
| `{{FOLIO}}`      | N° Folio                                     |
| `{{QR}}`         | Shape/imagen placeholder para el código QR   |

**Elementos fijos en la plantilla** (NO son placeholders):
- Logo Selcap
- Sello NCh 2728 del Ministerio
- Firma de Gerardo Woywood (imagen)
- Sello de la empresa "Selcap Capacitación Ltda. RUT: 77.578.690-6"
- Texto legal de conformidad
- Bordes y diseño general

---

## Manejo de Errores

Si algún paso falla, agregar un nodo **Error Trigger** que envíe al callback:

```json
POST {{callback_url}}
Content-Type: application/json
X-Callback-Secret: {{callback_secret}}

{
  "attempt_id": 123,
  "error": "Descripción del error de n8n"
}
```

Esto actualiza el registro de certificado a `status = 'error'` en la base de datos,
permitiendo al admin reintentar desde el panel.

---

## Diagrama Visual

```
[Webhook] → [Copy Slides] → [Replace Text] → [Insert QR] → [Export PDF] → [POST to Selcap] → [Delete Copy]
                                                                                ↓
                                                                    (Error) → [POST error to Selcap]
```
