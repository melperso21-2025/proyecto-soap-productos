# Proyecto SOAP Productos

Tarea de **Aplicaciones Distribuidas** — servicio web SOAP en Node.js para
gestión de productos, con **tres clientes** en lenguajes distintos (Python,
PHP y C#), cada uno con su propio front web, y una API REST adicional sobre
el mismo backend.

Como mejora adicional, el sistema incluye gestión de **locales (sucursales)
con ubicación geográfica y stock por tienda**, con su propio mapa
interactivo — expuesta también por **SOAP** (`LocalesService`, un segundo
servicio dentro del mismo `productos.wsdl`), sin tocar en absoluto las 6
operaciones calificadas de `ProductosService`.

**Equipo:** Ismael · Alexandra · Israel
*(Diego formaba parte del equipo original pero ya no participa en el
proyecto; su trabajo de cliente PHP quedó a cargo de Ismael.)*

- **Ismael** — servidor SOAP + REST, front PHP, diseño y desarrollo de la
  mejora de locales/mapa/stock por tienda (SOAP `LocalesService`) en los 3 fronts.
- **Alexandra** — cliente C# de consola con mapa interactivo (idea y
  desarrollo original) y validaciones/pruebas en SoapUI. El front web de C#
  parte de esa misma idea; Ismael lo llevó a un panel web completo,
  replicando el mismo patrón que los fronts de Python y PHP.
- **Israel** — cliente y front web de Python, y revisión general (check) de
  todo el sistema antes de la entrega.

## URLs del sistema (con todo corriendo en local)

| Servicio | URL |
|---|---|
| WSDL | http://localhost:8000/productos?wsdl |
| SOAP — `ProductosService` (6 operaciones calificadas) | http://localhost:8000/productos |
| SOAP — `LocalesService` (mejora adicional) | http://localhost:8000/locales |
| API REST (info + endpoints) | http://localhost:8000/ |
| Front Python (Flask + zeep) | http://localhost:5000 |
| Front PHP (SoapClient nativo) | http://localhost:5001 |
| Front C# (ASP.NET Core Razor Pages) | http://localhost:5002 |

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
├── cliente-csharp/
│   ├── Program.cs       Cliente de consola + mapa (dotnet-svcutil) — Alexandra
│   └── front/           Front web en ASP.NET Core Razor Pages     — puerto 5002
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

**2. Los tres fronts, en paralelo (puertos distintos, no chocan entre sí):**

```bash
# Front Python (Flask)
cd cliente-python
pip install -r requirements.txt
python app.py            # http://127.0.0.1:5000

# Front PHP (servidor embebido de PHP)
cd cliente-php/front
php -S localhost:5001    # http://localhost:5001

# Front C# (ASP.NET Core)
cd cliente-csharp/front
dotnet run --urls http://localhost:5002    # http://localhost:5002
```

Cada front identifica en su barra lateral qué lenguaje lo implementa (badge
"Cliente Python · Flask + zeep" / "Cliente PHP · SoapClient nativo" /
"Cliente C# · ASP.NET Core + dotnet-svcutil"), con un color de acento propio
(verde azulado, índigo y naranja respectivamente). Los tres consumen el WSDL
directamente para las 6 operaciones de productos **y** para la mejora
adicional de locales/mapa (`LocalesService`), y usan `GET /api/equipo` (REST)
solo para mostrar quién hizo el proyecto — demostrando en un mismo front el
uso de los dos protocolos.

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

## Mejora adicional: locales, mapa y stock por tienda (SOAP)

Un segundo servicio SOAP, `LocalesService`, declarado en el mismo
`productos.wsdl` con su propio `portType`/`binding`/`service`, en su propio
endpoint (`/locales`). Las 6 operaciones de `ProductosService` no se tocaron
para nada — esto es una extensión aparte del mismo contrato.

| Operación | Entrada | Salida |
|---|---|---|
| `CrearLocal` | nombre, lat, lng | estado, mensaje, local |
| `ListarLocales` | — | lista de locales |
| `ActualizarLocal` | id, nombre, lat, lng | estado, mensaje, local |
| `EliminarLocal` | id | estado, mensaje |
| `AsignarStockLocal` | código, localId, cantidad | estado, mensaje |
| `ConsultarStockPorLocal` | código | estado, mensaje, stock por local |
| `ObtenerMapaLocales` | — | todas las tiendas con sus productos y valor |
| `CalcularValorPorLocal` | localId | nombre de tienda, productos, valor total |

`servidor/operacionesLocales.js` concentra esta lógica (mismo patrón que
`operaciones.js` para las 6 obligatorias), reutilizada tanto por SOAP
(`server.js`) como por los endpoints REST equivalentes en `rest.js` (estos
últimos quedan solo como comodidad para probar con Postman/curl — los 3
fronts usan SOAP).

Requiere Supabase configurado (`servidor/db/schema_locales.sql`): un local
con su ubicación geográfica no tiene sentido como dato en memoria pura.

**Nota técnica:** `node-soap` resuelve a qué servicio pertenece una
petición entrante solo por la ruta HTTP, no por la operación solicitada — si
dos `service` del mismo WSDL compartieran la misma dirección, siempre
despacharía al primero. Por eso `LocalesService` vive en `/locales` y no en
`/productos`.

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
