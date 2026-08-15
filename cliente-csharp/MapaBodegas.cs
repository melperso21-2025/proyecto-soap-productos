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

public record Bodega(string Nombre, double Lat, double Lng);

public static class MapaBodegas
{
    // Coordenadas de bodegas/tiendas de ejemplo (Ecuador). Cualquier
    // categoria que no este en este diccionario cae en BodegaPorDefecto.
    private static readonly Dictionary<string, Bodega> BodegaPorCategoria = new(StringComparer.OrdinalIgnoreCase)
    {
        ["Perifericos"] = new Bodega("Bodega Quito Norte", -0.1807, -78.4678),
        ["Computadoras"] = new Bodega("Bodega Quito Norte", -0.1807, -78.4678),
        ["Pantallas"] = new Bodega("Bodega Guayaquil", -2.1894, -79.8891),
        ["Monitores"] = new Bodega("Bodega Guayaquil", -2.1894, -79.8891),
        ["Mobiliario"] = new Bodega("Bodega Cuenca", -2.9001, -79.0059),
    };

    private static readonly Bodega BodegaPorDefecto = new("Centro de Distribución Ambato", -1.2543, -78.6229);

    private static Bodega ResolverBodega(string categoria) =>
        BodegaPorCategoria.TryGetValue(categoria, out var bodega) ? bodega : BodegaPorDefecto;

    // Devuelve la ruta del HTML generado, o null si no habia productos.
    public static string? Generar(Producto[] productos, string rutaSalida)
    {
        if (productos.Length == 0)
        {
            return null;
        }

        var grupos = productos
            .Select(p => new { Producto = p, Bodega = ResolverBodega(p.categoria) })
            .GroupBy(x => x.Bodega)
            .Select(g => new
            {
                nombre = g.Key.Nombre,
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
            .Select(p => new
            {
                p.codigo,
                p.nombre,
                p.categoria,
                p.precio,
                p.cantidad,
                ubicacion = ResolverBodega(p.categoria).Nombre,
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
        </style>
        </head>
        <body>
        <header>
          <h1>Ubicación de stock de productos</h1>
        </header>

        <div id="map"></div>

        <div id="listado">
          <h2>Productos y su ubicación</h2>
          <table>
            <thead>
              <tr>
                <th>Código</th><th>Nombre</th><th>Categoría</th>
                <th>Precio</th><th>Cantidad</th><th>Ubicación</th>
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

          bodegas.forEach(b => {
            const items = b.productos
              .map(p => `<li>${p.codigo} - ${p.nombre} (x${p.cantidad}, $${p.precio})</li>`)
              .join('');
            L.marker([b.lat, b.lng]).addTo(mapa)
              .bindPopup(`<b>${b.nombre}</b><br/>${b.productos.length} producto(s):<ul>${items}</ul>`);
          });

          const cuerpoTabla = document.getElementById('tabla-productos');
          cuerpoTabla.innerHTML = listado.map(p => `
            <tr>
              <td>${p.codigo}</td>
              <td>${p.nombre}</td>
              <td>${p.categoria}</td>
              <td>$${p.precio}</td>
              <td>${p.cantidad}</td>
              <td>${p.ubicacion}</td>
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
