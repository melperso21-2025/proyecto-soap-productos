# Proyecto SOAP Productos

Tarea de **Aplicaciones Distribuidas** — servicio web SOAP en Node.js para
gestión de productos, con dos clientes en lenguajes distintos (Python y PHP),
cada uno con su propio front web, y una API REST adicional sobre el mismo
backend.

**Equipo:** Ismael · Alexandra · Israel
*(Diego formaba parte del equipo original pero ya no participa en el
proyecto; su trabajo de cliente PHP quedó a cargo de Ismael.)*

## Estructura

```
proyecto-soap-productos/
├── servidor/            Servidor SOAP + REST (Node.js + express + soap)
│   ├── server.js
│   ├── productos.wsdl
│   ├── operaciones.js          logica de negocio compartida por SOAP y REST
│   ├── rest.js                 rutas REST (GET/POST/PATCH/DELETE /api/...)
│   ├── validaciones.js
│   ├── data/productos.js       almacén en memoria (fuente de verdad)
│   └── db/supabaseClient.js    persistencia opcional en Supabase
├── cliente-python/      Cliente Python (zeep) + front Flask   — Israel (puerto 5000)
├── cliente-php/
│   ├── client.php       Cliente de consola (SoapClient nativo)
│   └── front/           Front web en PHP puro                — Ismael (puerto 5001)
├── cliente-csharp/      Cliente C# (.NET, dotnet-svcutil)      — Alexandra
├── evidencias/           Capturas: servidor, clientes, SoapUI
├── informe/              Informe técnico en PDF
├── GUIA_SUPABASE.md      Cómo crear el proyecto y dar acceso al equipo
├── GUIA_GITHUB.md        Cómo subir el repo y organizar el trabajo en equipo
├── GUIA_DESPLIEGUE.md    Cómo desplegar el servidor en Render
├── ESTADO_PROYECTO.md    Checklist de lo hecho / lo que falta
└── PROMPTS_EQUIPO.md     Prompts guiados para cada integrante
```

## Cómo levantar todo

**1. Servidor (SOAP + REST), siempre primero:**

```bash
cd servidor
npm install
cp .env.example .env    # opcional: solo si ya configuraron Supabase (ver GUIA_SUPABASE.md)
npm start
```

- WSDL: [http://localhost:8000/productos?wsdl](http://localhost:8000/productos?wsdl)
- API REST: [http://localhost:8000/api/productos](http://localhost:8000/api/productos)
- Si `.env` no existe o Supabase no responde, el servidor sigue funcionando
  **100% en memoria** — nunca depende de un servicio externo para cumplir el
  requisito obligatorio de la tarea.

**2. Los dos fronts, en paralelo (puertos distintos, no chocan entre sí):**

```bash
# Front Python (Flask)
cd cliente-python
pip install -r requirements.txt
python app.py            # http://127.0.0.1:5000

# Front PHP (servidor embebido de PHP)
cd cliente-php/front
php -S localhost:5001    # http://localhost:5001
```

Cada front identifica en su barra lateral qué lenguaje lo implementa
(badge "Cliente Python · Flask + zeep" / "Cliente PHP · SoapClient nativo"),
consume el WSDL directamente para las 6 operaciones de productos, y consume
`GET /api/equipo` (la API REST) para mostrar quién hizo el proyecto —
demostrando en un mismo front el uso de los dos protocolos.

**3. Clientes de consola (opcional, sin interfaz web):**

```bash
cd cliente-python && python client.py
cd cliente-php && php client.php
cd cliente-csharp && dotnet run
```

Todos demuestran las 7 pruebas mínimas que pide el enunciado: registro de 2
productos, consulta existente/inexistente, listado, actualización de stock,
cálculo de valor de inventario y eliminación (incluyendo un caso incorrecto
de cada operación clave).

El cliente C# usa un proxy generado con `dotnet-svcutil` (herramienta local
del repo, ver `dotnet-tools.json`) a partir del WSDL — mismo rol que `zeep`
en Python o `SoapClient` en PHP. Si necesitas regenerarlo (por ejemplo,
después de un cambio en `productos.wsdl`):

```bash
cd cliente-csharp
dotnet tool restore
dotnet tool run dotnet-svcutil "http://localhost:8000/productos?wsdl" \
  --outputFile ProductosServiceReference.cs \
  --namespace "*,ClienteSoap.ProductosService"
```

## Las 6 operaciones del servicio (SOAP y REST)

| Operación | SOAP | REST | Entrada | Salida |
|---|---|---|---|---|
| Registrar | `RegistrarProducto` | `POST /api/productos` | código, nombre, categoría, precio, cantidad | estado, mensaje |
| Consultar | `ConsultarProducto` | `GET /api/productos/:codigo` | código | estado, mensaje, datos del producto |
| Listar | `ListarProductos` | `GET /api/productos` | — | lista de productos |
| Actualizar stock | `ActualizarStock` | `PATCH /api/productos/:codigo/stock` | código, cantidad | estado, mensaje, cantidadActualizada |
| Calcular valor | `CalcularValorInventario` | `GET /api/productos/:codigo/valor` | código | nombre, precio, cantidad, valorTotal |
| Eliminar | `EliminarProducto` | `DELETE /api/productos/:codigo` | código | estado, mensaje |

Ambos protocolos comparten la misma lógica de validación
(`servidor/operaciones.js`) — nunca deberían responder distinto ante los
mismos datos.

## Pruebas en SoapUI

1. **New SOAP Project** → WSDL: `http://localhost:8000/productos?wsdl`.
2. SoapUI genera automáticamente una petición de ejemplo por operación.
3. Para cada una de las 6 operaciones, correr un caso correcto y al menos un
   caso incorrecto (código duplicado, código inexistente, precio negativo,
   cantidad negativa, campos vacíos) y guardar captura en `evidencias/`.

## Guías del proyecto

- [`GUIA_SUPABASE.md`](GUIA_SUPABASE.md) — crear el proyecto, ejecutar el
  esquema (`servidor/db/schema.sql` y `servidor/db/schema_integrantes.sql`)
  e invitar al equipo en el plan gratuito.
- [`GUIA_GITHUB.md`](GUIA_GITHUB.md) — repo, ramas por responsable, commits en
  español y checklist de entrega.
- [`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md) — desplegar el servidor en Render
  para tener un ambiente accesible por todo el equipo.
- [`ESTADO_PROYECTO.md`](ESTADO_PROYECTO.md) — qué ya está hecho y qué falta,
  por persona.

## Informe técnico

Va en `informe/` en formato PDF, con la estructura de la sección 12 del
enunciado (introducción, marco teórico, arquitectura, desarrollo, evidencias,
análisis, conclusiones, recomendaciones, bibliografía APA 7ª edición).
