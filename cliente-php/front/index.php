<?php
/**
 * Front web en PHP nativo (sin framework) para el servicio SOAP de productos.
 * Responsable: Ismael (PHP quedo a su cargo tras la salida de Diego del equipo).
 *
 * Espejo funcional del dashboard de Python (cliente-python/app.py): mismas
 * 6 vistas, mismo menu lateral, misma paleta. Usa SoapClient nativo para
 * hablar con el servicio (igual que cliente-php/client.php) y consume la
 * API REST del servidor (GET /api/equipo) para mostrar al equipo - asi
 * demuestra los dos protocolos que expone el mismo backend.
 *
 * Se corre con el servidor embebido de PHP, en un puerto distinto al de
 * Python para que ambos convivan al mismo tiempo:
 *   php -S localhost:5001 -t cliente-php/front
 */

session_start();

const WSDL_URL = "http://localhost:8000/productos?wsdl";
const API_EQUIPO_URL = "http://localhost:8000/api/equipo";
const VISTAS = ["registrar", "consultar", "listar", "stock", "valor", "eliminar"];
const UMBRAL_BAJO_STOCK = 5;

function e($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function obtenerCliente(): SoapClient {
    return new SoapClient(WSDL_URL, [
        "trace" => true,
        "exceptions" => true,
        "connection_timeout" => 5,
    ]);
}

function flash(string $mensaje, string $categoria): void {
    $_SESSION["flash"][] = [$categoria, $mensaje];
}

function irA(string $vista, array $query = []): void {
    if (!in_array($vista, VISTAS, true)) {
        $vista = "listar";
    }
    $query["view"] = $vista;
    header("Location: index.php?" . http_build_query($query));
    exit;
}

function obtenerEquipo(): array {
    $ch = curl_init(API_EQUIPO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $respuesta = curl_exec($ch);
    curl_close($ch);
    if ($respuesta === false) {
        return [];
    }
    $datos = json_decode($respuesta, true);
    return is_array($datos) ? $datos : [];
}

// ============================================================
// Acciones que escriben (POST) o ejecutan una consulta directa
// ============================================================
$metodo = $_SERVER["REQUEST_METHOD"];
$accion = $_GET["accion"] ?? null;

if ($metodo === "POST" && $accion === "registrar") {
    try {
        $cliente = obtenerCliente();
        $r = $cliente->RegistrarProducto([
            "codigo" => $_POST["codigo"],
            "nombre" => $_POST["nombre"],
            "categoria" => $_POST["categoria"],
            "precio" => (float) $_POST["precio"],
            "cantidad" => (int) $_POST["cantidad"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al registrar: " . $error->getMessage(), "error");
    }
    irA("registrar");
}

if ($metodo === "POST" && $accion === "consultar") {
    $codigo = $_POST["codigo"];
    try {
        $cliente = obtenerCliente();
        $r = $cliente->ConsultarProducto(["codigo" => $codigo]);
        if ($r->estado) {
            irA("consultar", [
                "codigo" => $r->codigo,
                "nombre" => $r->nombre,
                "categoria" => $r->categoria,
                "precio" => $r->precio,
                "cantidad" => $r->cantidad,
                "encontrado" => "1",
            ]);
        }
        flash($r->mensaje, "error");
    } catch (SoapFault $error) {
        flash("Error al consultar: " . $error->getMessage(), "error");
    }
    irA("consultar", ["codigo" => $codigo]);
}

if ($metodo === "POST" && $accion === "stock") {
    $siguiente = $_POST["next"] ?? "stock";
    try {
        $cliente = obtenerCliente();
        $r = $cliente->ActualizarStock([
            "codigo" => $_POST["codigo"],
            "cantidad" => (int) $_POST["cantidad"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al actualizar stock: " . $error->getMessage(), "error");
    }
    irA($siguiente);
}

if ($accion === "valor") {
    $codigo = $_POST["codigo"] ?? ($_GET["codigo"] ?? "");
    try {
        $cliente = obtenerCliente();
        $r = $cliente->CalcularValorInventario(["codigo" => $codigo]);
        if ($r->estado) {
            irA("valor", [
                "codigo" => $codigo,
                "nombre" => $r->nombre,
                "precio" => $r->precio,
                "cantidad" => $r->cantidad,
                "valorTotal" => $r->valorTotal,
            ]);
        }
        flash($r->mensaje, "error");
    } catch (SoapFault $error) {
        flash("Error al calcular valor: " . $error->getMessage(), "error");
    }
    irA("valor", ["codigo" => $codigo]);
}

if ($metodo === "POST" && $accion === "eliminar") {
    $codigo = $_POST["codigo"];
    $siguiente = $_POST["next"] ?? "eliminar";
    try {
        $cliente = obtenerCliente();
        $r = $cliente->EliminarProducto(["codigo" => $codigo]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al eliminar: " . $error->getMessage(), "error");
    }
    irA($siguiente);
}

// ============================================================
// GET normal: preparar datos y renderizar la vista activa
// ============================================================
$vista = $_GET["view"] ?? "listar";
if (!in_array($vista, VISTAS, true)) {
    $vista = "listar";
}
$codigoPrefill = $_GET["codigo"] ?? "";

$productos = [];
try {
    $cliente = obtenerCliente();
    $respuesta = $cliente->ListarProductos([]);
    $crudo = $respuesta->productos ?? [];
    if (is_object($crudo)) {
        $productos = [$crudo];
    } elseif (is_array($crudo)) {
        $productos = $crudo;
    }
} catch (SoapFault $error) {
    flash("No se pudo conectar al servicio SOAP en " . WSDL_URL . ": " . $error->getMessage(), "error");
}

$totalProductos = count($productos);
$valorTotalInventario = 0.0;
$bajoStock = 0;
foreach ($productos as $p) {
    $valorTotalInventario += $p->precio * $p->cantidad;
    if ($p->cantidad < UMBRAL_BAJO_STOCK) {
        $bajoStock++;
    }
}

$resultadoConsulta = null;
if ($vista === "consultar" && isset($_GET["encontrado"])) {
    $resultadoConsulta = [
        "codigo" => $_GET["codigo"] ?? "",
        "nombre" => $_GET["nombre"] ?? "",
        "categoria" => $_GET["categoria"] ?? "",
        "precio" => $_GET["precio"] ?? "",
        "cantidad" => $_GET["cantidad"] ?? "",
    ];
}

$resultadoValor = null;
if ($vista === "valor" && isset($_GET["valorTotal"])) {
    $resultadoValor = [
        "codigo" => $_GET["codigo"] ?? "",
        "nombre" => $_GET["nombre"] ?? "",
        "precio" => $_GET["precio"] ?? "",
        "cantidad" => $_GET["cantidad"] ?? "",
        "valorTotal" => $_GET["valorTotal"] ?? "",
    ];
}

$equipo = obtenerEquipo();

$mensajes = $_SESSION["flash"] ?? [];
unset($_SESSION["flash"]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard SOAP Productos (PHP)</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="shell">
    <nav class="sidebar" aria-label="Operaciones del servicio">
      <div class="brand">
        <div class="brand-mark">PHP</div>
        <div class="brand-text">
          <span class="brand-name">Panel Productos</span>
          <span class="brand-sub">SOAP · productos.wsdl</span>
        </div>
      </div>
      <div class="lang-badge">Cliente PHP · SoapClient nativo</div>

      <div class="nav-label">Las 6 operaciones</div>
      <div class="nav">
        <a class="nav-item <?= $vista === "registrar" ? "active" : "" ?>" href="index.php?view=registrar">
          <span class="num">01</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
          Registrar producto
        </a>
        <a class="nav-item <?= $vista === "consultar" ? "active" : "" ?>" href="index.php?view=consultar">
          <span class="num">02</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.8-4.8"/></svg>
          Consultar producto
        </a>
        <a class="nav-item <?= $vista === "listar" ? "active" : "" ?>" href="index.php?view=listar">
          <span class="num">03</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>
          Listar productos
        </a>
        <a class="nav-item <?= $vista === "stock" ? "active" : "" ?>" href="index.php?view=stock">
          <span class="num">04</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V13M10 19V9M16 19V5M4 13l6-4 6-4 0 0"/><path d="M15 5h5v5"/></svg>
          Actualizar stock
        </a>
        <a class="nav-item <?= $vista === "valor" ? "active" : "" ?>" href="index.php?view=valor">
          <span class="num">05</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8.5 7.5h7M8 12h1M11.5 12h1M15 12h1M8 15.5h1M11.5 15.5h1M15 15.5h1"/></svg>
          Calcular valor
        </a>
        <a class="nav-item <?= $vista === "eliminar" ? "active" : "" ?>" href="index.php?view=eliminar">
          <span class="num">06</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 7h14M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12"/><path d="M10 11v6M14 11v6"/></svg>
          Eliminar producto
        </a>
      </div>

      <div class="sidebar-footer">
        Bonus opcional · no exigido por el PDF de la tarea
        <div class="team">
          <?php if ($equipo): ?>
            <?php foreach ($equipo as $integrante): ?>
              <div class="team-row"><span class="team-nombre"><?= e($integrante["nombre"]) ?></span><span class="team-rol"><?= e($integrante["rol"]) ?></span></div>
            <?php endforeach; ?>
          <?php else: ?>
            Equipo no disponible (falló GET /api/equipo)
          <?php endif; ?>
        </div>
      </div>
    </nav>

    <main>
      <?php foreach ($mensajes as [$categoria, $texto]): ?>
        <div class="result <?= $categoria === "ok" ? "result-ok" : "result-error" ?>" style="margin-bottom:1.5rem;">
          <div><b><?= $categoria === "ok" ? "estado = true" : "estado = false" ?></b><?= e($texto) ?></div>
        </div>
      <?php endforeach; ?>

      <!-- ============ 01 REGISTRAR ============ -->
      <section class="view <?= $vista === "registrar" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 1 / 6 · RegistrarProducto</span>
            <h1 class="page-title">Registrar producto</h1>
            <p class="page-desc">Agrega un producto nuevo al inventario. El código debe ser único — si ya existe, el servicio lo rechaza.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="card" style="max-width:32rem;">
          <h3>Datos del producto <span class="tag">entrada</span></h3>
          <form method="post" action="index.php?accion=registrar">
            <div class="field"><label for="r-codigo">Código</label><input id="r-codigo" name="codigo" placeholder="P202" required></div>
            <div class="field"><label for="r-nombre">Nombre</label><input id="r-nombre" name="nombre" placeholder="Silla gamer" required></div>
            <div class="field-row">
              <div class="field"><label for="r-categoria">Categoría</label><input id="r-categoria" name="categoria" placeholder="Mobiliario" required></div>
              <div class="field"><label for="r-precio">Precio</label><input id="r-precio" name="precio" type="number" step="0.01" min="0.01" placeholder="189.90" required></div>
            </div>
            <div class="field"><label for="r-cantidad">Cantidad</label><input id="r-cantidad" name="cantidad" type="number" min="0" placeholder="10" required></div>
            <button class="btn btn-primary" type="submit">Registrar producto</button>
          </form>
        </div>
      </section>

      <!-- ============ 02 CONSULTAR ============ -->
      <section class="view <?= $vista === "consultar" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 2 / 6 · ConsultarProducto</span>
            <h1 class="page-title">Consultar producto</h1>
            <p class="page-desc">Busca un producto por su código y muestra su ficha completa.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="grid-2">
          <div class="card">
            <h3>Buscar por código <span class="tag">entrada</span></h3>
            <form method="post" action="index.php?accion=consultar">
              <div class="field"><label for="c-codigo">Código</label><input id="c-codigo" name="codigo" value="<?= e($codigoPrefill) ?>" placeholder="P100" required></div>
              <button class="btn btn-primary" type="submit">Consultar</button>
            </form>
          </div>

          <div class="card">
            <h3>Ficha del producto <span class="tag">salida</span></h3>
            <?php if ($resultadoConsulta): ?>
              <div class="kv">
                <div class="kv-row"><span class="k">Código</span><span class="v"><?= e($resultadoConsulta["codigo"]) ?></span></div>
                <div class="kv-row"><span class="k">Nombre</span><span class="v"><?= e($resultadoConsulta["nombre"]) ?></span></div>
                <div class="kv-row"><span class="k">Categoría</span><span class="v"><?= e($resultadoConsulta["categoria"]) ?></span></div>
                <div class="kv-row"><span class="k">Precio</span><span class="v">$<?= e(number_format((float) $resultadoConsulta["precio"], 2)) ?></span></div>
                <div class="kv-row"><span class="k">Cantidad</span><span class="v"><?= e(str_pad((string) (int) $resultadoConsulta["cantidad"], 4, "0", STR_PAD_LEFT)) ?></span></div>
              </div>
            <?php else: ?>
              <p class="vacio-nota">Todavía no has consultado ningún código en esta sesión.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ============ 03 LISTAR ============ -->
      <section class="view <?= $vista === "listar" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 3 / 6 · ListarProductos</span>
            <h1 class="page-title">Productos registrados</h1>
            <p class="page-desc">Vista general del inventario — no recibe parámetros de entrada.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="kpi-row">
          <div class="kpi"><div class="label">Productos registrados</div><div class="value"><?= e($totalProductos) ?></div></div>
          <div class="kpi"><div class="label">Valor total inventario</div><div class="value">$<?= e(number_format($valorTotalInventario, 2)) ?></div></div>
          <div class="kpi"><div class="label">Bajo stock (&lt; <?= e(UMBRAL_BAJO_STOCK) ?>)</div><div class="value <?= $bajoStock > 0 ? "warn" : "" ?>"><?= e($bajoStock) ?></div></div>
        </div>

        <div class="table-wrap">
          <table>
            <thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th style="text-align:right;">Precio</th><th style="text-align:right;">Stock</th><th></th></tr></thead>
            <tbody>
              <?php if (!$productos): ?>
                <tr><td colspan="6" class="vacio">No hay productos registrados todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($productos as $p): ?>
                <tr>
                  <td class="codigo"><?= e($p->codigo) ?></td>
                  <td><?= e($p->nombre) ?></td>
                  <td><?= e($p->categoria) ?></td>
                  <td class="num">$<?= e(number_format($p->precio, 2)) ?></td>
                  <td class="num"><span class="stock-chip <?= $p->cantidad < UMBRAL_BAJO_STOCK ? "stock-low" : "stock-ok" ?>"><?= e(str_pad((string) $p->cantidad, 4, "0", STR_PAD_LEFT)) ?></span></td>
                  <td>
                    <div class="row-actions">
                      <a class="icon-btn" href="index.php?view=consultar&codigo=<?= urlencode($p->codigo) ?>">Consultar</a>
                      <a class="icon-btn" href="index.php?view=stock&codigo=<?= urlencode($p->codigo) ?>">Stock</a>
                      <a class="icon-btn" href="index.php?accion=valor&codigo=<?= urlencode($p->codigo) ?>">Valor</a>
                      <form method="post" action="index.php?accion=eliminar" class="inline-form"
                            onsubmit="return confirm('¿Eliminar <?= e($p->codigo) ?>? Esta acción no se puede deshacer.');">
                        <input type="hidden" name="codigo" value="<?= e($p->codigo) ?>">
                        <input type="hidden" name="next" value="listar">
                        <button class="icon-btn icon-btn-danger" type="submit">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ============ 04 STOCK ============ -->
      <section class="view <?= $vista === "stock" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 4 / 6 · ActualizarStock</span>
            <h1 class="page-title">Actualizar stock</h1>
            <p class="page-desc">Cambia la cantidad disponible de un producto existente.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="card" style="max-width:26rem;">
          <h3>Nueva cantidad <span class="tag">entrada</span></h3>
          <form method="post" action="index.php?accion=stock">
            <input type="hidden" name="next" value="stock">
            <div class="field"><label for="s-codigo">Código</label><input id="s-codigo" name="codigo" value="<?= e($codigoPrefill) ?>" placeholder="P101" required></div>
            <div class="field"><label for="s-cantidad">Nueva cantidad</label><input id="s-cantidad" name="cantidad" type="number" min="0" placeholder="20" required></div>
            <button class="btn btn-primary" type="submit">Actualizar stock</button>
          </form>
        </div>
      </section>

      <!-- ============ 05 VALOR ============ -->
      <section class="view <?= $vista === "valor" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 5 / 6 · CalcularValorInventario</span>
            <h1 class="page-title">Calcular valor de inventario</h1>
            <p class="page-desc">Multiplica precio × cantidad disponible para un producto.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="grid-2">
          <div class="card">
            <h3>Producto <span class="tag">entrada</span></h3>
            <form method="get" action="index.php">
              <input type="hidden" name="accion" value="valor">
              <div class="field"><label for="v-codigo">Código</label><input id="v-codigo" name="codigo" value="<?= e($codigoPrefill) ?>" placeholder="P201" required></div>
              <button class="btn btn-primary" type="submit">Calcular</button>
            </form>
          </div>

          <div class="card">
            <h3>Resultado <span class="tag">salida</span></h3>
            <?php if ($resultadoValor): ?>
              <div class="stat-tile">
                <span class="label">Valor total del inventario — <?= e($resultadoValor["codigo"]) ?></span>
                <span class="amount">$<?= e(number_format((float) $resultadoValor["valorTotal"], 2)) ?></span>
                <span class="formula">$<?= e(number_format((float) $resultadoValor["precio"], 2)) ?> × <?= e((int) $resultadoValor["cantidad"]) ?> unidades (<?= e($resultadoValor["nombre"]) ?>)</span>
              </div>
            <?php else: ?>
              <p class="vacio-nota">Todavía no has calculado el valor de ningún producto en esta sesión.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- ============ 06 ELIMINAR ============ -->
      <section class="view <?= $vista === "eliminar" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Operación 6 / 6 · EliminarProducto</span>
            <h1 class="page-title">Eliminar producto</h1>
            <p class="page-desc">Quita un producto del inventario de forma permanente.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl</span>
        </div>

        <div class="card" style="max-width:26rem;">
          <h3>Producto a eliminar <span class="tag">entrada</span></h3>
          <form method="post" action="index.php?accion=eliminar"
                onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.');">
            <input type="hidden" name="next" value="eliminar">
            <div class="field"><label for="e-codigo">Código</label><input id="e-codigo" name="codigo" placeholder="P200" required></div>
            <div class="danger-box">
              <h4>Esta acción no se puede deshacer</h4>
              <p>El producto se borra de la memoria del servidor y, si Supabase está conectado, también de la tabla persistida.</p>
            </div>
            <button class="btn btn-danger" type="submit">Eliminar producto</button>
          </form>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
