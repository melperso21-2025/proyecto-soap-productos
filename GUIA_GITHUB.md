# Guía: GitHub para el equipo (Ismael, Alexandra, Israel, Diego)

## 1. Crear el repositorio (Ismael, una sola vez)

1. En [github.com](https://github.com) → **New repository**.
2. Nombre: `proyecto-soap-productos`. Visibilidad: **Private** (recomendado
   mientras es tarea de curso; lo pueden poner público después de calificar
   si quieren mostrarlo en su portafolio).
3. **No** marques "Initialize with README" (ya tenemos uno local).
4. Copia la URL que te da GitHub (`git@github.com:usuario/proyecto-soap-productos.git`
   o la versión `https://`).

Desde la carpeta del proyecto:

```bash
git init
git add .
git commit -m "chore: estructura inicial del proyecto SOAP productos"
git branch -M main
git remote add origin <URL_DEL_REPO>
git push -u origin main
```

Luego crea la rama `desarrollo`, que es donde va a trabajar todo el equipo
día a día (ver sección 3):

```bash
git checkout -b desarrollo
git push -u origin desarrollo
```

## 2. Invitar a los 3 compañeros

1. En el repo → **Settings** → **Collaborators** → **Add people**.
2. Busca por su usuario o correo de GitHub: Alexandra, Israel, Diego.
3. Rol: **Write** (pueden hacer push y abrir PRs, no pueden borrar el repo ni
   cambiar configuración crítica).
4. Cada compañero acepta la invitación (le llega notificación/correo) y luego
   clona el repo:

```bash
git clone <URL_DEL_REPO>
cd proyecto-soap-productos
```

## 3. Estrategia de ramas (simple, pensada para 4 personas y 4 días)

Tres niveles, de más estable a más experimental:

- **`main`** — solo recibe código ya integrado y probado desde `desarrollo`.
  Nadie hace `push` directo aquí; representa el estado que se entrega. Se
  actualiza en puntos de control (ej. fin del día 3 y antes de la entrega
  final), nunca a cada rato.
- **`desarrollo`** — rama de trabajo compartida del equipo. Todo el mundo
  arranca y termina su día aquí. Es donde se juntan las 4 partes antes de
  pasar a `main`.
- **`feature/*`** — una rama corta por responsabilidad, siguiendo el plan de
  trabajo del Excel, que sale de `desarrollo` y vuelve a `desarrollo`:
  - `feature/servidor` (Ismael) — server.js, validaciones, WSDL.
  - `feature/cliente-python` (Israel) — cliente-python/.
  - `feature/cliente-php` (Diego) — cliente-php/.
  - `feature/informe` (Israel + Diego, luego Ismael consolida) — informe/.

```
feature/servidor ──┐
feature/cliente-python ─┼──▶ desarrollo ──▶ main
feature/cliente-php ─┘         (integración)   (entrega)
```

Flujo diario (cada integrante, sobre su propia parte):

```bash
git checkout desarrollo
git pull origin desarrollo
git checkout -b feature/mi-parte      # solo la primera vez
# ... trabajas y guardas cambios ...
git add servidor/server.js
git commit -m "feat: implementa ActualizarStock y CalcularValorInventario"
git push -u origin feature/mi-parte
```

Luego cada quien abre un **Pull Request hacia `desarrollo`** (no hacia
`main`) en GitHub. Esto calza con la tarea #17 del plan ("Revisión cruzada de
código"): antes de aprobar el PR, otro integrante del equipo lo revisa y
comenta.

Cuando `desarrollo` esté estable y probado (las 6 operaciones funcionando con
los 2 clientes), Ismael abre el PR final de `desarrollo` → `main`:

```bash
git checkout main
git pull origin main
git merge origin/desarrollo
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

- [ ] Todas las `feature/*` fueron mergeadas a `desarrollo` vía Pull Request.
- [ ] `desarrollo` fue mergeada a `main` (ver último paso de la sección 3).
- [ ] `main` tiene el código de servidor + 2 clientes + WSDL, todo probado.
- [ ] Cada integrante hizo al menos un commit visible con su parte.
- [ ] Carpeta `evidencias/` con capturas de servidor, clientes y SoapUI.
- [ ] `informe/` con el PDF técnico.
- [ ] README.md actualizado con instrucciones de instalación y ejecución.
