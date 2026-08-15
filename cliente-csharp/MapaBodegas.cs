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

        string datosJson = JsonSerializer.Serialize(grupos);

        string html = $$"""
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="UTF-8" />
        <title>Mapa de bodegas - Productos</title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
          html, body, #map { height: 100%; margin: 0; font-family: Arial, sans-serif; }
          .leaflet-popup-content b { font-size: 14px; }
          .leaflet-popup-content ul { margin: 4px 0 0 0; padding-left: 18px; font-size: 13px; }
        </style>
        </head>
        <body>
        <div id="map"></div>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
          const bodegas = {{datosJson}};
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
