// Genera un mapa Leaflet (HTML standalone) ubicando cada producto en la
// bodega/tienda que corresponde a su categoria, y lo abre en el navegador.
//
// No se agrego un campo de ubicacion al esquema compartido (WSDL,
// validaciones.js, tabla productos de Supabase) porque eso es
// infraestructura que usan los 3 integrantes -- este mapeo categoria ->
// bodega vive solo aqui, en el cliente C#, sin tocar el servidor.

using System.Text.Json;
using ClienteSoap.ProductosService;

namespace ClienteSoap;

public record Bodega(string Nombre, string Localizacion, double Lat, double Lng);

public static class MapaBodegas
{
    // Bodegas/tiendas reales (Ecuador) donde puede quedar un producto.
    private static readonly Bodega BodegaQuito = new("Bodega Quito Norte", "Uio", -0.1807, -78.4678);
    private static readonly Bodega BodegaGuayaquil = new("Bodega Guayaquil", "Gye", -2.1894, -79.8891);
    private static readonly Bodega BodegaGuayaquilCentro = new("Bodega Guayaquil Centro", "Gye", -2.1710, -79.9224);
    private static readonly Bodega BodegaCuenca = new("Bodega Cuenca", "Cue", -2.9001, -79.0059);
    private static readonly Bodega BodegaAmbato = new("Centro de Distribución Ambato", "Amb", -1.2543, -78.6229);

    // Bodega por defecto si la categoria no esta mapeada abajo.
    private static readonly Bodega BodegaPorDefecto = BodegaQuito;

    private static readonly Dictionary<string, Bodega> BodegaPorCategoria = new(StringComparer.OrdinalIgnoreCase)
    {
        ["Perifericos"] = BodegaQuito,
        ["Computadoras"] = BodegaQuito,
        ["Laptop"] = BodegaQuito,
        ["Laptops"] = BodegaQuito,
        ["Pantallas"] = BodegaGuayaquil,
        ["Monitores"] = BodegaGuayaquil,
        ["Mobiliario"] = BodegaCuenca,
    };

    // Casos puntuales por codigo de producto, tienen prioridad sobre la
    // categoria (para repartir los productos entre varias ciudades reales
    // en vez de que todos caigan en la misma bodega por categoria).
    private static readonly Dictionary<string, Bodega> BodegaPorCodigoProducto = new(StringComparer.OrdinalIgnoreCase)
    {
        ["P300"] = BodegaGuayaquilCentro,
        ["P002"] = BodegaAmbato,
        ["P004"] = BodegaAmbato,
        ["P006"] = BodegaGuayaquil,
        ["P008"] = BodegaGuayaquil,
    };

    private static Bodega ResolverBodega(Producto producto)
    {
        if (BodegaPorCodigoProducto.TryGetValue(producto.codigo, out var porCodigo))
        {
            return porCodigo;
        }
        return BodegaPorCategoria.TryGetValue(producto.categoria, out var porCategoria) ? porCategoria : BodegaPorDefecto;
    }

    // Devuelve la ruta del HTML generado, o null si no habia productos.
    public static string? Generar(Producto[] productos, string rutaSalida)
    {
        if (productos.Length == 0)
        {
            return null;
        }

        var grupos = productos
            .Select(p => new { Producto = p, Bodega = ResolverBodega(p) })
            .GroupBy(x => x.Bodega)
            .Select(g => new
            {
                nombre = g.Key.Nombre,
                localizacion = g.Key.Localizacion,
                lat = g.Key.Lat,
                lng = g.Key.Lng,
                productos = g.Select(x => new
                {
                    x.Producto.codigo,
                    x.Producto.nombre,
                    x.Producto.categoria,
                    x.Producto.precio,
                    x.Producto.cantidad,
                }).ToArray(),
            })
            .ToArray();

        var listado = productos
            .Select(p =>
            {
                var bodega = ResolverBodega(p);
                return new
                {
                    p.codigo,
                    p.nombre,
                    p.categoria,
                    p.precio,
                    p.cantidad,
                    ubicacion = bodega.Nombre,
                    localizacion = bodega.Localizacion,
                    lat = bodega.Lat,
                    lng = bodega.Lng,
                };
            })
            .ToArray();

        string datosJson = JsonSerializer.Serialize(grupos);
        string listadoJson = JsonSerializer.Serialize(listado);

        string html = $$"""
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="UTF-8" />
        <title>Ubicación de stock de productos</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
          html, body { height: 100%; margin: 0; font-family: Arial, sans-serif; }
          body { display: flex; flex-direction: column; }

          header { flex: 0 0 auto; background: #2c3e50; color: white; padding: 14px 24px; }
          header h1 { margin: 0; font-size: 20px; }

          #map { flex: 0 0 50vh; width: 100%; }

          #listado { flex: 1 1 auto; overflow-y: auto; padding: 16px 24px; }
          #listado h2 { margin: 0 0 10px 0; font-size: 16px; color: #2c3e50; }
          table { width: 100%; border-collapse: collapse; font-size: 14px; }
          th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #ddd; }
          th { background: #f4f4f4; position: sticky; top: 0; }
          tr:hover { background: #fafafa; }

          .leaflet-popup-content b { font-size: 14px; }
          .leaflet-popup-content ul { margin: 4px 0 0 0; padding-left: 18px; font-size: 13px; }

          #listado h2 { display: flex; align-items: center; gap: 12px; }
          #boton-mostrar-todos {
            font-size: 12px; padding: 4px 10px; border: 1px solid #2c3e50;
            background: white; color: #2c3e50; border-radius: 4px; cursor: pointer;
          }
          #boton-mostrar-todos:hover { background: #2c3e50; color: white; }
          .nombre-producto { color: #2563eb; cursor: pointer; text-decoration: underline; }
          .nombre-producto:hover { color: #1d4ed8; }
        </style>
        </head>
        <body>
        <header>
          <h1>Ubicación de stock de productos</h1>
        </header>

        <div id="map"></div>

        <div id="listado">
          <h2>
            Productos y su ubicación
            <button id="boton-mostrar-todos" onclick="mostrarTodos()">Mostrar todos</button>
          </h2>
          <table>
            <thead>
              <tr>
                <th>Código</th><th>Nombre</th><th>Categoría</th>
                <th>Precio</th><th>Cantidad</th><th>Ubicación</th><th>Localización</th>
              </tr>
            </thead>
            <tbody id="tabla-productos"></tbody>
          </table>
        </div>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
          const bodegas = {{datosJson}};
          const listado = {{listadoJson}};

          const mapa = L.map('map').setView([-1.5, -78.9], 6.5);

          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          }).addTo(mapa);

          // Capa reemplazable: se vacia y se vuelve a llenar segun se pida
          // ver todas las bodegas o solo la de un producto.
          const capaMarcadores = L.layerGroup().addTo(mapa);

          function mostrarTodos() {
            capaMarcadores.clearLayers();
            bodegas.forEach(b => {
              const items = b.productos
                .map(p => `<li>${p.codigo} - ${p.nombre} (x${p.cantidad}, $${p.precio})</li>`)
                .join('');
              L.marker([b.lat, b.lng]).addTo(capaMarcadores)
                .bindPopup(`<b>${b.nombre} (${b.localizacion})</b><br/>${b.productos.length} producto(s):<ul>${items}</ul>`);
            });
            mapa.setView([-1.5, -78.9], 6.5);
          }

          function mostrarSoloProducto(indice) {
            const p = listado[indice];
            capaMarcadores.clearLayers();
            L.marker([p.lat, p.lng]).addTo(capaMarcadores)
              .bindPopup(`<b>${p.ubicacion} (${p.localizacion})</b><br/>${p.codigo} - ${p.nombre} (x${p.cantidad}, $${p.precio})`)
              .openPopup();
            mapa.setView([p.lat, p.lng], 12);
          }

          mostrarTodos();

          const cuerpoTabla = document.getElementById('tabla-productos');
          cuerpoTabla.innerHTML = listado.map((p, indice) => `
            <tr>
              <td>${p.codigo}</td>
              <td><span class="nombre-producto" onclick="mostrarSoloProducto(${indice})">${p.nombre}</span></td>
              <td>${p.categoria}</td>
              <td>$${p.precio}</td>
              <td>${p.cantidad}</td>
              <td>${p.ubicacion}</td>
              <td>${p.localizacion}</td>
            </tr>
          `).join('');
        </script>
        </body>
        </html>
        """;

        File.WriteAllText(rutaSalida, html);
        return rutaSalida;
    }

    public static void AbrirEnNavegador(string ruta)
    {
        try
        {
            System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo(ruta) { UseShellExecute = true });
        }
        catch (Exception error)
        {
            Console.WriteLine($"No se pudo abrir el navegador automáticamente ({error.Message}).");
            Console.WriteLine($"Abre el archivo manualmente: {ruta}");
        }
    }
}
