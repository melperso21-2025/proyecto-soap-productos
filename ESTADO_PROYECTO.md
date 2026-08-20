# Estado del proyecto — SOAP Productos

> Última actualización: ver historial de commits de este archivo.
> Léanlo antes de tocar código: dice qué ya existe, qué falta, y qué te toca
> a ti específicamente.

## Equipo

| Integrante | Responsabilidad principal | Rama de trabajo |
|---|---|---|
| **Ismael** | Servidor SOAP (server.js), WSDL, API REST, front web PHP | `feature/servidor` |
| **Alexandra** | Validaciones, mensajes de error, pruebas del WSDL en SoapUI, empaquetado final | `feature/validaciones` |
| **Israel** | Cliente y front web Python, marco teórico del informe | `feature/cliente-python` |
| **Equipo** | Pruebas SoapUI, revisión cruzada de código, ensayo de defensa | — |

> ⚠️ **Diego ya no forma parte del equipo.** Su trabajo original (cliente
> PHP, `feature/cliente-php`) pasó a Ismael, quien además lo amplió con un
> front web (antes solo era un cliente de consola). El equipo quedó en 3
> personas.
>
> La modalidad de "grupo de 4" se había confirmado antes con el docente —
> **avísenle del cambio a 3 integrantes** antes de la entrega, para no tener
> sorpresas en la defensa virtual.

## Objetivo general

Desarrollar una aplicación distribuida cliente-servidor que implemente un
servicio web SOAP en Node.js, usando un WSDL para definir las 6 operaciones
de gestión de productos, con clientes en dos lenguajes distintos (Python y
PHP) y pruebas en SoapUI.

## Objetivos específicos

- Comprender el funcionamiento del protocolo SOAP y la estructura de un WSDL.
- Implementar un servidor SOAP en Node.js con las 6 operaciones obligatorias.
- Desarrollar clientes en Python y PHP que consuman ese servicio.
- Validar datos de entrada y controlar errores (duplicados, inexistentes, etc.).
- Probar cada operación en SoapUI con un caso correcto y uno incorrecto.
- Documentar todo el proceso en un informe técnico.

## Ya está hecho ✅

- [x] `productos.wsdl` — las 6 operaciones (types, message, portType, binding,
      service, port), validado y funcionando en `http://localhost:8000/productos?wsdl`.
- [x] `servidor/server.js` + `servidor/operaciones.js` — las 6 operaciones
      probadas de punta a punta: RegistrarProducto, ConsultarProducto,
      ListarProductos, ActualizarStock, CalcularValorInventario,
      EliminarProducto.
- [x] `servidor/validaciones.js` — código no vacío/no duplicado, precio > 0,
      cantidad entera ≥ 0, producto debe existir. (**Alexandra: ya lo
      revisaste y agregaste el caso de código duplicado, gracias**).
- [x] Persistencia híbrida con Supabase — memoria como fuente de verdad,
      Supabase conectado y probado con datos reales (proyecto ya creado).
- [x] **API REST** (`servidor/rest.js`) sobre el mismo servidor/puerto 8000:
      `GET/POST/PATCH/DELETE /api/productos...` y `GET /api/equipo`, probada
      de punta a punta y coexistiendo con el WSDL sin conflicto.
- [x] `cliente-python/client.py` (consola) — las 7 demostraciones mínimas del
      PDF, probado contra el servidor real.
- [x] `cliente-python/app.py` — front web Flask, menú lateral con las 6
      operaciones, badge de lenguaje, equipo dinámico vía `GET /api/equipo`.
      Puerto 5000.
- [x] `cliente-php/client.php` (consola) — las mismas 7 demostraciones, probado.
- [x] `cliente-php/front/index.php` — front web en PHP puro (sin framework),
      espejo funcional del de Python, mismo diseño, badge y equipo dinámico.
      Puerto 5001, corre en paralelo al de Python sin chocar.
- [x] Repositorio en GitHub con ramas `main`, `dev` y las `feature/*`.
- [x] Guías: [`GUIA_SUPABASE.md`](GUIA_SUPABASE.md),
      [`GUIA_GITHUB.md`](GUIA_GITHUB.md), [`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md).
- [x] Wireframe del dashboard bonus, aplicado a ambos fronts (no exigido por
      el PDF).

## Qué falta ⏳

### Ismael
- [ ] Avisar al docente que el equipo quedó en 3 integrantes (Diego salió).
- [ ] Invitar a Alexandra e Israel como colaboradores en GitHub si aún no lo
      están (Settings → Collaborators).
- [ ] Ejecutar `servidor/db/schema_integrantes.sql` en el SQL Editor de
      Supabase (tabla `integrantes` — mientras no exista, `/api/equipo`
      funciona con un respaldo hardcodeado, pero conviene tener la real).
- [ ] Cuando el equipo lo decida: desplegar en Render
      ([`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md)) — no urgente, es bonus.

### Cada compañero (Alexandra, Israel) — primer paso
- [ ] Clonar el repo y pararse en tu rama (`git checkout feature/tu-rama`,
      ver sección 3 de [`GUIA_GITHUB.md`](GUIA_GITHUB.md)).
- [ ] Copiar `servidor/.env.example` a `servidor/.env` y pegar la
      `SUPABASE_KEY` que te pase Ismael.
- [ ] Correr el proyecto localmente y confirmar que funciona antes de tocar
      nada (`npm install` + `npm start` en `servidor/`, luego tu cliente).

### Alexandra (`feature/validaciones`)
- [x] Leer `servidor/validaciones.js`, confirmar cobertura de los 7 casos de
      la sección 11 del PDF, agregar el caso de código duplicado.
- [ ] Importar `productos.wsdl` en SoapUI y dejar preparados los 12+ casos
      de prueba (1 correcto + 1 incorrecto por cada una de las 6 operaciones).
      *(Ya tienes el checklist en `evidencias/checklist_soapui.md`.)*
- [x] Guardar capturas en `evidencias/` (WSDL importado, request XML,
      response XML, por operación) — 14 evidencias subidas.
- [x] Cliente adicional en C# (`cliente-csharp/`, `dotnet-svcutil`) para la
      entrega individual — mismas 7 demostraciones que los clientes de
      Python y PHP, probado contra el servidor real.
- [ ] Al final: empaquetar el zip de entrega (`Apellido_Nombre_TareaSOAP.zip`).

### Israel (`feature/cliente-python`)
- [x] Cliente de consola (`client.py`) y front web (`app.py`) construidos y
      probados.
- [ ] Revisar el rediseño del front (menú lateral, badge, equipo vía REST) —
      cambió bastante desde tu primera versión, tómate un momento para
      entenderlo antes de la defensa.
- [ ] Aportar al marco teórico del informe: SOAP, XML, WSDL, cliente-servidor,
      SOAP vs REST (sección 12 del PDF) — ahora el proyecto también expone
      REST, así que la comparación SOAP vs REST se puede ilustrar con
      ejemplos reales de este mismo backend.

### Equipo (todos)
- [ ] Revisión cruzada de código: cada quien revisa la parte de un
      compañero y deja comentarios en el Pull Request antes de aprobar.
- [x] Informe técnico en PDF (`informe/informe_tecnico.pdf`): introducción,
      objetivos, marco teórico, arquitectura (diagrama con las 3 instancias),
      desarrollo, evidencias, análisis de resultados, conclusiones,
      recomendaciones, bibliografía APA 7. Incluye la API REST como mejora
      adicional. **Pendiente antes de entregar:**
  - [ ] Completar apellidos de los 3 integrantes y nombre del docente en la
        portada (quedaron como `[Apellido]` / `[Nombre del docente]`).
  - [ ] Reemplazar los 4 recuadros "[ESPACIO PARA CAPTURA]" de la sección de
        Evidencias con capturas reales: consola del servidor corriendo,
        WSDL en el navegador, consola de `client.py`, consola de `client.php`.
        El documento ya dice exactamente qué comando correr y qué capturar
        en cada caso.
  - [ ] `informe/informe_tecnico.docx` es el editable (por si hay que ajustar
        texto); el `.pdf` es el que se entrega.
- [ ] Empaquetar el zip final una vez completado lo anterior.
- [ ] Ensayar la defensa virtual: cada integrante debe poder explicar y
      modificar en vivo **su** parte del código (1.5 pts de la rúbrica).

## Por qué el código ya está hecho

Para que puedan avanzar en paralelo desde ya (WSDL, servidor, API REST y
fronts listos), pero esto **no reemplaza que cada quien entienda su parte**:
la rúbrica da 1.5 puntos solo a la defensa virtual, y ahí piden explicar y
modificar el código en vivo — no basta con que "funcione".
