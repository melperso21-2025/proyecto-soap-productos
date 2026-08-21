// API REST sobre el mismo servidor y puerto que el servicio SOAP.
// Reutiliza la misma logica de negocio (operaciones.js), asi que ambos
// protocolos validan y responden exactamente igual.

const express = require("express");
const operaciones = require("./operaciones");
const operacionesLocales = require("./operacionesLocales");
const supabaseClient = require("./db/supabaseClient");

function montarApiRest(app) {
  app.use(express.json());

  // La raiz "/" no tiene contenido propio (este servidor solo expone SOAP
  // y REST) - se deja una pagina informativa para no ver "Cannot GET /".
  app.get("/", (_req, res) => {
    res.json({
      servicio: "Servidor SOAP + REST de productos",
      instancia: operaciones.NOMBRE_INSTANCIA,
      wsdl: "/productos?wsdl",
      soap: "/productos",
      rest: {
        listar: "GET /api/productos",
        consultar: "GET /api/productos/:codigo",
        registrar: "POST /api/productos",
        actualizarStock: "PATCH /api/productos/:codigo/stock",
        calcularValor: "GET /api/productos/:codigo/valor",
        eliminar: "DELETE /api/productos/:codigo",
        equipo: "GET /api/equipo",
        instancia: "GET /api/instancia",
        locales: "GET/POST /api/locales",
        localesMapa: "GET /api/locales/mapa",
        localesEditarEliminar: "PATCH/DELETE /api/locales/:id",
        valorPorLocal: "GET /api/locales/:id/valor",
        stockPorLocal: "GET/POST /api/productos/:codigo/stock-local",
      },
      fronts: {
        python: "http://127.0.0.1:5000",
        php: "http://localhost:5001",
        csharp: "http://localhost:5002",
      },
    });
  });

  app.get("/api/productos", (_req, res) => {
    res.json(operaciones.listarProductos());
  });

  app.get("/api/productos/:codigo", (req, res) => {
    res.json(operaciones.consultarProducto(req.params.codigo));
  });

  app.post("/api/productos", async (req, res) => {
    res.json(await operaciones.registrarProducto(req.body));
  });

  app.patch("/api/productos/:codigo/stock", async (req, res) => {
    res.json(await operaciones.actualizarStock(req.params.codigo, req.body.cantidad));
  });

  app.get("/api/productos/:codigo/valor", (req, res) => {
    res.json(operaciones.calcularValorInventario(req.params.codigo));
  });

  app.delete("/api/productos/:codigo", async (req, res) => {
    res.json(await operaciones.eliminarProducto(req.params.codigo));
  });

  app.get("/api/equipo", async (_req, res) => {
    const integrantes = await supabaseClient.listarIntegrantes();
    res.json(integrantes);
  });

  // Identifica QUE instancia local esta respondiendo (los 3 integrantes
  // corren su propio servidor apuntando al mismo Supabase compartido).
  app.get("/api/instancia", (_req, res) => {
    res.json({ nombre: operaciones.NOMBRE_INSTANCIA });
  });

  // ---- Locales (sucursales) y stock por local -- mejora adicional ----
  // Estas rutas son un envoltorio delgado sobre operacionesLocales.js, la
  // misma logica que usa el servicio SOAP en LocalesPortType (ver
  // server.js) -- asi REST y SOAP responden exactamente igual. Se dejan
  // disponibles por REST solo como comodidad para pruebas rapidas con
  // Postman/curl; el camino "oficial" de la mejora es SOAP.

  app.get("/api/locales", async (_req, res) => {
    res.json(await operacionesLocales.listarLocales());
  });

  app.post("/api/locales", async (req, res) => {
    res.json(await operacionesLocales.crearLocal(req.body));
  });

  app.get("/api/locales/mapa", async (_req, res) => {
    res.json(await operacionesLocales.obtenerMapaLocales());
  });

  app.get("/api/locales/:id/valor", async (req, res) => {
    res.json(await operacionesLocales.calcularValorPorLocal(req.params.id));
  });

  app.patch("/api/locales/:id", async (req, res) => {
    res.json(await operacionesLocales.actualizarLocal(req.params.id, req.body));
  });

  app.delete("/api/locales/:id", async (req, res) => {
    res.json(await operacionesLocales.eliminarLocal(req.params.id));
  });

  app.get("/api/productos/:codigo/stock-local", async (req, res) => {
    res.json(await operacionesLocales.consultarStockPorLocal(req.params.codigo));
  });

  app.post("/api/productos/:codigo/stock-local", async (req, res) => {
    res.json(await operacionesLocales.asignarStockLocal(req.params.codigo, req.body.localId, req.body.cantidad));
  });
}

module.exports = { montarApiRest };
