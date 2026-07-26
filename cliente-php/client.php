<?php
/**
 * Cliente SOAP en PHP para el servicio de gestion de productos.
 * Responsable: Diego.
 *
 * Requisitos cubiertos (seccion 8.2 y 6 del enunciado):
 *   1. Registro de dos productos.
 *   2. Consulta de un producto existente.
 *   3. Consulta de un producto inexistente.
 *   4. Listado de productos.
 *   5. Actualizacion de stock.
 *   6. Calculo del valor del inventario.
 *   7. Eliminacion de un producto.
 *
 * Requiere la extension nativa "soap" de PHP habilitada en php.ini
 * (extension=soap).
 */

const WSDL_URL = "http://localhost:8000/productos?wsdl";

function separador($titulo) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo $titulo . "\n";
    echo str_repeat("=", 60) . "\n";
}

function mostrar($respuesta) {
    print_r($respuesta);
}

function main() {
    try {
        $cliente = new SoapClient(WSDL_URL, [
            "trace" => true,
            "exceptions" => true,
            "connection_timeout" => 5,
        ]);
    } catch (SoapFault $error) {
        echo "No se pudo conectar al servicio SOAP en " . WSDL_URL . "\n";
        echo "Detalle: " . $error->getMessage() . "\n";
        echo "Verifica que el servidor Node.js este corriendo (npm start en /servidor).\n";
        return;
    }

    try {
        separador("1. Registrar dos productos");
        mostrar($cliente->RegistrarProducto([
            "codigo" => "P200", "nombre" => "Silla ergonomica", "categoria" => "Mobiliario",
            "precio" => 120.00, "cantidad" => 12,
        ]));
        mostrar($cliente->RegistrarProducto([
            "codigo" => "P201", "nombre" => "Escritorio", "categoria" => "Mobiliario",
            "precio" => 210.75, "cantidad" => 6,
        ]));

        separador("1b. Caso incorrecto: codigo duplicado");
        mostrar($cliente->RegistrarProducto([
            "codigo" => "P200", "nombre" => "Silla ergonomica", "categoria" => "Mobiliario",
            "precio" => 120.00, "cantidad" => 12,
        ]));

        separador("2. Consultar producto existente (P200)");
        mostrar($cliente->ConsultarProducto(["codigo" => "P200"]));

        separador("3. Consultar producto inexistente (P999)");
        mostrar($cliente->ConsultarProducto(["codigo" => "P999"]));

        separador("4. Listar productos");
        mostrar($cliente->ListarProductos([]));

        separador("5. Actualizar stock de P201 a 25 unidades");
        mostrar($cliente->ActualizarStock(["codigo" => "P201", "cantidad" => 25]));

        separador("6. Calcular valor del inventario de P201");
        mostrar($cliente->CalcularValorInventario(["codigo" => "P201"]));

        separador("7. Eliminar producto P200");
        mostrar($cliente->EliminarProducto(["codigo" => "P200"]));

        separador("7b. Caso incorrecto: eliminar producto ya eliminado");
        mostrar($cliente->EliminarProducto(["codigo" => "P200"]));

    } catch (SoapFault $error) {
        echo "Error SOAP devuelto por el servidor: " . $error->getMessage() . "\n";
    }
}

main();
