# Proyecto SOAP Productos

Tarea de **Aplicaciones Distribuidas** — servicio web SOAP en Node.js para
gestión de productos, con dos clientes en lenguajes distintos (Python y PHP).

**Equipo:** Ismael · Alexandra · Israel · Diego

## Estructura

```
proyecto-soap-productos/
├── servidor/            Servidor SOAP (Node.js + express + soap)
│   ├── server.js
│   ├── productos.wsdl
│   ├── validaciones.js
│   ├── data/productos.js       almacén en memoria (fuente de verdad)
│   └── db/supabaseClient.js    persistencia opcional en Supabase
├── cliente-python/      Cliente en Python (zeep)         — Israel
├── cliente-php/         Cliente en PHP (SoapClient)       — Diego
├── dashboard/           Panel web opcional (bonus, ver wireframe)
├── evidencias/          Capturas: servidor, clientes, SoapUI
├── informe/             Informe técnico en PDF
├── GUIA_SUPABASE.md     Cómo crear el proyecto y dar acceso al equipo
├── GUIA_GITHUB.md       Cómo subir el repo y organizar el trabajo en equipo
└── GUIA_DESPLIEGUE.md   Cómo desplegar el servidor en Render
```

## Cómo levantar el servidor

```bash
cd servidor
npm install
cp .env.example .env    # opcional: solo si ya configuraron Supabase (ver GUIA_SUPABASE.md)
npm start
```

- WSDL: [http://localhost:8000/productos?wsdl](http://localhost:8000/productos?wsdl)
- Si `.env` no existe o Supabase no responde, el servidor sigue funcionando
  **100% en memoria** — nunca depende de un servicio externo para cumplir el
  requisito obligatorio de la tarea.

## Cómo correr los clientes

Con el servidor corriendo en otra terminal:

```bash
# Cliente Python
cd cliente-python
pip install -r requirements.txt
python client.py

# Cliente PHP (requiere la extensión "soap" habilitada en php.ini)
cd cliente-php
php client.php
```

Ambos clientes demuestran las 7 pruebas mínimas que pide el enunciado: registro
de 2 productos, consulta existente/inexistente, listado, actualización de
stock, cálculo de valor de inventario y eliminación (incluyendo un caso
incorrecto de cada operación clave).

## Las 6 operaciones del servicio

| Operación | Entrada | Salida |
|---|---|---|
| `RegistrarProducto` | código, nombre, categoría, precio, cantidad | estado, mensaje |
| `ConsultarProducto` | código | estado, mensaje, datos del producto |
| `ListarProductos` | — | lista de productos |
| `ActualizarStock` | código, cantidad | estado, mensaje, cantidadActualizada |
| `CalcularValorInventario` | código | nombre, precio, cantidad, valorTotal |
| `EliminarProducto` | código | estado, mensaje |

## Pruebas en SoapUI

1. **New SOAP Project** → WSDL: `http://localhost:8000/productos?wsdl`.
2. SoapUI genera automáticamente una petición de ejemplo por operación.
3. Para cada una de las 6 operaciones, correr un caso correcto y al menos un
   caso incorrecto (código duplicado, código inexistente, precio negativo,
   cantidad negativa, campos vacíos) y guardar captura en `evidencias/`.

## Guías del proyecto

- [`GUIA_SUPABASE.md`](GUIA_SUPABASE.md) — crear el proyecto, ejecutar el
  esquema (`servidor/db/schema.sql`) e invitar al equipo en el plan gratuito.
- [`GUIA_GITHUB.md`](GUIA_GITHUB.md) — repo, ramas por responsable, commits en
  español y checklist de entrega.
- [`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md) — desplegar el servidor en Render
  para tener un ambiente accesible por todo el equipo.

## Informe técnico

Va en `informe/` en formato PDF, con la estructura de la sección 12 del
enunciado (introducción, marco teórico, arquitectura, desarrollo, evidencias,
análisis, conclusiones, recomendaciones, bibliografía APA 7ª edición).
