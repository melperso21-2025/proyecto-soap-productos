"""
Front web (Flask) para el cliente Python del servicio SOAP de productos.
Responsable: Israel. Complementa a client.py (mismo alcance de operaciones).

Usa el mismo mecanismo que client.py: zeep.Client(WSDL_URL) para leer el
WSDL y generar dinamicamente las operaciones del servicio. La diferencia es
que aqui las llamadas se disparan desde formularios HTML en vez de estar
hardcodeadas en un script de consola.

El WSDL declara DOS servicios: ProductosService (las 6 operaciones
calificadas de la tarea, sin cambios) y LocalesService (la mejora
adicional de locales/mapa/stock por tienda) -- ambos por SOAP real, cada
uno en su propio endpoint. zeep necesita bind(servicio, puerto) explicito
para hablar con el segundo servicio.

La pagina tiene un menu lateral con las 6 operaciones (mismo orden del
enunciado) + la mejora adicional. Cada accion redirige de vuelta a "/" con
?view=<operacion> para que, tras recargar, siga viendo la pestana en la
que estaba trabajando.

Requiere el servidor Node.js corriendo (npm start en /servidor).
"""

from decimal import Decimal

import requests
from flask import Flask, flash, redirect, render_template, request, url_for
from zeep import Client
from zeep.exceptions import Fault, TransportError
from zeep.helpers import serialize_object

WSDL_URL = "http://localhost:8000/productos?wsdl"
API_EQUIPO_URL = "http://localhost:8000/api/equipo"
API_INSTANCIA_URL = "http://localhost:8000/api/instancia"
API_PRODUCTOS_URL = "http://localhost:8000/api/productos"
VISTAS = ("registrar", "consultar", "listar", "stock", "valor", "eliminar", "locales")
UMBRAL_BAJO_STOCK = 5

app = Flask(__name__)
app.secret_key = "dev-dashboard-soap"  # solo para firmar mensajes flash en local


def obtener_cliente():
    """Crea un cliente zeep nuevo por peticion (evita estado compartido
    entre requests; el costo de releer el WSDL es despreciable aqui).
    Habla con ProductosService (las 6 operaciones calificadas)."""
    return Client(WSDL_URL)


def obtener_cliente_locales():
    """Cliente SOAP para la mejora adicional (LocalesService) -- portType
    separado del contrato calificado, ver productos.wsdl."""
    return Client(WSDL_URL).bind("LocalesService", "LocalesPort")


def _limpiar(valor):
    """zeep devuelve xsd:decimal como Decimal de Python, que ni Jinja
    "tojson" ni el propio json de Flask saben serializar. Convertimos a
    tipos nativos (float/int/str/dict/list) de forma recursiva antes de
    pasarlo a la plantilla."""
    if isinstance(valor, Decimal):
        return float(valor)
    if isinstance(valor, dict):
        return {k: _limpiar(v) for k, v in valor.items()}
    if isinstance(valor, list):
        return [_limpiar(v) for v in valor]
    return valor


def _serializar(valor):
    """serialize_object() convierte los CompoundValue de zeep en dict/list
    planos; _limpiar() se encarga de los Decimal que quedan adentro."""
    return _limpiar(serialize_object(valor, target_cls=dict))


def ir_a(vista, **query):
    """Redirige a "/" dejando activa la pestana indicada."""
    if vista not in VISTAS:
        vista = "listar"
    return redirect(url_for("index", view=vista, **query))


def obtener_equipo():
    """Consulta la API REST del servidor (no SOAP) para mostrar quien
    hizo el proyecto - demuestra el uso real de la capa REST."""
    try:
        respuesta = requests.get(API_EQUIPO_URL, timeout=3)
        respuesta.raise_for_status()
        return respuesta.json()
    except requests.RequestException:
        return []


def obtener_origenes():
    """Mapa codigo -> origen (que instancia local registro el producto).
    El WSDL no expone este campo (no esta en el contrato SOAP obligatorio),
    asi que se completa aparte consultando la API REST del mismo servidor."""
    try:
        respuesta = requests.get(API_PRODUCTOS_URL, timeout=3)
        respuesta.raise_for_status()
        datos = respuesta.json().get("productos", [])
        return {p["codigo"]: p.get("origen") for p in datos}
    except requests.RequestException:
        return {}


def obtener_locales():
    """Lista de locales (sucursales) via SOAP (LocalesService) -- mejora
    adicional, portType separado de las 6 operaciones calificadas."""
    try:
        locales = obtener_cliente_locales().ListarLocales()
        return _serializar(locales)
    except (Fault, TransportError, ConnectionError):
        return []


def obtener_stock_local(codigo):
    """Cantidad de un producto por local, para dibujar el mapa junto a
    la ficha de ConsultarProducto."""
    try:
        resultado = obtener_cliente_locales().ConsultarStockPorLocal(codigo=codigo)
        if not resultado.estado:
            return []
        return _serializar(resultado.stockPorLocal or [])
    except (Fault, TransportError, ConnectionError):
        return []


def obtener_mapa_locales():
    """Todas las tiendas con sus productos y valor de inventario, en una
    sola llamada SOAP. Alimenta el mapa central de "Locales y stock" y el
    mini-mapa de "Listar productos"."""
    try:
        mapa = obtener_cliente_locales().ObtenerMapaLocales()
        return _serializar(mapa)
    except (Fault, TransportError, ConnectionError):
        return []


def resumen_tiendas_por_producto(mapa_locales):
    """codigo -> {tiendas, total} a partir del mapa completo, para la
    columna "Tiendas" de Listar productos."""
    resumen = {}
    for local in mapa_locales:
        for producto in local.get("productos", []):
            fila = resumen.setdefault(producto["codigo"], {"tiendas": 0, "total": 0})
            fila["tiendas"] += 1
            fila["total"] += producto["cantidad"]
    return resumen


def obtener_valor_local(local_id):
    """Valor de inventario de una tienda especifica (precio x stock_local),
    por SOAP. Extiende CalcularValorInventario (global) sin tocarla."""
    try:
        resultado = obtener_cliente_locales().CalcularValorPorLocal(localId=int(local_id))
        return _serializar(resultado)
    except (Fault, TransportError, ConnectionError, ValueError):
        return None


def obtener_instancia():
    """De que maquina viene el servidor al que este front esta conectado.
    Los 3 integrantes corren su propio server.js apuntando al mismo Supabase,
    asi que esto evita confundir el origen de los datos que se ven aqui."""
    try:
        respuesta = requests.get(API_INSTANCIA_URL, timeout=3)
        respuesta.raise_for_status()
        return respuesta.json().get("nombre", "desconocido")
    except requests.RequestException:
        return "desconocido"


@app.route("/")
def index():
    vista = request.args.get("view", "listar")
    if vista not in VISTAS:
        vista = "listar"

    productos = []
    try:
        cliente = obtener_cliente()
        respuesta = cliente.service.ListarProductos()
        # zeep devuelve la lista "productos" directamente (sin envoltorio)
        # cuando la respuesta SOAP tiene un unico campo repetible.
        productos = getattr(respuesta, "productos", respuesta) or []
    except (TransportError, ConnectionError) as error:
        flash(f"No se pudo conectar al servicio SOAP en {WSDL_URL}: {error}", "error")

    valor_total_inventario = sum((p.precio or 0) * (p.cantidad or 0) for p in productos)
    bajo_stock = sum(1 for p in productos if (p.cantidad or 0) < UMBRAL_BAJO_STOCK)

    # Resultado estructurado de ConsultarProducto, si venimos de ese POST.
    resultado_consulta = None
    stock_por_local = []
    if vista == "consultar" and request.args.get("encontrado"):
        resultado_consulta = {
            "codigo": request.args.get("codigo", ""),
            "nombre": request.args.get("nombre", ""),
            "categoria": request.args.get("categoria", ""),
            "precio": request.args.get("precio", ""),
            "cantidad": request.args.get("cantidad", ""),
        }
        stock_por_local = obtener_stock_local(resultado_consulta["codigo"])

    # Resultado estructurado de CalcularValorInventario, si venimos de ese POST.
    resultado_valor = None
    if vista == "valor" and request.args.get("valorTotal"):
        resultado_valor = {
            "codigo": request.args.get("codigo", ""),
            "nombre": request.args.get("nombre", ""),
            "precio": request.args.get("precio", ""),
            "cantidad": request.args.get("cantidad", ""),
            "valorTotal": request.args.get("valorTotal", ""),
        }

    # Valor de inventario de una tienda especifica, si venimos de ese GET.
    resultado_valor_local = None
    if vista == "valor" and request.args.get("localId"):
        resultado_valor_local = obtener_valor_local(request.args["localId"])

    locales = obtener_locales()

    # Modo edicion de un local: prellena el formulario de "Nuevo local"
    # con los datos del local elegido, en vez de crear uno nuevo.
    local_editar = None
    if vista == "locales" and request.args.get("editar"):
        local_editar = next(
            (l for l in locales if str(l["id"]) == request.args["editar"]), None
        )

    mapa_locales = obtener_mapa_locales() if vista in ("listar", "locales") else []

    return render_template(
        "index.html",
        vista=vista,
        productos=productos,
        total_productos=len(productos),
        valor_total_inventario=valor_total_inventario,
        bajo_stock=bajo_stock,
        umbral_bajo_stock=UMBRAL_BAJO_STOCK,
        codigo_prefill=request.args.get("codigo", ""),
        resultado_consulta=resultado_consulta,
        resultado_valor=resultado_valor,
        resultado_valor_local=resultado_valor_local,
        equipo=obtener_equipo(),
        instancia=obtener_instancia(),
        origenes=obtener_origenes(),
        locales=locales,
        local_editar=local_editar,
        mapa_locales=mapa_locales,
        resumen_tiendas=resumen_tiendas_por_producto(mapa_locales),
        stock_por_local=stock_por_local,
    )


@app.route("/registrar", methods=["POST"])
def registrar():
    codigo = request.form["codigo"]
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.RegistrarProducto(
            codigo=codigo,
            nombre=request.form["nombre"],
            categoria=request.form["categoria"],
            precio=float(request.form["precio"]),
            cantidad=int(request.form["cantidad"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")

        # Tienda inicial (opcional, mejora adicional): segundo paso por
        # SOAP (LocalesService) solo si el usuario eligio una tienda.
        tienda_id = request.form.get("tienda_id", "")
        cantidad_tienda = request.form.get("cantidad_tienda", "")
        if resultado.estado and tienda_id and cantidad_tienda:
            try:
                resultado_tienda = obtener_cliente_locales().AsignarStockLocal(
                    codigo=codigo, localId=int(tienda_id), cantidad=int(cantidad_tienda)
                )
                flash(
                    f"Tienda inicial: {resultado_tienda.mensaje}",
                    "ok" if resultado_tienda.estado else "error",
                )
            except (Fault, TransportError, ConnectionError, ValueError) as error:
                flash(f"No se pudo asignar la tienda inicial: {error}", "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al registrar: {error}", "error")
    return ir_a("registrar")


@app.route("/consultar", methods=["POST"])
def consultar():
    codigo = request.form["codigo"]
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.ConsultarProducto(codigo=codigo)
        if resultado.estado:
            return ir_a(
                "consultar",
                codigo=resultado.codigo,
                nombre=resultado.nombre,
                categoria=resultado.categoria,
                precio=resultado.precio,
                cantidad=resultado.cantidad,
                encontrado="1",
            )
        flash(resultado.mensaje, "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al consultar: {error}", "error")
    return ir_a("consultar", codigo=codigo)


@app.route("/actualizar-stock", methods=["POST"])
def actualizar_stock():
    siguiente = request.form.get("next", "stock")
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.ActualizarStock(
            codigo=request.form["codigo"],
            cantidad=int(request.form["cantidad"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al actualizar stock: {error}", "error")
    return ir_a(siguiente)


@app.route("/calcular-valor", methods=["GET", "POST"])
def calcular_valor():
    codigo = request.values.get("codigo", "")
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.CalcularValorInventario(codigo=codigo)
        if resultado.estado:
            return ir_a(
                "valor",
                codigo=codigo,
                nombre=resultado.nombre,
                precio=resultado.precio,
                cantidad=resultado.cantidad,
                valorTotal=resultado.valorTotal,
            )
        flash(resultado.mensaje, "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al calcular valor: {error}", "error")
    return ir_a("valor", codigo=codigo)


@app.route("/eliminar", methods=["POST"])
def eliminar():
    codigo = request.form["codigo"]
    siguiente = request.form.get("next", "eliminar")
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.EliminarProducto(codigo=codigo)
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al eliminar: {error}", "error")
    return ir_a(siguiente)


@app.route("/locales", methods=["POST"])
def crear_local():
    try:
        resultado = obtener_cliente_locales().CrearLocal(
            nombre=request.form["nombre"],
            lat=float(request.form["lat"]),
            lng=float(request.form["lng"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al crear el local: {error}", "error")
    return ir_a("locales")


@app.route("/locales/editar", methods=["POST"])
def editar_local():
    try:
        resultado = obtener_cliente_locales().ActualizarLocal(
            id=int(request.form["id"]),
            nombre=request.form["nombre"],
            lat=float(request.form["lat"]),
            lng=float(request.form["lng"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al editar el local: {error}", "error")
    return ir_a("locales")


@app.route("/locales/eliminar", methods=["POST"])
def eliminar_local():
    try:
        resultado = obtener_cliente_locales().EliminarLocal(id=int(request.form["id"]))
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al eliminar el local: {error}", "error")
    return ir_a("locales")


@app.route("/stock-local", methods=["POST"])
def asignar_stock_local():
    codigo = request.form["codigo"]
    siguiente = request.form.get("next", "consultar")
    try:
        resultado = obtener_cliente_locales().AsignarStockLocal(
            codigo=codigo, localId=int(request.form["localId"]), cantidad=int(request.form["cantidad"])
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al asignar el stock: {error}", "error")

    if siguiente != "consultar":
        return ir_a(siguiente, codigo=codigo)

    # Volvemos a consultar el producto por SOAP para que la ficha (nombre,
    # categoria, precio...) no quede incompleta al regresar a la vista.
    try:
        cliente = obtener_cliente()
        producto = cliente.service.ConsultarProducto(codigo=codigo)
        if producto.estado:
            return ir_a(
                "consultar",
                codigo=producto.codigo,
                nombre=producto.nombre,
                categoria=producto.categoria,
                precio=producto.precio,
                cantidad=producto.cantidad,
                encontrado="1",
            )
    except (Fault, TransportError, ConnectionError):
        pass
    return ir_a("consultar", codigo=codigo)


if __name__ == "__main__":
    # debug=False a proposito: el debugger interactivo de Flask permite
    # ejecutar codigo arbitrario desde el navegador si algo falla - no debe
    # activarse ni siquiera en la demo local de la defensa.
    app.run(debug=False, port=5000)
