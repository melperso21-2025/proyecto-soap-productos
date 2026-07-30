require("dotenv").config();
const fs = require("fs");
const path = require("path");
const http = require("http");
const express = require("express");
const soap = require("soap");

const productos = require("./data/productos");
const supabaseClient = require("./db/supabaseClient");
const operaciones = require("./operaciones");
const { montarApiRest } = require("./rest");

const PORT = process.env.PORT || 8000;
const WSDL_PATH = path.join(__dirname, "productos.wsdl");

// El servicio SOAP es un envoltorio delgado sobre operaciones.js: la misma
// logica y validaciones que usa la API REST (ver rest.js), asi los dos
// protocolos responden exactamente igual ante los mismos datos.
const service = {
  ProductosService: {
    ProductosPort: {
      RegistrarProducto: (args, callback) => {
        operaciones.registrarProducto(args).then(callback);
      },

      ConsultarProducto: (args, callback) => {
        callback(operaciones.consultarProducto(args.codigo));
      },

      ListarProductos: (_args, callback) => {
        // node-soap serializa cualquier propiedad presente en el objeto,
        // aunque no este declarada en el WSDL. "origen" es metadata solo
        // para la API REST (ver rest.js) - aqui se filtra explicitamente
        // para no romper el contrato SOAP con un elemento no declarado.
        const { productos: lista } = operaciones.listarProductos();
        const productosSoap = lista.map(({ codigo, nombre, categoria, precio, cantidad }) => ({
          codigo,
          nombre,
          categoria,
          precio,
          cantidad,
        }));
        callback({ productos: productosSoap });
      },

      ActualizarStock: (args, callback) => {
        operaciones.actualizarStock(args.codigo, args.cantidad).then(callback);
      },

      CalcularValorInventario: (args, callback) => {
        callback(operaciones.calcularValorInventario(args.codigo));
      },

      EliminarProducto: (args, callback) => {
        operaciones.eliminarProducto(args.codigo).then(callback);
      },
    },
  },
};

async function iniciar() {
  console.log("Hidratando memoria desde Supabase (si está configurado)...");
  const productosIniciales = await supabaseClient.cargarProductos();
  productos.cargarDesde(productosIniciales);
  console.log(
    supabaseClient.habilitado
      ? `Supabase conectado. ${productosIniciales.length} producto(s) cargado(s) en memoria.`
      : "Supabase no configurado: el servidor funciona solo en memoria."
  );

  const app = express();
  montarApiRest(app);

  const server = http.createServer(app);
  const wsdlXml = fs.readFileSync(WSDL_PATH, "utf8");

  soap.listen(server, "/productos", service, wsdlXml);

  server.listen(PORT, () => {
    console.log(`Servidor SOAP escuchando en http://localhost:${PORT}/productos`);
    console.log(`WSDL disponible en http://localhost:${PORT}/productos?wsdl`);
    console.log(`API REST disponible en http://localhost:${PORT}/api/productos`);
  });
}

iniciar();
