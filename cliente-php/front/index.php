<?php
/**
 * Front web en PHP nativo (sin framework) para el servicio SOAP de productos.
 * Responsable: Ismael (PHP quedo a su cargo tras la salida de Diego del equipo).
 *
 * Espejo funcional del dashboard de Python (cliente-python/app.py): mismas
 * 6 vistas, mismo menu lateral, misma paleta. Usa SoapClient nativo para
 * hablar con el servicio (igual que cliente-php/client.php).
 *
 * El WSDL declara DOS servicios: ProductosService (las 6 operaciones
 * calificadas, sin cambios) y LocalesService (mejora adicional de
 * locales/mapa/stock por tienda) -- ambos por SOAP real. A diferencia de
 * zeep (Python), SoapClient de PHP fusiona las operaciones de ambos
 * servicios en un solo cliente y enruta automaticamente cada llamada a la
 * direccion correcta segun lo que declara el WSDL -- no hace falta un
 * cliente separado por servicio.
 *
 * Se corre con el servidor embebido de PHP, en un puerto distinto al de
 * Python para que ambos convivan al mismo tiempo:
 *   php -S localhost:5001 -t cliente-php/front
 */

session_start();

const WSDL_URL = "http://localhost:8000/productos?wsdl";
const API_EQUIPO_URL = "http://localhost:8000/api/equipo";
const API_INSTANCIA_URL = "http://localhost:8000/api/instancia";
const API_PRODUCTOS_URL = "http://localhost:8000/api/productos";
const VISTAS = ["registrar", "consultar", "listar", "stock", "valor", "eliminar", "locales"];
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

// SOAP no distingue "un elemento" de "una lista de un elemento": cuando un
// campo repetible (locales, productos, stockPorLocal) tiene un solo valor,
// SoapClient lo entrega como un unico stdClass en vez de un array de uno.
// Esta funcion convierte recursivamente stdClass -> array asociativo,
// envuelve esos campos conocidos en una lista si hiciera falta, y castea a
// float los xsd:decimal -- SoapClient los devuelve como string por
// defecto (para no perder precision), lo cual rompe operaciones JS como
// "valorTotal.toFixed()" si se serializan tal cual con json_encode.
function normalizarSoap($valor) {
    if (is_object($valor)) {
        $valor = get_object_vars($valor);
    }
    if (!is_array($valor)) {
        return $valor;
    }
    $camposLista = ["locales", "productos", "stockPorLocal"];
    $camposDecimal = ["lat", "lng", "precio", "valorTotal"];
    $normalizado = [];
    foreach ($valor as $clave => $v) {
        $v = normalizarSoap($v);
        if (in_array($clave, $camposLista, true) && $v !== null) {
            if (!is_array($v) || !array_is_list($v)) {
                $v = [$v];
            }
        } elseif (in_array($clave, $camposDecimal, true) && is_string($v) && is_numeric($v)) {
            $v = (float) $v;
        }
        $normalizado[$clave] = $v;
    }
    return $normalizado;
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

function obtenerJson(string $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $respuesta = curl_exec($ch);
    curl_close($ch);
    if ($respuesta === false) {
        return null;
    }
    return json_decode($respuesta, true);
}

function obtenerEquipo(): array {
    $datos = obtenerJson(API_EQUIPO_URL);
    return is_array($datos) ? $datos : [];
}

function obtenerInstancia(): string {
    $datos = obtenerJson(API_INSTANCIA_URL);
    return is_array($datos) && isset($datos["nombre"]) ? $datos["nombre"] : "desconocido";
}

function obtenerOrigenes(): array {
    // El WSDL no expone "origen" (no esta en el contrato SOAP obligatorio),
    // asi que se completa aparte consultando la API REST del mismo servidor.
    $datos = obtenerJson(API_PRODUCTOS_URL);
    $lista = $datos["productos"] ?? [];
    $mapa = [];
    foreach ((is_array($lista) ? $lista : []) as $p) {
        $mapa[$p["codigo"]] = $p["origen"] ?? null;
    }
    return $mapa;
}

function obtenerLocales(): array {
    // Mejora adicional por SOAP (LocalesService), portType separado del
    // contrato calificado -- ver productos.wsdl.
    try {
        $r = obtenerCliente()->ListarLocales([]);
        return normalizarSoap($r)["locales"] ?? [];
    } catch (SoapFault $error) {
        return [];
    }
}

function obtenerStockPorLocal(string $codigo): array {
    try {
        $r = obtenerCliente()->ConsultarStockPorLocal(["codigo" => $codigo]);
        if (!$r->estado) {
            return [];
        }
        return normalizarSoap($r)["stockPorLocal"] ?? [];
    } catch (SoapFault $error) {
        return [];
    }
}

function obtenerMapaLocales(): array {
    // Todas las tiendas con sus productos y valor de inventario, en una
    // sola llamada SOAP. Alimenta el mapa central de "Locales y stock" y
    // el mini-mapa de "Listar productos".
    try {
        $r = obtenerCliente()->ObtenerMapaLocales([]);
        $lista = normalizarSoap($r)["locales"] ?? [];
        // Si un local no tiene ningun producto, el elemento repetible
        // "productos" (minOccurs=0) no llega en absoluto en el XML -- ni
        // siquiera como null -- asi que hay que ponerle un array vacio
        // explicito para que el mapa (JS) no truene con "undefined".
        foreach ($lista as &$local) {
            $local["productos"] = $local["productos"] ?? [];
        }
        unset($local);
        return $lista;
    } catch (SoapFault $error) {
        return [];
    }
}

function resumenTiendasPorProducto(array $mapaLocales): array {
    $resumen = [];
    foreach ($mapaLocales as $local) {
        foreach (($local["productos"] ?? []) as $producto) {
            if (!isset($resumen[$producto["codigo"]])) {
                $resumen[$producto["codigo"]] = ["tiendas" => 0, "total" => 0];
            }
            $resumen[$producto["codigo"]]["tiendas"]++;
            $resumen[$producto["codigo"]]["total"] += $producto["cantidad"];
        }
    }
    return $resumen;
}

function obtenerValorLocal($localId) {
    // Valor de inventario de una tienda especifica (precio x stock_local),
    // por SOAP. Extiende CalcularValorInventario (global) sin tocarla.
    try {
        $r = obtenerCliente()->CalcularValorPorLocal(["localId" => (int) $localId]);
        $resultado = normalizarSoap($r);
        $resultado["productos"] = $resultado["productos"] ?? [];
        return $resultado;
    } catch (SoapFault $error) {
        return null;
    }
}

// ============================================================
// Acciones que escriben (POST) o ejecutan una consulta directa
// ============================================================
$metodo = $_SERVER["REQUEST_METHOD"];
$accion = $_GET["accion"] ?? null;

if ($metodo === "POST" && $accion === "registrar") {
    $codigo = $_POST["codigo"];
    try {
        $cliente = obtenerCliente();
        $r = $cliente->RegistrarProducto([
            "codigo" => $codigo,
            "nombre" => $_POST["nombre"],
            "categoria" => $_POST["categoria"],
            "precio" => (float) $_POST["precio"],
            "cantidad" => (int) $_POST["cantidad"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");

        // Tienda inicial (opcional, mejora adicional): segundo paso por
        // SOAP (LocalesService) solo si el usuario eligio una tienda.
        $tiendaId = $_POST["tienda_id"] ?? "";
        $cantidadTienda = $_POST["cantidad_tienda"] ?? "";
        if ($r->estado && $tiendaId !== "" && $cantidadTienda !== "") {
            try {
                $rTienda = $cliente->AsignarStockLocal([
                    "codigo" => $codigo,
                    "localId" => (int) $tiendaId,
                    "cantidad" => (int) $cantidadTienda,
                ]);
                flash("Tienda inicial: " . $rTienda->mensaje, $rTienda->estado ? "ok" : "error");
            } catch (SoapFault $error) {
                flash("No se pudo asignar la tienda inicial: " . $error->getMessage(), "error");
            }
        }
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

if ($metodo === "POST" && $accion === "locales") {
    try {
        $r = obtenerCliente()->CrearLocal([
            "nombre" => $_POST["nombre"],
            "lat" => (float) $_POST["lat"],
            "lng" => (float) $_POST["lng"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al crear el local: " . $error->getMessage(), "error");
    }
    irA("locales");
}

if ($metodo === "POST" && $accion === "locales-editar") {
    try {
        $r = obtenerCliente()->ActualizarLocal([
            "id" => (int) $_POST["id"],
            "nombre" => $_POST["nombre"],
            "lat" => (float) $_POST["lat"],
            "lng" => (float) $_POST["lng"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al editar el local: " . $error->getMessage(), "error");
    }
    irA("locales");
}

if ($metodo === "POST" && $accion === "locales-eliminar") {
    try {
        $r = obtenerCliente()->EliminarLocal(["id" => (int) $_POST["id"]]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al eliminar el local: " . $error->getMessage(), "error");
    }
    irA("locales");
}

if ($metodo === "POST" && $accion === "stock-local") {
    $codigo = $_POST["codigo"];
    $siguiente = $_POST["next"] ?? "consultar";
    try {
        $r = obtenerCliente()->AsignarStockLocal([
            "codigo" => $codigo,
            "localId" => (int) $_POST["localId"],
            "cantidad" => (int) $_POST["cantidad"],
        ]);
        flash($r->mensaje, $r->estado ? "ok" : "error");
    } catch (SoapFault $error) {
        flash("Error al asignar el stock: " . $error->getMessage(), "error");
    }

    if ($siguiente !== "consultar") {
        irA($siguiente, ["codigo" => $codigo]);
    }

    // Volvemos a consultar por SOAP para que la ficha no quede incompleta.
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
    } catch (SoapFault $error) {
        // sigue abajo con el fallback
    }
    irA("consultar", ["codigo" => $codigo]);
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
$stockPorLocal = [];
if ($vista === "consultar" && isset($_GET["encontrado"])) {
    $resultadoConsulta = [
        "codigo" => $_GET["codigo"] ?? "",
        "nombre" => $_GET["nombre"] ?? "",
        "categoria" => $_GET["categoria"] ?? "",
        "precio" => $_GET["precio"] ?? "",
        "cantidad" => $_GET["cantidad"] ?? "",
    ];
    $stockPorLocal = obtenerStockPorLocal($resultadoConsulta["codigo"]);
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

$resultadoValorLocal = null;
if ($vista === "valor" && isset($_GET["localId"])) {
    $resultadoValorLocal = obtenerValorLocal($_GET["localId"]);
}

$equipo = obtenerEquipo();
$instancia = obtenerInstancia();
$origenes = obtenerOrigenes();
$locales = obtenerLocales();

$localEditar = null;
if ($vista === "locales" && isset($_GET["editar"])) {
    foreach ($locales as $local) {
        if ((string) $local["id"] === (string) $_GET["editar"]) {
            $localEditar = $local;
            break;
        }
    }
}

$mapaLocales = in_array($vista, ["listar", "locales"], true) ? obtenerMapaLocales() : [];
$resumenTiendas = resumenTiendasPorProducto($mapaLocales);

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

      <div class="nav-label">Mejora adicional</div>
      <div class="nav">
        <a class="nav-item <?= $vista === "locales" ? "active" : "" ?>" href="index.php?view=locales">
          <span class="num">07</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
          Locales y stock
        </a>
      </div>

      <div class="sidebar-footer">
        <span class="instancia-pill"><span class="status-dot"></span>Servidor: <?= e($instancia) ?></span>
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

            <div class="field" style="border-top:1px solid var(--border); margin-top:0.9rem; padding-top:0.9rem;">
              <label for="r-tienda">Tienda inicial (opcional) <span class="tag" style="margin-left:0.4rem;">mejora adicional</span></label>
              <select id="r-tienda" name="tienda_id">
                <option value="">— Sin asignar —</option>
                <?php foreach ($locales as $local): ?>
                  <option value="<?= e($local["id"]) ?>"><?= e($local["nombre"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label for="r-cantidad-tienda">Cantidad en esa tienda</label><input id="r-cantidad-tienda" name="cantidad_tienda" type="number" min="0" placeholder="10"></div>

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

        <?php if ($resultadoConsulta): ?>
        <div class="grid-2" style="margin-top:1.25rem;">
          <div class="card">
            <h3>Stock por local <span class="tag">mejora adicional</span></h3>
            <div id="mapa-stock" style="height:260px; border-radius:6px; overflow:hidden; margin-bottom:0.9rem;"></div>
            <?php if ($stockPorLocal): ?>
              <div class="kv">
                <?php foreach ($stockPorLocal as $fila): ?>
                  <div class="kv-row"><span class="k"><?= e($fila["nombre"]) ?></span><span class="v"><?= e(str_pad((string) (int) $fila["cantidad"], 4, "0", STR_PAD_LEFT)) ?></span></div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="vacio-nota">Este producto todavía no tiene stock asignado a ningún local.</p>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3>Asignar / actualizar stock en un local <span class="tag">entrada</span></h3>
            <?php if ($locales): ?>
              <form method="post" action="index.php?accion=stock-local">
                <input type="hidden" name="codigo" value="<?= e($resultadoConsulta["codigo"]) ?>">
                <div class="field">
                  <label for="sl-local">Local</label>
                  <select id="sl-local" name="localId" required>
                    <?php foreach ($locales as $local): ?>
                      <option value="<?= e($local["id"]) ?>"><?= e($local["nombre"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field"><label for="sl-cantidad">Cantidad en ese local</label><input id="sl-cantidad" name="cantidad" type="number" min="0" placeholder="15" required></div>
                <button class="btn btn-primary" type="submit">Guardar</button>
              </form>
            <?php else: ?>
              <p class="vacio-nota">Todavía no hay locales creados — ve a "Locales y stock" para crear el primero.</p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
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

        <div class="card" style="margin-bottom:1.25rem;">
          <h3>Mapa de tiendas <span class="tag">mejora adicional</span></h3>
          <?php if ($mapaLocales): ?>
            <div id="mapa-listar" style="height:220px; border-radius:6px; overflow:hidden;"></div>
          <?php else: ?>
            <p class="vacio-nota">Todavía no hay tiendas creadas — ve a "Locales y stock" para crear la primera.</p>
          <?php endif; ?>
        </div>

        <div class="table-wrap">
          <table>
            <thead><tr><th>Código</th><th>Nombre</th><th>Categoría</th><th style="text-align:right;">Precio</th><th style="text-align:right;">Stock</th><th>Origen</th><th>Tiendas</th><th></th></tr></thead>
            <tbody>
              <?php if (!$productos): ?>
                <tr><td colspan="8" class="vacio">No hay productos registrados todavía.</td></tr>
              <?php endif; ?>
              <?php foreach ($productos as $p): ?>
                <tr>
                  <td class="codigo"><?= e($p->codigo) ?></td>
                  <td><?= e($p->nombre) ?></td>
                  <td><?= e($p->categoria) ?></td>
                  <td class="num">$<?= e(number_format($p->precio, 2)) ?></td>
                  <td class="num"><span class="stock-chip <?= $p->cantidad < UMBRAL_BAJO_STOCK ? "stock-low" : "stock-ok" ?>"><?= e(str_pad((string) $p->cantidad, 4, "0", STR_PAD_LEFT)) ?></span></td>
                  <td><span class="origen-chip"><?= e($origenes[$p->codigo] ?? "—") ?></span></td>
                  <td>
                    <?php if (isset($resumenTiendas[$p->codigo])): ?>
                      <span class="origen-chip"><?= e($resumenTiendas[$p->codigo]["tiendas"]) ?> tienda(s) · <?= e($resumenTiendas[$p->codigo]["total"]) ?> u.</span>
                    <?php else: ?>
                      <span class="vacio-nota" style="margin:0;">sin asignar</span>
                    <?php endif; ?>
                  </td>
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

        <div class="grid-2">
          <div class="card">
            <h3>Nueva cantidad (global) <span class="tag">entrada</span></h3>
            <form method="post" action="index.php?accion=stock">
              <input type="hidden" name="next" value="stock">
              <div class="field"><label for="s-codigo">Código</label><input id="s-codigo" name="codigo" value="<?= e($codigoPrefill) ?>" placeholder="P101" required></div>
              <div class="field"><label for="s-cantidad">Nueva cantidad</label><input id="s-cantidad" name="cantidad" type="number" min="0" placeholder="20" required></div>
              <button class="btn btn-primary" type="submit">Actualizar stock</button>
            </form>
          </div>

          <div class="card">
            <h3>Actualizar stock en una tienda <span class="tag">mejora adicional</span></h3>
            <?php if ($locales): ?>
              <form method="post" action="index.php?accion=stock-local">
                <input type="hidden" name="next" value="stock">
                <div class="field"><label for="st-codigo">Código</label><input id="st-codigo" name="codigo" value="<?= e($codigoPrefill) ?>" placeholder="P101" required></div>
                <div class="field">
                  <label for="st-local">Tienda</label>
                  <select id="st-local" name="localId" required>
                    <?php foreach ($locales as $local): ?>
                      <option value="<?= e($local["id"]) ?>"><?= e($local["nombre"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field"><label for="st-cantidad">Cantidad en esa tienda</label><input id="st-cantidad" name="cantidad" type="number" min="0" placeholder="15" required></div>
                <button class="btn btn-primary" type="submit">Guardar</button>
              </form>
            <?php else: ?>
              <p class="vacio-nota">Todavía no hay tiendas creadas — ve a "Locales y stock" para crear la primera.</p>
            <?php endif; ?>
          </div>
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

        <div class="grid-2" style="margin-top:1.25rem;">
          <div class="card">
            <h3>Valor por tienda <span class="tag">mejora adicional</span></h3>
            <?php if ($locales): ?>
              <form method="get" action="index.php">
                <input type="hidden" name="view" value="valor">
                <div class="field">
                  <label for="vl-local">Tienda</label>
                  <select id="vl-local" name="localId" required>
                    <?php foreach ($locales as $local): ?>
                      <option value="<?= e($local["id"]) ?>"><?= e($local["nombre"]) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn-primary" type="submit">Calcular</button>
              </form>
            <?php else: ?>
              <p class="vacio-nota">Todavía no hay tiendas creadas.</p>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3>Resultado por tienda <span class="tag">salida</span></h3>
            <?php if ($resultadoValorLocal && !empty($resultadoValorLocal["estado"])): ?>
              <div class="stat-tile">
                <span class="label">Valor total en <?= e($resultadoValorLocal["localNombre"]) ?></span>
                <span class="amount">$<?= e(number_format((float) $resultadoValorLocal["valorTotal"], 2)) ?></span>
              </div>
              <?php if ($resultadoValorLocal["productos"]): ?>
                <div class="kv" style="margin-top:0.9rem;">
                  <?php foreach ($resultadoValorLocal["productos"] as $p): ?>
                    <div class="kv-row"><span class="k"><?= e($p["nombre"]) ?></span><span class="v"><?= e($p["cantidad"]) ?> u. · $<?= e(number_format((float) $p["precio"], 2)) ?></span></div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="vacio-nota">Esta tienda todavía no tiene productos con stock asignado.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="vacio-nota">Todavía no has calculado el valor de ninguna tienda en esta sesión.</p>
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
              <p>El producto se borra de la memoria del servidor y, si Supabase está conectado, también de la tabla persistida (incluyendo su stock en todas las tiendas).</p>
            </div>
            <button class="btn btn-danger" type="submit">Eliminar producto</button>
          </form>
        </div>
      </section>

      <!-- ============ 07 LOCALES (mejora adicional) ============ -->
      <section class="view <?= $vista === "locales" ? "active" : "" ?>">
        <div class="topline">
          <div>
            <span class="eyebrow">Mejora adicional · LocalesService (WSDL)</span>
            <h1 class="page-title">Locales y stock por sucursal</h1>
            <p class="page-desc">Crea locales con su ubicación geográfica; luego, desde "Consultar producto", asigna cuánto stock de cada producto hay en cada local.</p>
          </div>
          <span class="status-pill"><span class="status-dot"></span>conectado a /productos?wsdl (LocalesService)</span>
        </div>

        <div class="grid-2">
          <div class="card">
            <h3><?= $localEditar ? "Editar local" : "Nuevo local" ?> <span class="tag">entrada</span></h3>
            <?php if ($localEditar): ?>
              <form method="post" action="index.php?accion=locales-editar">
                <input type="hidden" name="id" value="<?= e($localEditar["id"]) ?>">
                <div class="field"><label for="l-nombre">Nombre</label><input id="l-nombre" name="nombre" value="<?= e($localEditar["nombre"]) ?>" required></div>
                <div class="field-row">
                  <div class="field"><label for="l-lat">Latitud</label><input id="l-lat" name="lat" type="number" step="0.0001" value="<?= e($localEditar["lat"]) ?>" required></div>
                  <div class="field"><label for="l-lng">Longitud</label><input id="l-lng" name="lng" type="number" step="0.0001" value="<?= e($localEditar["lng"]) ?>" required></div>
                </div>
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="icon-btn" href="index.php?view=locales" style="margin-left:0.6rem;">Cancelar</a>
              </form>
            <?php else: ?>
              <form method="post" action="index.php?accion=locales">
                <div class="field"><label for="l-nombre">Nombre</label><input id="l-nombre" name="nombre" placeholder="Local Manta" required></div>
                <div class="field-row">
                  <div class="field"><label for="l-lat">Latitud</label><input id="l-lat" name="lat" type="number" step="0.0001" placeholder="-0.9500" required></div>
                  <div class="field"><label for="l-lng">Longitud</label><input id="l-lng" name="lng" type="number" step="0.0001" placeholder="-80.7300" required></div>
                </div>
                <button class="btn btn-primary" type="submit">Crear local</button>
              </form>
            <?php endif; ?>
          </div>

          <div class="card">
            <h3>Locales registrados <span class="tag">salida</span></h3>
            <?php if ($locales): ?>
              <div class="kv">
                <?php foreach ($locales as $local): ?>
                  <div class="kv-row">
                    <span class="k"><?= e($local["nombre"]) ?></span>
                    <span class="v"><?= e(number_format((float) $local["lat"], 4)) ?>, <?= e(number_format((float) $local["lng"], 4)) ?></span>
                    <div class="row-actions" style="margin-left:0.6rem;">
                      <a class="icon-btn" href="index.php?view=locales&editar=<?= e($local["id"]) ?>">Editar</a>
                      <form method="post" action="index.php?accion=locales-eliminar" class="inline-form"
                            onsubmit="return confirm('¿Eliminar <?= e($local["nombre"]) ?>? También se borra su stock asociado.');">
                        <input type="hidden" name="id" value="<?= e($local["id"]) ?>">
                        <button class="icon-btn icon-btn-danger" type="submit">Eliminar</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="vacio-nota">Todavía no hay locales creados.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-top:1.25rem;">
          <h3>Mapa de tiendas <span class="tag">productos y stock</span></h3>
          <?php if ($mapaLocales): ?>
            <div id="mapa-locales-central" style="height:360px; border-radius:6px; overflow:hidden;"></div>
          <?php else: ?>
            <p class="vacio-nota">Todavía no hay tiendas creadas.</p>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>

  <?php if ($resultadoConsulta || $mapaLocales): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Dibuja un mapa Leaflet con un marcador por tienda; cada popup puede
    // mostrar solo la cantidad (mapa por producto, en Consultar) o la lista
    // completa de productos + valor (mapa de tiendas, en Listar/Locales).
    function renderizarMapaTiendas(idContenedor, tiendas, popupHtml) {
      const contenedor = document.getElementById(idContenedor);
      if (!contenedor || !tiendas.length) return;
      const mapa = L.map(contenedor).setView([-1.5, -78.9], 6.2);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap'
      }).addTo(mapa);
      const puntos = [];
      tiendas.forEach(function (tienda) {
        const punto = [tienda.lat, tienda.lng];
        puntos.push(punto);
        L.marker(punto).addTo(mapa).bindPopup(popupHtml(tienda));
      });
      if (puntos.length > 1) {
        mapa.fitBounds(puntos, { padding: [24, 24] });
      } else {
        mapa.setView(puntos[0], 12);
      }
    }

    <?php if ($resultadoConsulta): ?>
    renderizarMapaTiendas(
      'mapa-stock',
      <?= json_encode(array_values($stockPorLocal)) ?>,
      function (fila) { return '<b>' + fila.nombre + '</b><br>' + fila.cantidad + ' unidades'; }
    );
    <?php endif; ?>

    <?php if ($mapaLocales): ?>
    function popupTienda(tienda) {
      const lista = tienda.productos.length
        ? tienda.productos.map(function (p) { return p.nombre + ' — ' + p.cantidad + ' u.'; }).join('<br>')
        : 'Sin stock asignado';
      return '<b>' + tienda.nombre + '</b><br>' + lista + '<br><b>Valor: $' + tienda.valorTotal.toFixed(2) + '</b>';
    }
    <?php if ($vista === "listar"): ?>
    renderizarMapaTiendas('mapa-listar', <?= json_encode(array_values($mapaLocales)) ?>, popupTienda);
    <?php elseif ($vista === "locales"): ?>
    renderizarMapaTiendas('mapa-locales-central', <?= json_encode(array_values($mapaLocales)) ?>, popupTienda);
    <?php endif; ?>
    <?php endif; ?>
  </script>
  <?php endif; ?>
</body>
</html>
