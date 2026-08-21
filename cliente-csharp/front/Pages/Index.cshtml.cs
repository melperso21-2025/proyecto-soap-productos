// Front web en ASP.NET Core (Razor Pages) para el cliente SOAP en C#.
// Responsable: Alexandra. Espejo funcional de app.py (Python) e index.php
// (PHP): mismo menu lateral con las 6 operaciones + la mejora adicional de
// locales/mapa, cada front con su propio color.
//
// El WSDL declara DOS servicios: ProductosService (las 6 operaciones
// calificadas, sin cambios) y LocalesService (mejora adicional de
// locales/mapa/stock por tienda) -- ambos por SOAP real, cada uno con su
// propio cliente generado (ProductosPortTypeClient / LocalesPortTypeClient)
// via dotnet-svcutil, apuntando cada uno a su propio endpoint.

using System.Text.Json;
using System.Text.Json.Serialization;
using ClienteSoap.ProductosService;
using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;

namespace Front.Pages;

public record LocalDto(int id, string nombre, double lat, double lng);
public record StockLocalDto(int localId, string nombre, double lat, double lng, int cantidad);
public record IntegranteDto(string nombre, string rol);
public record ResultadoConsulta(string Codigo, string Nombre, string Categoria, string Precio, string Cantidad);
public record ResultadoValor(string Codigo, string Nombre, string Precio, string Cantidad, string ValorTotal);
public record ProductoLocalDto(string codigo, string nombre, decimal precio, int cantidad);
public record MapaLocalDto(int id, string nombre, double lat, double lng, List<ProductoLocalDto> productos, decimal valorTotal);
public record ResumenTiendaDto(int Tiendas, int Total);
public record ResultadoValorLocal(bool Estado, string LocalNombre, List<ProductoLocalDto> Productos, decimal ValorTotal);

public class IndexModel : PageModel
{
    private const string WsdlUrl = "http://localhost:8000/productos?wsdl";
    private const string ApiEquipoUrl = "http://localhost:8000/api/equipo";
    private const string ApiInstanciaUrl = "http://localhost:8000/api/instancia";
    private const string ApiProductosUrl = "http://localhost:8000/api/productos";
    private static readonly string[] Vistas = { "registrar", "consultar", "listar", "stock", "valor", "eliminar", "locales" };
    private const int UmbralBajoStock = 5;

    private static readonly HttpClient Http = new() { Timeout = TimeSpan.FromSeconds(3) };
    private static readonly JsonSerializerOptions JsonOpts = new(JsonSerializerDefaults.Web);

    public string Vista { get; set; } = "listar";
    public List<Producto> Productos { get; set; } = new();
    public int TotalProductos => Productos.Count;
    public decimal ValorTotalInventario => Productos.Sum(p => p.precio * p.cantidad);
    public int BajoStock => Productos.Count(p => p.cantidad < UmbralBajoStock);
    public string CodigoPrefill { get; set; } = "";
    public ResultadoConsulta? ResultadoConsultaData { get; set; }
    public ResultadoValor? ResultadoValorData { get; set; }
    public List<LocalDto> Locales { get; set; } = new();
    public List<StockLocalDto> StockPorLocal { get; set; } = new();
    public List<IntegranteDto> Equipo { get; set; } = new();
    public string Instancia { get; set; } = "desconocido";
    public Dictionary<string, string?> Origenes { get; set; } = new();
    public List<MapaLocalDto> MapaLocales { get; set; } = new();
    public Dictionary<string, ResumenTiendaDto> ResumenTiendas { get; set; } = new();
    public LocalDto? LocalEditar { get; set; }
    public ResultadoValorLocal? ResultadoValorLocalData { get; set; }

    [TempData] public string? FlashMensaje { get; set; }
    [TempData] public bool FlashOk { get; set; }

    private static ProductosPortTypeClient ObtenerCliente() => new();
    private static LocalesPortTypeClient ObtenerClienteLocales() => new();

    private void SetFlash(string mensaje, bool ok)
    {
        FlashMensaje = mensaje;
        FlashOk = ok;
    }

    private RedirectToPageResult IrA(string vista, object? routeValues = null)
    {
        if (!Vistas.Contains(vista)) vista = "listar";
        var dict = new Dictionary<string, object?> { ["view"] = vista };
        if (routeValues is not null)
        {
            foreach (var prop in routeValues.GetType().GetProperties())
            {
                dict[prop.Name] = prop.GetValue(routeValues);
            }
        }
        return RedirectToPage("Index", dict);
    }

    public async Task OnGetAsync(string? view, string? codigo, string? nombre, string? categoria,
        string? precio, string? cantidad, string? encontrado, string? valorTotal, string? editar, string? localId)
    {
        Vista = view is not null && Vistas.Contains(view) ? view : "listar";
        CodigoPrefill = codigo ?? "";

        try
        {
            var cliente = ObtenerCliente();
            var respuesta = await cliente.ListarProductosAsync();
            Productos = respuesta.productos?.ToList() ?? new();
        }
        catch (Exception error)
        {
            SetFlash($"No se pudo conectar al servicio SOAP en {WsdlUrl}: {error.Message}", false);
        }

        if (Vista == "consultar" && encontrado == "1")
        {
            ResultadoConsultaData = new ResultadoConsulta(codigo ?? "", nombre ?? "", categoria ?? "", precio ?? "0", cantidad ?? "0");
            StockPorLocal = await ObtenerStockPorLocalAsync(ResultadoConsultaData.Codigo);
        }

        if (Vista == "valor" && valorTotal is not null)
        {
            ResultadoValorData = new ResultadoValor(codigo ?? "", nombre ?? "", precio ?? "0", cantidad ?? "0", valorTotal);
        }

        if (Vista == "valor" && localId is not null)
        {
            ResultadoValorLocalData = await ObtenerValorLocalAsync(localId);
        }

        Equipo = await ObtenerEquipoAsync();
        Instancia = await ObtenerInstanciaAsync();
        Origenes = await ObtenerOrigenesAsync();
        Locales = await ObtenerLocalesAsync();

        if (Vista == "locales" && editar is not null)
        {
            LocalEditar = Locales.FirstOrDefault(l => l.id.ToString() == editar);
        }

        if (Vista is "listar" or "locales")
        {
            MapaLocales = await ObtenerMapaLocalesAsync();
            foreach (var local in MapaLocales)
            {
                foreach (var p in local.productos)
                {
                    ResumenTiendas[p.codigo] = ResumenTiendas.TryGetValue(p.codigo, out var actual)
                        ? new ResumenTiendaDto(actual.Tiendas + 1, actual.Total + p.cantidad)
                        : new ResumenTiendaDto(1, p.cantidad);
                }
            }
        }
    }

    // ---------------- Las 6 operaciones (SOAP) ----------------

    public async Task<IActionResult> OnPostRegistrarAsync(string codigo, string nombre, string categoria, decimal precio, int cantidad,
        int? tiendaId, int? cantidadTienda)
    {
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.RegistrarProductoAsync(new RegistrarProductoRequest(codigo, nombre, categoria, precio, cantidad));
            var mensaje = r.mensaje;
            var ok = r.estado;

            // Tienda inicial (opcional, mejora adicional): segundo paso por
            // SOAP (LocalesService) solo si el usuario eligio una tienda.
            if (r.estado && tiendaId is not null && cantidadTienda is not null)
            {
                var clienteLocales = ObtenerClienteLocales();
                var rTienda = await clienteLocales.AsignarStockLocalAsync(new AsignarStockLocalRequest(codigo, tiendaId.Value, cantidadTienda.Value));
                mensaje += $" · Tienda inicial: {rTienda.mensaje}";
                ok = ok && rTienda.estado;
            }
            SetFlash(mensaje, ok);
        }
        catch (Exception error)
        {
            SetFlash($"Error al registrar: {error.Message}", false);
        }
        return IrA("registrar");
    }

    public async Task<IActionResult> OnPostConsultarAsync(string codigo)
    {
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.ConsultarProductoAsync(new ConsultarProductoRequest(codigo));
            if (r.estado)
            {
                return IrA("consultar", new { codigo = r.codigo, nombre = r.nombre, categoria = r.categoria, precio = r.precio, cantidad = r.cantidad, encontrado = "1" });
            }
            SetFlash(r.mensaje, false);
        }
        catch (Exception error)
        {
            SetFlash($"Error al consultar: {error.Message}", false);
        }
        return IrA("consultar", new { codigo });
    }

    public async Task<IActionResult> OnPostActualizarStockAsync(string codigo, int cantidad, string? next)
    {
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.ActualizarStockAsync(new ActualizarStockRequest(codigo, cantidad));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al actualizar stock: {error.Message}", false);
        }
        return IrA(next ?? "stock");
    }

    public async Task<IActionResult> OnGetCalcularValorAsync(string codigo)
    {
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.CalcularValorInventarioAsync(new CalcularValorInventarioRequest(codigo));
            if (r.estado)
            {
                return IrA("valor", new { codigo, nombre = r.nombre, precio = r.precio, cantidad = r.cantidad, valorTotal = r.valorTotal });
            }
            SetFlash(r.mensaje, false);
        }
        catch (Exception error)
        {
            SetFlash($"Error al calcular valor: {error.Message}", false);
        }
        return IrA("valor", new { codigo });
    }

    public async Task<IActionResult> OnPostEliminarAsync(string codigo, string? next)
    {
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.EliminarProductoAsync(new EliminarProductoRequest(codigo));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al eliminar: {error.Message}", false);
        }
        return IrA(next ?? "eliminar");
    }

    // ---------------- Locales y stock por local (SOAP, mejora adicional) ----------------

    public async Task<IActionResult> OnPostCrearLocalAsync(string nombre, decimal lat, decimal lng)
    {
        try
        {
            var r = await ObtenerClienteLocales().CrearLocalAsync(new CrearLocalRequest(nombre, lat, lng));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al crear el local: {error.Message}", false);
        }
        return IrA("locales");
    }

    public async Task<IActionResult> OnPostAsignarStockLocalAsync(string codigo, int localId, int cantidad, string? next)
    {
        try
        {
            var r = await ObtenerClienteLocales().AsignarStockLocalAsync(new AsignarStockLocalRequest(codigo, localId, cantidad));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al asignar el stock: {error.Message}", false);
        }

        if (next is not null && next != "consultar")
        {
            return IrA(next, new { codigo });
        }

        // Volvemos a consultar por SOAP para que la ficha no quede incompleta.
        try
        {
            var cliente = ObtenerCliente();
            var r = await cliente.ConsultarProductoAsync(new ConsultarProductoRequest(codigo));
            if (r.estado)
            {
                return IrA("consultar", new { codigo = r.codigo, nombre = r.nombre, categoria = r.categoria, precio = r.precio, cantidad = r.cantidad, encontrado = "1" });
            }
        }
        catch (Exception)
        {
            // sigue con el fallback de abajo
        }
        return IrA("consultar", new { codigo });
    }

    public async Task<IActionResult> OnPostEditarLocalAsync(int id, string nombre, decimal lat, decimal lng)
    {
        try
        {
            var r = await ObtenerClienteLocales().ActualizarLocalAsync(new ActualizarLocalRequest(id, nombre, lat, lng));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al editar el local: {error.Message}", false);
        }
        return IrA("locales");
    }

    public async Task<IActionResult> OnPostEliminarLocalAsync(int id)
    {
        try
        {
            var r = await ObtenerClienteLocales().EliminarLocalAsync(new EliminarLocalRequest(id));
            SetFlash(r.mensaje, r.estado);
        }
        catch (Exception error)
        {
            SetFlash($"Error al eliminar el local: {error.Message}", false);
        }
        return IrA("locales");
    }

    // ---------------- Helpers REST (equipo, instancia, origen, locales) ----------------

    private async Task<List<IntegranteDto>> ObtenerEquipoAsync()
    {
        try
        {
            var datos = await Http.GetFromJsonAsync<List<IntegranteDto>>(ApiEquipoUrl, JsonOpts);
            return datos ?? new();
        }
        catch
        {
            return new();
        }
    }

    private async Task<string> ObtenerInstanciaAsync()
    {
        try
        {
            var json = await Http.GetFromJsonAsync<JsonElement>(ApiInstanciaUrl);
            return json.TryGetProperty("nombre", out var n) ? n.GetString() ?? "desconocido" : "desconocido";
        }
        catch
        {
            return "desconocido";
        }
    }

    private async Task<Dictionary<string, string?>> ObtenerOrigenesAsync()
    {
        try
        {
            var json = await Http.GetFromJsonAsync<JsonElement>(ApiProductosUrl);
            var mapa = new Dictionary<string, string?>();
            if (json.TryGetProperty("productos", out var lista))
            {
                foreach (var p in lista.EnumerateArray())
                {
                    var codigo = p.GetProperty("codigo").GetString() ?? "";
                    var origen = p.TryGetProperty("origen", out var o) && o.ValueKind != JsonValueKind.Null ? o.GetString() : null;
                    mapa[codigo] = origen;
                }
            }
            return mapa;
        }
        catch
        {
            return new();
        }
    }

    private async Task<List<LocalDto>> ObtenerLocalesAsync()
    {
        try
        {
            var r = await ObtenerClienteLocales().ListarLocalesAsync();
            return (r.locales ?? Array.Empty<Local>())
                .Select(l => new LocalDto(l.id, l.nombre, (double)l.lat, (double)l.lng))
                .ToList();
        }
        catch
        {
            return new();
        }
    }

    private async Task<List<StockLocalDto>> ObtenerStockPorLocalAsync(string codigo)
    {
        try
        {
            var r = await ObtenerClienteLocales().ConsultarStockPorLocalAsync(new ConsultarStockPorLocalRequest(codigo));
            if (!r.estado) return new();
            return (r.stockPorLocal ?? Array.Empty<StockLocalItem>())
                .Select(f => new StockLocalDto(f.localId, f.nombre, (double)f.lat, (double)f.lng, f.cantidad))
                .ToList();
        }
        catch
        {
            return new();
        }
    }

    private async Task<List<MapaLocalDto>> ObtenerMapaLocalesAsync()
    {
        // Todas las tiendas con sus productos y valor de inventario, en una
        // sola llamada SOAP. Alimenta el mapa central de "Locales y stock" y
        // el mini-mapa de "Listar productos".
        try
        {
            var r = await ObtenerClienteLocales().ObtenerMapaLocalesAsync();
            return (r.locales ?? Array.Empty<LocalConProductos>())
                .Select(l => new MapaLocalDto(
                    l.id, l.nombre, (double)l.lat, (double)l.lng,
                    (l.productos ?? Array.Empty<ProductoLocal>())
                        .Select(p => new ProductoLocalDto(p.codigo, p.nombre, p.precio, p.cantidad))
                        .ToList(),
                    l.valorTotal
                ))
                .ToList();
        }
        catch
        {
            return new();
        }
    }

    private async Task<ResultadoValorLocal?> ObtenerValorLocalAsync(string localId)
    {
        // Valor de inventario de una tienda especifica (precio x stock_local),
        // por SOAP. Extiende CalcularValorInventario (global) sin tocarla.
        try
        {
            if (!int.TryParse(localId, out var id)) return null;
            var r = await ObtenerClienteLocales().CalcularValorPorLocalAsync(new CalcularValorPorLocalRequest(id));
            if (!r.estado) return new ResultadoValorLocal(false, "", new(), 0);
            var productos = (r.productos ?? Array.Empty<ProductoLocal>())
                .Select(p => new ProductoLocalDto(p.codigo, p.nombre, p.precio, p.cantidad))
                .ToList();
            return new ResultadoValorLocal(true, r.localNombre ?? "", productos, r.valorTotal);
        }
        catch
        {
            return null;
        }
    }
}
