# Estado del proyecto — SOAP Productos

> Última actualización: ver historial de commits de este archivo.
> Léanlo antes de tocar código: dice qué ya existe, qué falta, y qué te toca
> a ti específicamente.

## Equipo

| Integrante | Responsabilidad principal | Rama de trabajo |
|---|---|---|
| **Ismael** | Servidor SOAP (server.js), WSDL | `feature/servidor` |
| **Alexandra** | Validaciones, mensajes de error, pruebas del WSDL en SoapUI, empaquetado final | `feature/validaciones` |
| **Israel** | Cliente Python (zeep), marco teórico del informe | `feature/cliente-python` |
| **Diego** | Cliente PHP (SoapClient), marco teórico del informe | `feature/cliente-php` |
| **Equipo** | Pruebas SoapUI, revisión cruzada de código, ensayo de defensa | — |

Modalidad de entrega: 4 personas (confirmado con el docente), pero recuerden
que igual cada quien entrega su propio zip `Apellido_Nombre_TareaSOAP.zip`,
informe y defensa virtual individual (sección 1 y 13 del PDF de la tarea).

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
- [x] `servidor/server.js` — las 6 operaciones probadas de punta a punta:
      RegistrarProducto, ConsultarProducto, ListarProductos, ActualizarStock,
      CalcularValorInventario, EliminarProducto.
- [x] `servidor/validaciones.js` — código no vacío/no duplicado, precio > 0,
      cantidad entera ≥ 0, producto debe existir. (**Alexandra: revisar y
      hacerlo tuyo, ver sección "Qué hacer ahora"**).
- [x] Persistencia híbrida con Supabase — memoria como fuente de verdad,
      Supabase conectado y probado con datos reales (proyecto ya creado).
- [x] `cliente-python/client.py` — las 7 demostraciones mínimas del PDF,
      probado contra el servidor real.
- [x] `cliente-php/client.php` — las mismas 7 demostraciones, probado.
- [x] Repositorio en GitHub con ramas `main`, `dev` y las 5 `feature/*`.
- [x] Guías: [`GUIA_SUPABASE.md`](GUIA_SUPABASE.md),
      [`GUIA_GITHUB.md`](GUIA_GITHUB.md), [`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md).
- [x] Wireframe del dashboard bonus (opcional, no exigido por el PDF).

## Qué falta ⏳

### Ismael
- [ ] Invitar a Alexandra, Israel y Diego como colaboradores en GitHub
      (Settings → Collaborators).
- [ ] Pasarles por canal privado (WhatsApp/Discord, **no** GitHub): la URL
      del repo y el valor real de `SUPABASE_KEY` para su `.env` local.
- [ ] Cuando el equipo lo decida: desplegar en Render
      ([`GUIA_DESPLIEGUE.md`](GUIA_DESPLIEGUE.md)) — no urgente, es bonus.

### Cada compañero (Alexandra, Israel, Diego) — primer paso
- [ ] Clonar el repo y pararse en tu rama (`git checkout feature/tu-rama`,
      ver sección 3 de [`GUIA_GITHUB.md`](GUIA_GITHUB.md)).
- [ ] Copiar `servidor/.env.example` a `servidor/.env` y pegar la
      `SUPABASE_KEY` que te pase Ismael.
- [ ] Correr el proyecto localmente y confirmar que funciona antes de tocar
      nada (`npm install` + `npm start` en `servidor/`, luego tu cliente).

### Alexandra (`feature/validaciones`)
- [ ] **Leer y entender `servidor/validaciones.js` a fondo** — está escrito,
      pero en la defensa virtual te van a pedir que lo expliques y modifiques
      en vivo. No lo dejes como "caja negra".
- [ ] Confirmar que cubre los 7 casos de la sección 11 del PDF (código
      vacío/duplicado, nombre/categoría vacíos, precio ≤ 0, cantidad < 0,
      producto inexistente).
- [ ] Importar `productos.wsdl` en SoapUI y dejar preparados los 12+ casos
      de prueba (1 correcto + 1 incorrecto por cada una de las 6 operaciones).
- [ ] Guardar capturas en `evidencias/` (WSDL importado, request XML,
      response XML, por operación).
- [ ] Al final: empaquetar el zip de entrega (`Apellido_Nombre_TareaSOAP.zip`).

### Israel (`feature/cliente-python`)
- [ ] Leer `cliente-python/client.py` y entender cómo `zeep` consume el WSDL.
- [ ] Aportar al marco teórico del informe: SOAP, XML, WSDL, cliente-servidor,
      SOAP vs REST (sección 12 del PDF).

### Diego (`feature/cliente-php`)
- [ ] Leer `cliente-php/client.php` y entender cómo `SoapClient` nativo
      consume el WSDL (necesita la extensión `soap` habilitada en su
      `php.ini` local — ver nota en el archivo).
- [ ] Aportar al marco teórico del informe (mismo alcance que Israel).

### Equipo (todos)
- [ ] Revisión cruzada de código: cada quien revisa la parte de un
      compañero y deja comentarios en el Pull Request antes de aprobar.
- [ ] Informe técnico en PDF (`informe/`): introducción, objetivos, marco
      teórico, arquitectura (diagrama cliente-servidor), desarrollo,
      evidencias, análisis de resultados, conclusiones (mín. 3),
      recomendaciones (mín. 2), bibliografía APA 7.
- [ ] Ensayar la defensa virtual: cada integrante debe poder explicar y
      modificar en vivo **su** parte del código (1.5 pts de la rúbrica).

## Por qué el código ya está hecho

Para que puedan avanzar en paralelo desde ya (WSDL, servidor y clientes
listos), pero esto **no reemplaza que cada quien entienda su parte**: la
rúbrica da 1.5 puntos solo a la defensa virtual, y ahí piden explicar y
modificar el código en vivo — no basta con que "funcione".
