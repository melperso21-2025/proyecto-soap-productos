# Checklist de pruebas SoapUI — Alexandra (feature/validaciones)

Servidor corriendo en `http://localhost:8000/productos`, WSDL en
`http://localhost:8000/productos?wsdl`.

Ejecutar EN ESTE ORDEN. Usamos un solo producto (`P001`) para no complicar
el estado.

> ⚠️ **Supabase compartido:** si tu `servidor/.env` ya tiene la
> `SUPABASE_KEY` real (la misma de los 4 del equipo), el servidor persiste
> cada operación en la tabla `productos` de Supabase — que es compartida.
> Si reinicias el servidor, recarga el estado desde ahí, no desde cero. Y
> si alguien más del equipo registra productos de prueba al mismo tiempo
> que tú, `ListarProductos` (paso 3) podría no mostrar solo `P001`.
> Este checklist está diseñado para dejar la tabla limpia al final (el
> último paso elimina `P001`), pero mientras lo corres: avisa en el chat
> del equipo que estás probando, o corre el servidor sin `.env`
> configurado (memoria pura) si quieres control total del estado.

Por cada caso: marcar la casilla, capturar el **request XML** y el
**response XML** en SoapUI, y guardar el PNG/JPG en esta carpeta como
`NN_operacion_resultado.png` (ej. `01_RegistrarProducto_correcto.png`).

- [x] Captura extra: WSDL importado en SoapUI (árbol con las 6 operaciones)

## 1. [x] RegistrarProducto — correcto
```
codigo: P001
nombre: Mouse inalámbrico
categoria: Perifericos
precio: 25.50
cantidad: 100
```
Esperado: `estado=true`, `mensaje="Producto P001 registrado correctamente"`

## 2. [x] RegistrarProducto — incorrecto (precio <= 0)
```
codigo: P002
nombre: Monitor
categoria: Pantallas
precio: 0
cantidad: 10
```
Esperado: `estado=false`, `mensaje="El precio debe ser un número mayor que cero"`

> Nota para la defensa: también puedes repetir este caso dejando `codigo`,
> `nombre` o `categoria` vacíos, o `cantidad=-1`, para mostrar que
> `validarRegistro` los cubre todos — pero con documentar uno basta para
> la entrega.

## 3. [x] ListarProductos — correcto
Sin parámetros. Esperado: array `productos` con un solo elemento, `P001`
(P002 nunca se guardó porque fue rechazado en el paso 2).

## 4. [x] ConsultarProducto — correcto
```
codigo: P001
```
Esperado: `estado=true`, devuelve los 5 campos de P001.

## 5. [x] ConsultarProducto — incorrecto (no existe)
```
codigo: P999
```
Esperado: `estado=false`, `mensaje="El producto con el código P999 no existe"`

## 6. [x] ActualizarStock — correcto
```
codigo: P001
cantidad: 150
```
Esperado: `estado=true`, `cantidadActualizada=150`

## 7. [x] ActualizarStock — incorrecto (cantidad < 0)
```
codigo: P001
cantidad: -5
```
Esperado: `estado=false`, `mensaje="La cantidad debe ser un número entero igual o mayor que cero"`

## 8. [x] CalcularValorInventario — correcto
```
codigo: P001
```
Esperado: `estado=true`, `valorTotal = 25.50 * 150 = 3825`

## 9. [x] CalcularValorInventario — incorrecto (no existe)
```
codigo: P999
```
Esperado: `estado=false`, `mensaje="El producto con el código P999 no existe"`

## 10. [x] RegistrarProducto — incorrecto (código duplicado)
```
codigo: P001
nombre: Mouse inalámbrico
categoria: Perifericos
precio: 25.50
cantidad: 100
```
(Los mismos datos del caso 1 — P001 todavía existe porque no lo hemos
eliminado.)

Esperado: `estado=false`, `mensaje="El producto con el código P001 ya existe"`

> Este caso cubre la sección 8.1 del enunciado ("Evitar el registro de
> códigos duplicados") como requisito propio, distinto del caso 2
> (precio ≤ 0). La validación vive en `server.js:31`
> (`productos.existe(args.codigo)`), no en `validaciones.js`.

## 11. [x] EliminarProducto — incorrecto (código vacío)
```
codigo: (dejar el campo vacío, "")
```
Esperado: `estado=false`, `mensaje="El código del producto no puede estar vacío"`

> Hacer este ANTES del eliminar correcto para no perder P001 todavía.

## 12. [x] EliminarProducto — correcto
```
codigo: P001
```
Esperado: `estado=true`, `mensaje="Producto P001 eliminado correctamente"`

> Este paso también limpia la tabla compartida de Supabase — al terminar
> este caso, la tabla `productos` queda sin `P001`.

## 13. [x] ListarProductos — caso estructural (SOAP Fault)
En SoapUI, borra o corrompe una etiqueta XML del request (ej. deja
`<soapenv:Envelope>` sin cerrar, o quita el `<soapenv:Body>`) y envíalo.

Esperado: SoapUI muestra un **SOAP Fault** (error de XML/schema), no un
`estado=false` de negocio. Sirve para explicar en la defensa que
`ListarProductos` no tiene reglas de validación propias (no recibe
parámetros), pero el motor `soap`/SoapUI igual valida la forma del XML
por debajo.

---

## Pasos para importar en SoapUI

1. Abrir SoapUI → **File → New SOAP Project**.
2. Nombre: `ProductosService`. Initial WSDL:
   `http://localhost:8000/productos?wsdl` (con el servidor corriendo).
3. Marcar "Create Requests" → Finish.
4. SoapUI genera un árbol: `ProductosService > ProductosBinding >
   RegistrarProducto > Request 1` (y así por cada una de las 6 operaciones).
5. Abrir cada `Request 1`, reemplazar los `?` por los valores de arriba,
   click en el botón ▶ verde para enviar.
6. El panel derecho muestra el response XML — ahí capturas la evidencia.
7. Para tener los 13 requests documentados, duplica cada `Request 1` (botón
   derecho → Clone) y renómbralos `..._correcto` / `..._incorrecto`.
