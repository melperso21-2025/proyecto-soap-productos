// Cliente SOAP en C# (.NET) para el servicio de gestion de productos.
// Responsable: Alexandra.
//
// Requisitos cubiertos (seccion 8.2 y 6 del enunciado):
//   1. Registro de dos productos.
//   2. Consulta de un producto existente.
//   3. Consulta de un producto inexistente.
//   4. Listado de productos.
//   5. Actualizacion de stock.
//   6. Calculo del valor del inventario.
//   7. Eliminacion de un producto.
//
// El proxy (ServiceReference/ProductosServiceReference.cs) se genero con
// dotnet-svcutil a partir de http://localhost:8000/productos?wsdl -- mismo
// rol que zeep en el cliente Python o SoapClient en el cliente PHP: lee el
// WSDL y crea las clases necesarias para invocar las 6 operaciones sin
// construir el XML del sobre SOAP a mano.

using System.Reflection;
using System.ServiceModel;
using ClienteSoap.ProductosService;

Console.OutputEncoding = System.Text.Encoding.UTF8;

const string WsdlUrl = "http://localhost:8000/productos?wsdl";

void Separador(string titulo)
{
    Console.WriteLine();
    Console.WriteLine(new string('=', 60));
    Console.WriteLine(titulo);
    Console.WriteLine(new string('=', 60));
}

// Imprime cualquier respuesta del servicio mostrando sus campos publicos,
// equivalente a "print(respuesta)" en Python o "print_r($respuesta)" en PHP.
void Mostrar(object respuesta)
{
    var tipo = respuesta.GetType();
    foreach (FieldInfo campo in tipo.GetFields())
    {
        object? valor = campo.GetValue(respuesta);
        if (valor is Producto[] productos)
        {
            Console.WriteLine($"{campo.Name}:");
            foreach (Producto producto in productos)
            {
                Console.WriteLine(
                    $"  - codigo={producto.codigo}, nombre={producto.nombre}, " +
                    $"categoria={producto.categoria}, precio={producto.precio}, cantidad={producto.cantidad}");
            }
        }
        else
        {
            Console.WriteLine($"{campo.Name}: {valor}");
        }
    }
}

var cliente = new ProductosPortTypeClient();

try
{
    Separador("1. Registrar dos productos");
    Mostrar(await cliente.RegistrarProductoAsync(new RegistrarProductoRequest(
        "P300", "Teclado mecanico", "Perifericos", 89.99m, 10)));
    Mostrar(await cliente.RegistrarProductoAsync(new RegistrarProductoRequest(
        "P301", "Mouse gamer", "Perifericos", 45.00m, 20)));

    Separador("1b. Caso incorrecto: codigo duplicado");
    Mostrar(await cliente.RegistrarProductoAsync(new RegistrarProductoRequest(
        "P300", "Teclado mecanico", "Perifericos", 89.99m, 10)));

    Separador("2. Consultar producto existente (P300)");
    Mostrar(await cliente.ConsultarProductoAsync(new ConsultarProductoRequest("P300")));

    Separador("3. Consultar producto inexistente (P999)");
    Mostrar(await cliente.ConsultarProductoAsync(new ConsultarProductoRequest("P999")));

    Separador("4. Listar productos");
    Mostrar(await cliente.ListarProductosAsync());

    Separador("5. Actualizar stock de P301 a 30 unidades");
    Mostrar(await cliente.ActualizarStockAsync(new ActualizarStockRequest("P301", 30)));

    Separador("6. Calcular valor del inventario de P301");
    Mostrar(await cliente.CalcularValorInventarioAsync(new CalcularValorInventarioRequest("P301")));

    Separador("7. Eliminar producto P300");
    Mostrar(await cliente.EliminarProductoAsync(new EliminarProductoRequest("P300")));

    Separador("7b. Caso incorrecto: eliminar producto ya eliminado");
    Mostrar(await cliente.EliminarProductoAsync(new EliminarProductoRequest("P300")));
}
catch (EndpointNotFoundException error)
{
    Console.WriteLine($"No se pudo conectar al servicio SOAP en {WsdlUrl}");
    Console.WriteLine($"Detalle: {error.Message}");
    Console.WriteLine("Verifica que el servidor Node.js este corriendo (npm start en /servidor).");
}
catch (FaultException error)
{
    Console.WriteLine($"Error SOAP devuelto por el servidor: {error.Message}");
}
catch (CommunicationException error)
{
    Console.WriteLine($"Error de conexion durante la operacion: {error.Message}");
}
finally
{
    ((ICommunicationObject)cliente).Close();
}
