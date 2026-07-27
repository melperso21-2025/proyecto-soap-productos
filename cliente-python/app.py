"""
Front web (Flask) para el cliente Python del servicio SOAP de productos.
Responsable: Israel. Complementa a client.py (mismo alcance de operaciones).

Usa el mismo mecanismo que client.py: zeep.Client(WSDL_URL) para leer el
WSDL y generar dinamicamente las 6 operaciones del servicio. La diferencia
es que aqui las llamadas se disparan desde formularios HTML en vez de estar
hardcodeadas en un script de consola.

Requiere el servidor Node.js corriendo (npm start en /servidor).
"""

from flask import Flask, flash, redirect, render_template, request, url_for
from zeep import Client
from zeep.exceptions import Fault, TransportError

WSDL_URL = "http://localhost:8000/productos?wsdl"

app = Flask(__name__)
app.secret_key = "dev-dashboard-soap"  # solo para firmar mensajes flash en local


def obtener_cliente():
    """Crea un cliente zeep nuevo por peticion (evita estado compartido
    entre requests; el costo de releer el WSDL es despreciable aqui)."""
    return Client(WSDL_URL)


@app.route("/")
def index():
    productos = []
    try:
        cliente = obtener_cliente()
        respuesta = cliente.service.ListarProductos()
        # zeep devuelve la lista "productos" directamente (sin envoltorio)
        # cuando la respuesta SOAP tiene un unico campo repetible.
        productos = getattr(respuesta, "productos", respuesta) or []
    except (TransportError, ConnectionError) as error:
        flash(f"No se pudo conectar al servicio SOAP en {WSDL_URL}: {error}", "error")
    return render_template("index.html", productos=productos)


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
    return redirect(url_for("index"))


@app.route("/consultar", methods=["POST"])
def consultar():
    codigo = request.form["codigo"]
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.ConsultarProducto(codigo=codigo)
        if resultado.estado:
            flash(
                f"{resultado.codigo} - {resultado.nombre} | {resultado.categoria} "
                f"| precio {resultado.precio} | stock {resultado.cantidad}",
                "ok",
            )
        else:
            flash(resultado.mensaje, "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al consultar: {error}", "error")
    return redirect(url_for("index"))


@app.route("/actualizar-stock", methods=["POST"])
def actualizar_stock():
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.ActualizarStock(
            codigo=request.form["codigo"],
            cantidad=int(request.form["cantidad"]),
        )
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError, ValueError) as error:
        flash(f"Error al actualizar stock: {error}", "error")
    return redirect(url_for("index"))


@app.route("/calcular-valor/<codigo>")
def calcular_valor(codigo):
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.CalcularValorInventario(codigo=codigo)
        if resultado.estado:
            flash(
                f"Valor de inventario de {codigo}: {resultado.cantidad} x "
                f"{resultado.precio} = {resultado.valorTotal}",
                "ok",
            )
        else:
            flash(resultado.mensaje, "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al calcular valor: {error}", "error")
    return redirect(url_for("index"))


@app.route("/eliminar/<codigo>", methods=["POST"])
def eliminar(codigo):
    try:
        cliente = obtener_cliente()
        resultado = cliente.service.EliminarProducto(codigo=codigo)
        flash(resultado.mensaje, "ok" if resultado.estado else "error")
    except (Fault, TransportError, ConnectionError) as error:
        flash(f"Error al eliminar: {error}", "error")
    return redirect(url_for("index"))


if __name__ == "__main__":
    app.run(debug=True, port=5000)
