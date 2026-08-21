// Logica de negocio de las operaciones de "Locales" (mejora adicional,
// LocalesPortType en productos.wsdl). Compartida por el servicio SOAP
// (server.js) y la API REST (rest.js), igual que operaciones.js para las
// 6 operaciones calificadas -- asi ambos protocolos responden exactamente
// igual ante los mismos datos.
//
// A diferencia de las 6 operaciones obligatorias, esto SI depende de
// Supabase: un local con su ubicacion geografica no tiene sentido como
// dato en memoria. Si Supabase no esta configurado, cada funcion devuelve
// un estado=false explicito en vez de romper el resto del servidor.

const operaciones = require("./operaciones");
const supabaseClient = require("./db/supabaseClient");

function log(operacion, detalle) {
  const hora = new Date().toLocaleTimeString();
  console.log(`[${hora}] ${operacion} -> ${detalle}`);
}

function validarLatLng(lat, lng) {
  const latNum = Number(lat);
  const lngNum = Number(lng);
  if (Number.isNaN(latNum) || latNum < -90 || latNum > 90) {
    return { valido: false, mensaje: "La latitud debe ser un número entre -90 y 90" };
  }
  if (Number.isNaN(lngNum) || lngNum < -180 || lngNum > 180) {
    return { valido: false, mensaje: "La longitud debe ser un número entre -180 y 180" };
  }
  return { valido: true, latNum, lngNum };
}

async function crearLocal({ nombre, lat, lng }) {
  if (!nombre || String(nombre).trim() === "") {
    const mensaje = "El nombre del local no puede estar vacío";
    log("CrearLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  const validacion = validarLatLng(lat, lng);
  if (!validacion.valido) {
    log("CrearLocal", `RECHAZADO (${validacion.mensaje})`);
    return { estado: false, mensaje: validacion.mensaje };
  }
  const resultado = await supabaseClient.crearLocal({
    nombre: String(nombre).trim(),
    lat: validacion.latNum,
    lng: validacion.lngNum,
  });
  log("CrearLocal", resultado.mensaje);
  return resultado;
}

async function listarLocales() {
  const locales = await supabaseClient.listarLocales();
  log("ListarLocales", `${locales.length} local(es)`);
  return locales;
}

async function actualizarLocal(id, { nombre, lat, lng }) {
  const idNum = Number(id);
  if (!Number.isInteger(idNum)) {
    const mensaje = "El id del local debe ser un número entero";
    log("ActualizarLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  if (!nombre || String(nombre).trim() === "") {
    const mensaje = "El nombre del local no puede estar vacío";
    log("ActualizarLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  const validacion = validarLatLng(lat, lng);
  if (!validacion.valido) {
    log("ActualizarLocal", `RECHAZADO (${validacion.mensaje})`);
    return { estado: false, mensaje: validacion.mensaje };
  }
  const resultado = await supabaseClient.actualizarLocal(idNum, {
    nombre: String(nombre).trim(),
    lat: validacion.latNum,
    lng: validacion.lngNum,
  });
  log("ActualizarLocal", resultado.mensaje);
  return resultado;
}

async function eliminarLocal(id) {
  const idNum = Number(id);
  if (!Number.isInteger(idNum)) {
    const mensaje = "El id del local debe ser un número entero";
    log("EliminarLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  const resultado = await supabaseClient.eliminarLocal(idNum);
  log("EliminarLocal", resultado.mensaje);
  return resultado;
}

async function asignarStockLocal(codigo, localId, cantidad) {
  const producto = operaciones.consultarProducto(codigo);
  if (!producto.estado) {
    log("AsignarStockLocal", `RECHAZADO (${producto.mensaje})`);
    return { estado: false, mensaje: producto.mensaje };
  }
  const localIdNum = Number(localId);
  const cantidadNum = Number(cantidad);
  if (!Number.isInteger(localIdNum)) {
    const mensaje = "Debes indicar el local";
    log("AsignarStockLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  if (!Number.isInteger(cantidadNum) || cantidadNum < 0) {
    const mensaje = "La cantidad debe ser un número entero igual o mayor que cero";
    log("AsignarStockLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  const resultado = await supabaseClient.actualizarStockLocal(codigo, localIdNum, cantidadNum);
  log("AsignarStockLocal", `${codigo} @ local ${localIdNum} -> ${resultado.mensaje}`);
  return resultado;
}

async function consultarStockPorLocal(codigo) {
  const producto = operaciones.consultarProducto(codigo);
  if (!producto.estado) {
    log("ConsultarStockPorLocal", `RECHAZADO (${producto.mensaje})`);
    return { estado: false, mensaje: producto.mensaje, stockPorLocal: [] };
  }
  const stockPorLocal = await supabaseClient.obtenerStockPorLocal(codigo);
  log("ConsultarStockPorLocal", `${codigo} -> ${stockPorLocal.length} tienda(s)`);
  return { estado: true, mensaje: "Consulta realizada correctamente", stockPorLocal };
}

async function obtenerMapaLocales() {
  const [locales, stockRows] = await Promise.all([
    supabaseClient.listarLocales(),
    supabaseClient.listarTodoStockLocal(),
  ]);
  const { productos: catalogo } = operaciones.listarProductos();
  const catalogoPorCodigo = Object.fromEntries(catalogo.map((p) => [p.codigo, p]));
  const mapa = locales.map((local) => {
    const productosDelLocal = stockRows
      .filter((fila) => fila.local_id === local.id)
      .map((fila) => {
        const info = catalogoPorCodigo[fila.codigo_producto];
        return {
          codigo: fila.codigo_producto,
          nombre: info ? info.nombre : fila.codigo_producto,
          precio: info ? info.precio : 0,
          cantidad: fila.cantidad,
        };
      });
    const valorTotal = productosDelLocal.reduce((acc, p) => acc + p.precio * p.cantidad, 0);
    return { ...local, productos: productosDelLocal, valorTotal };
  });
  log("ObtenerMapaLocales", `${mapa.length} tienda(s)`);
  return mapa;
}

async function calcularValorPorLocal(localId) {
  const idNum = Number(localId);
  if (!Number.isInteger(idNum)) {
    const mensaje = "El id del local debe ser un número entero";
    log("CalcularValorPorLocal", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }
  const mapa = await obtenerMapaLocales();
  const local = mapa.find((l) => l.id === idNum);
  if (!local) {
    const mensaje = `No existe un local con id ${idNum}`;
    log("CalcularValorPorLocal", mensaje);
    return { estado: false, mensaje };
  }
  log("CalcularValorPorLocal", `${local.nombre} = ${local.valorTotal}`);
  return {
    estado: true,
    mensaje: "Cálculo realizado correctamente",
    localNombre: local.nombre,
    productos: local.productos,
    valorTotal: local.valorTotal,
  };
}

module.exports = {
  crearLocal,
  listarLocales,
  actualizarLocal,
  eliminarLocal,
  asignarStockLocal,
  consultarStockPorLocal,
  obtenerMapaLocales,
  calcularValorPorLocal,
};
