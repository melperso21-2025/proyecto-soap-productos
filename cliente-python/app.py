"""
Front web (Flask) para el cliente Python del servicio SOAP de productos.
Responsable: Israel. Complementa a client.py (mismo alcance de operaciones).

Usa el mismo mecanismo que client.py: zeep.Client(WSDL_URL) para leer el
WSDL y generar dinamicamente las 6 operaciones del servicio. La diferencia
es que aqui las llamadas se disparan desde formularios HTML en vez de estar
hardcodeadas en un script de consola.

La pagina tiene un menu lateral con las 6 operaciones (mismo orden del
enunciado). Cada accion redirige de vuelta a "/" con ?view=<operacion> para
que, tras recargar, siga viendo la pestana en la que estaba trabajando.

Requiere el servidor Node.js corriendo (npm start en /servidor).
"""

from flask import Flask, flash, redirect, render_template, request, url_for
from zeep import Client
from zeep.exceptions import Fault, TransportError

WSDL_URL = "http://localhost:8000/productos?wsdl"
VISTAS = ("registrar", "consultar", "listar", "stock", "valor", "eliminar")
UMBRAL_BAJO_STOCK = 5

app = Flask(__name__)
app.secret_key = "dev-dashboard-soap"  # solo para firmar mensajes flash en local


def obtener_cliente():
    """Crea un cliente zeep nuevo por peticion (evita estado compartido
    entre requests; el costo de releer el WSDL es despreciable aqui)."""
    return Client(WSDL_URL)


def ir_a(vista, **query):
    """Redirige a "/" dejando activa la pestana indicada."""
    if vista not in VISTAS:
        vista = "listar"
    return redirect(url_for("index", view=vista, **query))


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
    if vista == "consultar" and request.args.get("encontrado"):
        resultado_consulta = {
            "codigo": request.args.get("codigo", ""),
            "nombre": request.args.get("nombre", ""),
            "categoria": request.args.get("categoria", ""),
            "precio": request.args.get("precio", ""),
            "cantidad": request.args.get("cantidad", ""),
        }

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
    )


@app.route("/registrar", methods=["POST"])
def registrar():
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.RegistrarProducto(
            codigo=request.form["codigo"],
            nombre=request.form["nombre"],
            categoria=request.form["categoria"],
            precio=float(request.form["precio"]),
            cantidad=int(request.form["cantidad"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
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


if __name__ == "__main__":
    # debug=False a proposito: el debugger interactivo de Flask permite
    # ejecutar codigo arbitrario desde el navegador si algo falla - no debe
    # activarse ni siquiera en la demo local de la defensa.
    app.run(debug=False, port=5000)
