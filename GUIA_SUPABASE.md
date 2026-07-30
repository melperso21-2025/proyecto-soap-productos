# Guía: Supabase para el equipo (Ismael, Alexandra, Israel)

Esta guía es para Ismael (dueño del proyecto Supabase) y para los 2 compañeros que
necesitan acceso. El plan gratuito de Supabase permite invitar miembros a la
**organización**, no solo ver el proyecto — así que todos pueden entrar al mismo
dashboard, ver tablas, datos y logs.

## 1. Crear el proyecto (Ismael)

1. Entra a [supabase.com](https://supabase.com) e inicia sesión (recomendado con la
   cuenta de GitHub que vas a usar para el repo).
2. **New project** → elige tu organización personal (o crea una, ej. `equipo-soap`).
3. Nombre del proyecto: `soap-productos`. Contraseña de la base de datos: genera una
   segura y guárdala en un gestor de contraseñas (no la necesitas para el código,
   solo para acceso directo a Postgres si lo usan).
4. Región: la más cercana a Ecuador (`South America (São Paulo)` normalmente).
5. Espera 1-2 minutos a que aprovisione el proyecto.

## 2. Crear la tabla `productos`

1. En el menú lateral: **SQL Editor** → **New query**.
2. Pega el contenido de [`servidor/db/schema.sql`](servidor/db/schema.sql) de este
   repo y presiona **Run**.
3. Verifica en **Table Editor** que la tabla `productos` aparece con sus columnas y
   restricciones (código único, precio > 0, cantidad >= 0).

## 2b. Crear la tabla `integrantes` (para el endpoint `GET /api/equipo`)

1. **SQL Editor** → **New query** de nuevo.
2. Pega el contenido de
   [`servidor/db/schema_integrantes.sql`](servidor/db/schema_integrantes.sql)
   y presiona **Run**. Ya viene con los 3 nombres del equipo precargados.
3. Mientras no ejecutes esto, `/api/equipo` sigue funcionando con una lista
   de respaldo fija en el código — no bloquea nada, pero conviene tener la
   tabla real para que la defensa muestre datos "de verdad" desde Supabase.

## 3. Obtener las credenciales para el `.env`

1. **Project Settings** (ícono de engranaje) → **Data API**.
2. Copia la **Project URL** → va en `SUPABASE_URL`.
3. **Project Settings** → **API Keys** → copia la key **`anon` `public`** → va en
   `SUPABASE_KEY`.
4. En `servidor/`, copia `.env.example` a `.env` y pega ambos valores.
   **Este `.env` nunca se sube a GitHub** (ya está en `.gitignore`).

```bash
cp servidor/.env.example servidor/.env
```

> La `anon key` es pública por diseño (se usa desde el navegador en apps reales);
> lo que protege los datos es la política RLS. Para este proyecto académico se
> usa una política abierta (ver `schema.sql`) porque no se manejan datos
> sensibles — así evitan pelear con RLS mientras aprenden.

## 4. Invitar a los 2 compañeros (Ismael hace esto una sola vez)

1. Ve a **Organization Settings** (no Project Settings) → **Team**.
2. **Invite member** → ingresa el correo de cada compañero (Alexandra, Israel),
   uno a la vez.
3. Rol sugerido: **Developer** (puede ver/editar tablas y código, no puede borrar
   el proyecto ni facturación). Si prefieres que solo miren datos: **Read-only**.
4. Cada compañero revisa su correo, acepta la invitación y crea/inicia sesión en
   Supabase. Ya verán el proyecto `soap-productos` en su dashboard.
5. Cada compañero que vaya a **ejecutar** el servidor localmente necesita también
   copiar `.env.example` a `.env` con las mismas credenciales (pídeles el valor de
   `SUPABASE_KEY` por un canal privado del equipo — WhatsApp/Discord — nunca por
   GitHub).

## 5. Límites del plan gratuito a tener en cuenta

- 1 organización con miembros ilimitados, pero **máximo 2 proyectos activos**
  gratis por organización — de sobra para esta tarea.
- El proyecto se **pausa automáticamente tras 7 días sin actividad**. Si un día
  el servidor no logra conectarse, probablemente el proyecto esté pausado:
  entren al dashboard y  presionen **Restore project** (tarda ~2 minutos). Por
  eso el servidor está diseñado en modo **híbrido**: si Supabase no responde,
  sigue funcionando 100% en memoria, así nunca se cae la demo en vivo.
- 500 MB de base de datos — muy por encima de lo que esta tarea necesita.

## 6. Cómo funciona la integración híbrida (para el informe técnico)

- Al arrancar, `server.js` intenta cargar los productos existentes desde
  Supabase hacia el arreglo en memoria (`data/productos.js`).
- Cada operación que modifica datos (`RegistrarProducto`, `ActualizarStock`,
  `EliminarProducto`) actualiza primero la memoria (que es la fuente de verdad
  operativa, tal como pide el enunciado) y luego intenta persistir el mismo
  cambio en Supabase.
- Si Supabase falla o no está configurado, solo se registra una advertencia en
  consola (`⚠ No se pudo...`) — la operación en memoria y la respuesta SOAP al
  cliente **no se ven afectadas**. Esto es clave: el requisito obligatorio de la
  tarea nunca depende de un servicio externo.
