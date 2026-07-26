# Checklist de pruebas SoapUI — Alexandra (feature/validaciones)

Servidor corriendo en `http://localhost:8000/productos`, WSDL en
`http://localhost:8000/productos?wsdl`.

Ejecutar EN ESTE ORDEN (el estado vive en memoria, se reinicia si reinicias
el servidor). Usamos un solo producto (`P001`) para no complicar el estado.

Por cada caso: marcar la casilla, capturar el **request XML** y el
**response XML** en SoapUI, y guardar el PNG/JPG en esta carpeta como
`NN_operacion_resultado.png` (ej. `01_RegistrarProducto_correcto.png`).

- [ ] Captura extra: WSDL importado en SoapUI (árbol con las 6 operaciones)

## 1. [ ] RegistrarProducto — correcto
```
codigo: P001
nombre: Mouse inalámbrico
categoria: Perifericos
precio: 25.50
cantidad: 100
```
Esperado: `estado=true`, `mensaje="Producto P001 registrado correctamente"`

## 2. [ ] RegistrarProducto — incorrecto (precio <= 0)
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

## 3. [ ] ListarProductos — correcto
Sin parámetros. Esperado: array `productos` con un solo elemento, `P001`
(P002 nunca se guardó porque fue rechazado en el paso 2).

## 4. [ ] ConsultarProducto — correcto
```
codigo: P001
```
Esperado: `estado=true`, devuelve los 5 campos de P001.

## 5. [ ] ConsultarProducto — incorrecto (no existe)
```
codigo: P999
```
Esperado: `estado=false`, `mensaje="El producto con el código P999 no existe"`

## 6. [ ] ActualizarStock — correcto
```
codigo: P001
cantidad: 150
```
Esperado: `estado=true`, `cantidadActualizada=150`

## 7. [ ] ActualizarStock — incorrecto (cantidad < 0)
```
codigo: P001
cantidad: -5
```
Esperado: `estado=false`, `mensaje="La cantidad debe ser un número entero igual o mayor que cero"`

## 8. [ ] CalcularValorInventario — correcto
```
codigo: P001
```
Esperado: `estado=true`, `valorTotal = 25.50 * 150 = 3825`

## 9. [ ] CalcularValorInventario — incorrecto (no existe)
```
codigo: P999
```
Esperado: `estado=false`, `mensaje="El producto con el código P999 no existe"`

## 10. [ ] EliminarProducto — incorrecto (código vacío)
```
codigo: (dejar el campo vacío, "")
```
Esperado: `estado=false`, `mensaje="El código del producto no puede estar vacío"`

> Hacer este ANTES del eliminar correcto para no perder P001 todavía.

## 11. [ ] EliminarProducto — correcto
```
codigo: P001
```
Esperado: `estado=true`, `mensaje="Producto P001 eliminado correctamente"`

## 12. [ ] ListarProductos — caso estructural (SOAP Fault)
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
7. Para tener 12 requests documentados, duplica cada `Request 1` (botón
   derecho → Clone) y renómbralos `..._correcto` / `..._incorrecto`.
