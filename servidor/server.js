require("dotenv").config();
const fs = require("fs");
const path = require("path");
const http = require("http");
const express = require("express");
const soap = require("soap");

const productos = require("./data/productos");
const supabaseClient = require("./db/supabaseClient");
const { validarRegistro, validarCodigo, validarNuevaCantidad } = require("./validaciones");

const PORT = process.env.PORT || 8000;
const WSDL_PATH = path.join(__dirname, "productos.wsdl");

function log(operacion, detalle) {
  const hora = new Date().toLocaleTimeString();
  console.log(`[${hora}] ${operacion} -> ${detalle}`);
}

const service = {
  ProductosService: {
    ProductosPort: {
      RegistrarProducto: (args, callback) => {
        (async () => {
          const validacion = validarRegistro(args);
          if (!validacion.valido) {
            log("RegistrarProducto", `RECHAZADO (${validacion.mensaje})`);
            return callback({ estado: false, mensaje: validacion.mensaje });
          }

          if (productos.existe(args.codigo)) {
            const mensaje = `El producto con el código ${args.codigo} ya existe`;
            log("RegistrarProducto", `RECHAZADO (${mensaje})`);
            return callback({ estado: false, mensaje });
          }

          const nuevoProducto = {
            codigo: String(args.codigo).trim(),
            nombre: String(args.nombre).trim(),
            categoria: String(args.categoria).trim(),
            precio: Number(args.precio),
            cantidad: Number(args.cantidad),
          };

          productos.agregar(nuevoProducto);
          await supabaseClient.insertarProducto(nuevoProducto);

          const mensaje = `Producto ${nuevoProducto.codigo} registrado correctamente`;
          log("RegistrarProducto", mensaje);
          callback({ estado: true, mensaje });
        })();
      },

      ConsultarProducto: (args, callback) => {
        const validacionCodigo = validarCodigo(args.codigo);
        if (!validacionCodigo.valido) {
          log("ConsultarProducto", `RECHAZADO (${validacionCodigo.mensaje})`);
          return callback({ estado: false, mensaje: validacionCodigo.mensaje });
        }

        const producto = productos.buscarPorCodigo(args.codigo);
        if (!producto) {
          const mensaje = `El producto con el código ${args.codigo} no existe`;
          log("ConsultarProducto", mensaje);
          return callback({ estado: false, mensaje });
        }

        log("ConsultarProducto", `Encontrado ${producto.codigo}`);
        callback({
          estado: true,
          mensaje: "Producto encontrado",
          codigo: producto.codigo,
          nombre: producto.nombre,
          categoria: producto.categoria,
          precio: producto.precio,
          cantidad: producto.cantidad,
        });
      },

      ListarProductos: (_args, callback) => {
        const lista = productos.listar();
        log("ListarProductos", `${lista.length} producto(s)`);
        callback({ productos: lista });
      },

      ActualizarStock: (args, callback) => {
        (async () => {
          const validacionCodigo = validarCodigo(args.codigo);
          if (!validacionCodigo.valido) {
            log("ActualizarStock", `RECHAZADO (${validacionCodigo.mensaje})`);
            return callback({ estado: false, mensaje: validacionCodigo.mensaje });
          }

          const validacionCantidad = validarNuevaCantidad(args.cantidad);
          if (!validacionCantidad.valido) {
            log("ActualizarStock", `RECHAZADO (${validacionCantidad.mensaje})`);
            return callback({ estado: false, mensaje: validacionCantidad.mensaje });
          }

          if (!productos.existe(args.codigo)) {
            const mensaje = `El producto con el código ${args.codigo} no existe`;
            log("ActualizarStock", mensaje);
            return callback({ estado: false, mensaje });
          }

          const nuevaCantidad = Number(args.cantidad);
          productos.actualizarCantidad(args.codigo, nuevaCantidad);
          await supabaseClient.actualizarStock(args.codigo, nuevaCantidad);

          const mensaje = `Stock del producto ${args.codigo} actualizado correctamente`;
          log("ActualizarStock", mensaje);
          callback({ estado: true, mensaje, cantidadActualizada: nuevaCantidad });
        })();
      },

      CalcularValorInventario: (args, callback) => {
        const validacionCodigo = validarCodigo(args.codigo);
        if (!validacionCodigo.valido) {
          log("CalcularValorInventario", `RECHAZADO (${validacionCodigo.mensaje})`);
          return callback({ estado: false, mensaje: validacionCodigo.mensaje });
        }

        const producto = productos.buscarPorCodigo(args.codigo);
        if (!producto) {
          const mensaje = `El producto con el código ${args.codigo} no existe`;
          log("CalcularValorInventario", mensaje);
          return callback({ estado: false, mensaje });
        }

        const valorTotal = producto.precio * producto.cantidad;
        log("CalcularValorInventario", `${producto.codigo} = ${valorTotal}`);
        callback({
          estado: true,
          mensaje: "Cálculo realizado correctamente",
          nombre: producto.nombre,
          precio: producto.precio,
          cantidad: producto.cantidad,
          valorTotal,
        });
      },

      EliminarProducto: (args, callback) => {
        (async () => {
          const validacionCodigo = validarCodigo(args.codigo);
          if (!validacionCodigo.valido) {
            log("EliminarProducto", `RECHAZADO (${validacionCodigo.mensaje})`);
            return callback({ estado: false, mensaje: validacionCodigo.mensaje });
          }

          if (!productos.existe(args.codigo)) {
            const mensaje = `El producto con el código ${args.codigo} no existe`;
            log("EliminarProducto", mensaje);
            return callback({ estado: false, mensaje });
          }

          productos.eliminar(args.codigo);
          await supabaseClient.eliminarProducto(args.codigo);

          const mensaje = `Producto ${args.codigo} eliminado correctamente`;
          log("EliminarProducto", mensaje);
          callback({ estado: true, mensaje });
        })();
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
  const server = http.createServer(app);
  const wsdlXml = fs.readFileSync(WSDL_PATH, "utf8");

  soap.listen(server, "/productos", service, wsdlXml);

  server.listen(PORT, () => {
    console.log(`Servidor SOAP escuchando en http://localhost:${PORT}/productos`);
    console.log(`WSDL disponible en http://localhost:${PORT}/productos?wsdl`);
  });
}

iniciar();
