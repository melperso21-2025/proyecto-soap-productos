# Guía: GitHub para el equipo (Ismael, Alexandra, Israel)

> Diego formaba parte del equipo original pero ya no participa en el
> proyecto. Su rama (`feature/cliente-php`) y esa parte del trabajo quedaron
> a cargo de Ismael, quien además la amplió con un front web en PHP.

## Estado actual

Ya está hecho (Ismael):

- Repo creado: `https://github.com/melperso21-2025/proyecto-soap-productos`
- Rama `main` (estable, solo para entregas) y rama `dev` (trabajo del equipo)
  ya están en GitHub.
- Las 5 ramas de trabajo ya están creadas y subidas, una por responsabilidad:
  `feature/servidor`, `feature/validaciones`, `feature/cliente-python`,
  `feature/cliente-php`, `feature/informe`.

Lo que falta: confirmar que Alexandra e Israel ya son colaboradores
(sección 1) y que cada uno se pare sobre **su** rama `feature/*` (sección 3).

## 1. Invitar a Alexandra e Israel (Ismael, una sola vez)

1. En el repo → **Settings** → **Collaborators** → **Add people**.
2. Busca por su usuario o correo de GitHub: Alexandra, Israel.
3. Rol: **Write** (pueden hacer push y abrir PRs, no pueden borrar el repo ni
   cambiar configuración crítica).
4. Cada compañero acepta la invitación (le llega notificación/correo).

## 2. Clonar el repo (cada compañero, una sola vez)

```bash
git clone https://github.com/melperso21-2025/proyecto-soap-productos.git
cd proyecto-soap-productos
```

## 3. Estrategia de ramas (simple, pensada para 3 personas)

Tres niveles, de más estable a más experimental:

- **`main`** — solo recibe código ya integrado y probado desde `dev`. Nadie
  hace `push` directo aquí; representa el estado que se entrega. Se actualiza
  en puntos de control (ej. fin del día 3 y antes de la entrega final), nunca
  a cada rato.
- **`dev`** — rama de trabajo compartida del equipo. Todo el mundo arranca y
  termina su día aquí. Es donde se juntan las partes antes de pasar a `main`.
- **`feature/*`** — una rama corta por responsabilidad, que sale de `dev` y
  vuelve a `dev`. Ya están creadas en GitHub, cada quien trabaja en la suya:
  - `feature/servidor` (Ismael) — server.js, operaciones.js, rest.js, WSDL,
    arranque del servidor.
  - `feature/validaciones` (Alexandra) — validaciones.js, mensajes de
    error estándar, pruebas de WSDL en SoapUI.
  - `feature/cliente-python` (Israel) — cliente-python/ (cliente de consola
    y front Flask).
  - `feature/cliente-php` (Ismael, ya que Diego salió del equipo) —
    cliente-php/ (cliente de consola y front PHP).
  - `feature/informe` (Israel, luego Ismael consolida) — informe/.

```
feature/servidor ───────┐
feature/validaciones ───┤
feature/cliente-python ─┼──▶ dev ──▶ main
feature/cliente-php ────┘   (integración)   (entrega)
```

> `feature/servidor` y `feature/validaciones` tocan archivos dentro de la
> misma carpeta `servidor/` (Ismael y Alexandra ya trabajaban juntos en el
> Excel en el WSDL y los casos de error). Es normal que sus Pull Requests se
> crucen — por eso el PR de quien mergea segundo debe revisar con cuidado que
> no se pise el trabajo del otro, y por eso ambos avisan en el equipo cuando
> van a mergear a `dev`.

### Cómo se sube cada compañero a su rama (primera vez)

Como las ramas ya existen en GitHub, no se crean de nuevo — solo se "bajan"
localmente con `checkout`. Por ejemplo, Israel con la suya:

```bash
git fetch origin
git checkout feature/cliente-python
```

Git reconoce que `origin/feature/cliente-python` ya existe y conecta tu rama
local con ella automáticamente. Alexandra haría lo mismo con
`feature/validaciones`, y así cada quien.

### Flujo diario (una vez ya estás en tu rama)

```bash
git checkout feature/mi-parte
git pull origin dev            # trae lo último que ya se integró en dev
# ... trabajas y guardas cambios ...
git add cliente-python/client.py
git commit -m "feat: agrega consulta y listado de productos"
git push origin feature/mi-parte
```

Cuando tu parte esté lista (o al final del día), abres un **Pull Request en
GitHub de tu `feature/*` hacia `dev`** (no hacia `main`). Esto calza con la
tarea #17 del plan ("Revisión cruzada de código"): antes de aprobar el PR,
otro integrante del equipo lo revisa y comenta.

Cuando `dev` esté estable y probado (las 6 operaciones funcionando con los 2
clientes), Ismael abre el PR final de `dev` → `main`:

```bash
git checkout main
git pull origin main
git merge origin/dev
git push origin main
```

## 4. Convención de commits (en español)

Usen prefijos cortos para que el historial se lea fácil en la defensa virtual:

| Prefijo    | Cuándo usarlo                                   |
|------------|--------------------------------------------------|
| `feat:`    | una operación o funcionalidad nueva               |
| `fix:`     | corrección de un bug                              |
| `docs:`    | cambios en README, informe, guías                 |
| `test:`    | pruebas de SoapUI, evidencias                     |
| `chore:`   | configuración, dependencias, estructura de carpetas |

Ejemplos:
- `feat: agrega validacion de precio y cantidad en RegistrarProducto`
- `fix: corrige mensaje de error cuando el codigo no existe`
- `docs: agrega guia de instalacion de Supabase`

## 5. Qué NO debe subirse nunca (ya está en `.gitignore`)

- `servidor/.env` (contiene la `SUPABASE_KEY`).
- `node_modules/` (cada quien corre `npm install` localmente).

Si alguien ya subió un `.env` por error: avisen enseguida — hay que rotar la
`SUPABASE_KEY` desde el dashboard de Supabase, no basta con borrarlo del
commit siguiente (queda en el historial).

## 6. Checklist antes de la entrega final

- [ ] Todas las `feature/*` fueron mergeadas a `dev` vía Pull Request.
- [ ] `dev` fue mergeada a `main` (ver último paso de la sección 3).
- [ ] `main` tiene el código de servidor + 2 clientes + WSDL, todo probado.
- [ ] Cada integrante hizo al menos un commit visible con su parte.
- [ ] Carpeta `evidencias/` con capturas de servidor, clientes y SoapUI.
- [ ] `informe/` con el PDF técnico.
- [ ] README.md actualizado con instrucciones de instalación y ejecución.
