// API REST sobre el mismo servidor y puerto que el servicio SOAP.
// Reutiliza la misma logica de negocio (operaciones.js), asi que ambos
// protocolos validan y responden exactamente igual.

const express = require("express");
const operaciones = require("./operaciones");
const supabaseClient = require("./db/supabaseClient");

function montarApiRest(app) {
  app.use(express.json());

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
}

module.exports = { montarApiRest };
