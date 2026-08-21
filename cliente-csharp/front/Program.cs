// Front web en ASP.NET Core (Razor Pages) para el cliente SOAP en C#.
// Responsable: Alexandra. Espejo funcional de los fronts de Python (Flask)
// y PHP (nativo): mismo menu lateral, mismas 6 operaciones + la mejora
// adicional de locales/mapa, cada front con su propio color.
//
// Reutiliza el mismo proxy generado por dotnet-svcutil que usa el cliente
// de consola (ver ..\ServiceReference\ProductosServiceReference.cs) -- no
// se regenero nada, es el mismo contrato leido del WSDL.
//
// Corre en su propio puerto para convivir con los otros dos fronts:
//   dotnet run --urls http://localhost:5002

var builder = WebApplication.CreateBuilder(args);
builder.Services.AddRazorPages();

var app = builder.Build();

if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
}

// Los inputs HTML type="number" siempre envian el punto como separador
// decimal (formato invariante). Sin esto, el model binding usa la cultura
// del sistema operativo (en Windows en espanol, "." se lee como separador
// de miles) y "-0.95" se parsea como -95, rompiendo los campos de
// lat/lng/precio. Se fuerza en-US para todo el pipeline de esta app.
var culturaInvariante = new System.Globalization.CultureInfo("en-US");
app.UseRequestLocalization(new Microsoft.AspNetCore.Builder.RequestLocalizationOptions
{
    DefaultRequestCulture = new Microsoft.AspNetCore.Localization.RequestCulture(culturaInvariante),
    SupportedCultures = new[] { culturaInvariante },
    SupportedUICultures = new[] { culturaInvariante },
});

app.UseStaticFiles();
app.UseRouting();
app.MapRazorPages();

app.Run();
