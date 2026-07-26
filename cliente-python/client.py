"""
Cliente SOAP en Python para el servicio de gestion de productos.
Responsable: Israel.

Requisitos cubiertos (seccion 8.2 y 6 del enunciado):
  1. Registro de dos productos.
  2. Consulta de un producto existente.
  3. Consulta de un producto inexistente.
  4. Listado de productos.
  5. Actualizacion de stock.
  6. Calculo del valor del inventario.
  7. Eliminacion de un producto.
"""

import sys

from zeep import Client
from zeep.exceptions import Fault, TransportError

# En Windows la consola por defecto usa cp1252 y deforma tildes/eñes al
# imprimir las respuestas del servidor (que vienen en UTF-8).
sys.stdout.reconfigure(encoding="utf-8")

WSDL_URL = "http://localhost:8000/productos?wsdl"


def separador(titulo):
    print("\n" + "=" * 60)
    print(titulo)
    print("=" * 60)


def mostrar(respuesta):
    print(respuesta)


def main():
    try:
        cliente = Client(WSDL_URL)
    except (TransportError, ConnectionError) as error:
        print(f"No se pudo conectar al servicio SOAP en {WSDL_URL}")
        print(f"Detalle: {error}")
        print("Verifica que el servidor Node.js este corriendo (npm start en /servidor).")
        return

    servicio = cliente.service

    try:
        separador("1. Registrar dos productos")
        mostrar(servicio.RegistrarProducto(
            codigo="P100", nombre="Laptop Lenovo", categoria="Computadoras",
            precio=650.00, cantidad=8,
        ))
        mostrar(servicio.RegistrarProducto(
            codigo="P101", nombre="Monitor 24 pulgadas", categoria="Perifericos",
            precio=145.50, cantidad=15,
        ))

        separador("1b. Caso incorrecto: codigo duplicado")
        mostrar(servicio.RegistrarProducto(
            codigo="P100", nombre="Laptop Lenovo", categoria="Computadoras",
            precio=650.00, cantidad=8,
        ))

        separador("2. Consultar producto existente (P100)")
        mostrar(servicio.ConsultarProducto(codigo="P100"))

        separador("3. Consultar producto inexistente (P999)")
        mostrar(servicio.ConsultarProducto(codigo="P999"))

        separador("4. Listar productos")
        mostrar(servicio.ListarProductos())

        separador("5. Actualizar stock de P101 a 20 unidades")
        mostrar(servicio.ActualizarStock(codigo="P101", cantidad=20))

        separador("6. Calcular valor del inventario de P101")
        mostrar(servicio.CalcularValorInventario(codigo="P101"))

        separador("7. Eliminar producto P100")
        mostrar(servicio.EliminarProducto(codigo="P100"))

        separador("7b. Caso incorrecto: eliminar producto ya eliminado")
        mostrar(servicio.EliminarProducto(codigo="P100"))

    except Fault as error:
        print(f"Error SOAP devuelto por el servidor: {error}")
    except (TransportError, ConnectionError) as error:
        print(f"Error de conexion durante la operacion: {error}")


if __name__ == "__main__":
    main()
