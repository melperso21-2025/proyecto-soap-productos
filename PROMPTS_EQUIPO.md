# Prompts para Alexandra, Israel y Diego

Estos son los mensajes que cada uno le puede pegar a su asistente de IA
(Claude Code u otro) para avanzar en su parte del proyecto. Cada prompt es
autocontenido — no depende de esta conversación, así que funciona igual sin
importar quién lo use ni en qué momento.

## Cómo usarlos (los 3 siguen estos mismos pasos)

1. Clona el repo si no lo has hecho:
   ```bash
   git clone https://github.com/melperso21-2025/proyecto-soap-productos.git
   cd proyecto-soap-productos
   ```
2. Párate en tu rama (ya existe en GitHub, solo la "bajas"):
   ```bash
   git fetch origin
   git checkout feature/tu-rama    # la que te toque, ver tabla abajo
   ```
3. Copia `servidor/.env.example` a `servidor/.env` y pega la `SUPABASE_KEY`
   que te pasó Ismael por el canal privado del equipo.
4. Abre tu asistente de IA **en la carpeta del proyecto ya clonado** y
   pégale el prompt que te corresponde (abajo).
5. Lee las respuestas con calma. La idea NO es que copies y pegues sin
   entender — en la defensa virtual te van a pedir explicar y modificar tu
   parte en vivo (1.5 pts de la rúbrica dependen de eso).

| Quién | Rama | Prompt |
|---|---|---|
| Alexandra | `feature/validaciones` | [Ir al prompt](#prompt-para-alexandra) |
| Israel | `feature/cliente-python` | [Ir al prompt](#prompt-para-israel) |
| Diego | `feature/cliente-php` | [Ir al prompt](#prompt-para-diego) |

---

## Prompt para Alexandra

**Tu responsabilidad:** validaciones del servidor, mensajes de error,
pruebas del WSDL en SoapUI, y empaquetar el entregable final.

```text
Estoy en el proyecto proyecto-soap-productos: un servicio SOAP de gestión de
productos en Node.js (tarea de Aplicaciones Distribuidas), con un archivo
WSDL, un servidor que implementa 6 operaciones, y clientes en Python y PHP.
Estoy en la rama feature/validaciones. Mi parte del equipo es: las
validaciones del servidor, los mensajes de error, las pruebas en SoapUI, y
armar el zip de entrega final. Soy estudiante y estoy aprendiendo — no
asumas que el código ya está bien solo porque corre, revísalo conmigo con
sentido crítico y explícame el porqué de cada cosa.

Ayúdame con esto, en orden:

1. Lee servidor/server.js y servidor/validaciones.js. Explícame, operación
   por operación (RegistrarProducto, ConsultarProducto, ListarProductos,
   ActualizarStock, CalcularValorInventario, EliminarProducto), qué
   validación se aplica y en qué línea del código pasa. Quiero poder
   explicar esto en vivo en la defensa virtual.

2. Compara esas validaciones contra la sección 11 del enunciado de la tarea
   (adjunto el resumen abajo) y dime si falta cubrir algo:
   - El código del producto no esté vacío.
   - El código del producto no se repita.
   - El nombre no esté vacío.
   - La categoría no esté vacía.
   - El precio sea un número mayor que cero.
   - La cantidad sea un número entero igual o mayor que cero.
   - El producto exista antes de consultarlo, actualizarlo o eliminarlo.

3. Ayúdame a armar un checklist de pruebas para SoapUI: necesito un caso
   correcto y un caso incorrecto por cada una de las 6 operaciones (mínimo
   12 casos). Guárdalo en evidencias/checklist_soapui.md, con una casilla
   [ ] por caso para ir marcando conforme capturo cada evidencia (WSDL
   importado, request XML, response XML).

4. Cuando ya tengamos todo probado, ayúdame a armar el zip final
   Apellido_Alexandra_TareaSOAP.zip con lo que pide la sección 13 del
   enunciado (código, WSDL, package.json, README, evidencias, informe PDF).

Ve paso a paso, no hagas todo de una — quiero ir entendiendo cada parte.
```

---

## Prompt para Israel

**Tu responsabilidad:** cliente Python (zeep), y el marco teórico del
informe (SOAP, XML, WSDL, cliente-servidor, SOAP vs REST).

```text
Estoy en el proyecto proyecto-soap-productos: un servicio SOAP de gestión de
productos en Node.js (tarea de Aplicaciones Distribuidas), con un archivo
WSDL en servidor/productos.wsdl y un servidor con 6 operaciones. Estoy en la
rama feature/cliente-python. Mi parte del equipo es: el cliente en Python
(cliente-python/client.py, usando la librería zeep) y el marco teórico del
informe. Soy estudiante y estoy aprendiendo — quiero entender, no solo que
funcione.

Ayúdame con esto, en orden:

1. Lee cliente-python/client.py y explícame, línea por línea en las partes
   clave, cómo zeep se conecta al WSDL (Client(WSDL_URL)) y cómo invoca cada
   una de las 6 operaciones del servicio (servicio.RegistrarProducto(...),
   etc.). Quiero poder explicar esto en vivo en la defensa virtual y también
   modificarlo si el profesor pide agregar un caso de prueba nuevo.

2. Corre el cliente conmigo (con el servidor ya levantado en otra terminal
   con "npm start" desde la carpeta servidor/) y ayúdame a interpretar la
   salida de cada una de las 7 demostraciones que pide el enunciado:
   registro de 2 productos, consulta existente, consulta inexistente,
   listado, actualización de stock, cálculo de inventario, eliminación.

3. Ayúdame a preparar mis notas para el marco teórico del informe técnico
   (sección 12 del enunciado), cubriendo: aplicaciones distribuidas,
   servicios web, protocolo SOAP, lenguaje XML, archivo WSDL, arquitectura
   cliente-servidor, y diferencias entre SOAP y REST. Quiero que me
   expliques cada concepto con ejemplos de ESTE proyecto (no genéricos), y
   que me ayudes a organizar mis propias notas en viñetas — el texto final
   del informe lo redacto yo con mis palabras.

Ve paso a paso, y hazme preguntas de vez en cuando para confirmar que
entendí antes de seguir.
```

---

## Prompt para Diego

**Tu responsabilidad:** cliente PHP (SoapClient nativo), y el marco teórico
del informe (mismo alcance que Israel).

```text
Estoy en el proyecto proyecto-soap-productos: un servicio SOAP de gestión de
productos en Node.js (tarea de Aplicaciones Distribuidas), con un archivo
WSDL en servidor/productos.wsdl y un servidor con 6 operaciones. Estoy en la
rama feature/cliente-php. Mi parte del equipo es: el cliente en PHP
(cliente-php/client.php, usando la clase nativa SoapClient) y el marco
teórico del informe. Soy estudiante y estoy aprendiendo — quiero entender,
no solo que funcione.

Ayúdame con esto, en orden:

1. Antes que nada, revisa si mi PHP tiene habilitada la extensión "soap"
   (php -m debe mostrarla). Si no aparece, ayúdame a habilitarla en mi
   php.ini local (buscar la línea ";extension=soap" y quitarle el ";").

2. Lee cliente-php/client.php y explícame, en las partes clave, cómo
   SoapClient se conecta al WSDL (new SoapClient(WSDL_URL, [...])) y cómo
   invoca cada una de las 6 operaciones del servicio. Quiero poder explicar
   esto en vivo en la defensa virtual y también modificarlo si el profesor
   pide agregar un caso de prueba nuevo.

3. Corre el cliente conmigo (con el servidor ya levantado en otra terminal
   con "npm start" desde la carpeta servidor/) y ayúdame a interpretar la
   salida de cada una de las 7 demostraciones que pide el enunciado:
   registro de 2 productos, consulta existente, consulta inexistente,
   listado, actualización de stock, cálculo de inventario, eliminación.

4. Ayúdame a preparar mis notas para el marco teórico del informe técnico
   (sección 12 del enunciado), cubriendo: aplicaciones distribuidas,
   servicios web, protocolo SOAP, lenguaje XML, archivo WSDL, arquitectura
   cliente-servidor, y diferencias entre SOAP y REST. Quiero que me
   expliques cada concepto con ejemplos de ESTE proyecto (no genéricos), y
   que me ayudes a organizar mis propias notas en viñetas — el texto final
   del informe lo redacto yo con mis palabras. Coordina conmigo con Israel
   para no repetir exactamente lo mismo en el informe.

Ve paso a paso, y hazme preguntas de vez en cuando para confirmar que
entendí antes de seguir.
```

---

## Nota para todos

Ninguno de estos prompts reemplaza leer el enunciado completo de la tarea ni
el archivo [`ESTADO_PROYECTO.md`](ESTADO_PROYECTO.md) (ahí está el objetivo
general, los objetivos específicos, y el resto del checklist del equipo). Si
la IA les propone cambiar algo del servidor o del WSDL, avisen en el chat del
equipo antes de mergear a `dev` — Ismael y Alexandra ya trabajaron esa parte
juntos.
