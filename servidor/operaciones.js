// Logica de negocio de las 6 operaciones, compartida por el servicio SOAP
// (server.js) y la API REST (rest.js). Evita duplicar validaciones y
// mensajes de error entre los dos protocolos que exponen lo mismo.

const os = require("os");
const productos = require("./data/productos");
const supabaseClient = require("./db/supabaseClient");
const { validarRegistro, validarCodigo, validarNuevaCantidad } = require("./validaciones");

// Los 3 integrantes corren su propia instancia local del servidor, todas
// apuntando al mismo Supabase. Este nombre marca qué instancia registro
// cada producto (columna "origen") y se expone en GET /api/instancia.
const NOMBRE_INSTANCIA = process.env.NOMBRE_INSTANCIA || os.hostname();

function log(operacion, detalle) {
  const hora = new Date().toLocaleTimeString();
  console.log(`[${hora}] ${operacion} -> ${detalle}`);
}

async function registrarProducto(datos) {
  const validacion = validarRegistro(datos);
  if (!validacion.valido) {
    log("RegistrarProducto", `RECHAZADO (${validacion.mensaje})`);
    return { estado: false, mensaje: validacion.mensaje };
  }

  if (productos.existe(datos.codigo)) {
    const mensaje = `El producto con el código ${datos.codigo} ya existe`;
    log("RegistrarProducto", `RECHAZADO (${mensaje})`);
    return { estado: false, mensaje };
  }

  const nuevoProducto = {
    codigo: String(datos.codigo).trim(),
    nombre: String(datos.nombre).trim(),
    categoria: String(datos.categoria).trim(),
    precio: Number(datos.precio),
    cantidad: Number(datos.cantidad),
    origen: NOMBRE_INSTANCIA,
  };

  productos.agregar(nuevoProducto);
  await supabaseClient.insertarProducto(nuevoProducto);

  const mensaje = `Producto ${nuevoProducto.codigo} registrado correctamente`;
  log("RegistrarProducto", mensaje);
  return { estado: true, mensaje };
}

function consultarProducto(codigo) {
  const validacionCodigo = validarCodigo(codigo);
  if (!validacionCodigo.valido) {
    log("ConsultarProducto", `RECHAZADO (${validacionCodigo.mensaje})`);
    return { estado: false, mensaje: validacionCodigo.mensaje };
  }

  const producto = productos.buscarPorCodigo(codigo);
  if (!producto) {
    const mensaje = `El producto con el código ${codigo} no existe`;
    log("ConsultarProducto", mensaje);
    return { estado: false, mensaje };
  }

  log("ConsultarProducto", `Encontrado ${producto.codigo}`);
  return {
    estado: true,
    mensaje: "Producto encontrado",
    codigo: producto.codigo,
    nombre: producto.nombre,
    categoria: producto.categoria,
    precio: producto.precio,
    cantidad: producto.cantidad,
  };
}

function listarProductos() {
  const lista = productos.listar();
  log("ListarProductos", `${lista.length} producto(s)`);
  return { productos: lista };
}

async function actualizarStock(codigo, cantidad) {
  const validacionCodigo = validarCodigo(codigo);
  if (!validacionCodigo.valido) {
    log("ActualizarStock", `RECHAZADO (${validacionCodigo.mensaje})`);
    return { estado: false, mensaje: validacionCodigo.mensaje };
  }

  const validacionCantidad = validarNuevaCantidad(cantidad);
  if (!validacionCantidad.valido) {
    log("ActualizarStock", `RECHAZADO (${validacionCantidad.mensaje})`);
    return { estado: false, mensaje: validacionCantidad.mensaje };
  }

  if (!productos.existe(codigo)) {
    const mensaje = `El producto con el código ${codigo} no existe`;
    log("ActualizarStock", mensaje);
    return { estado: false, mensaje };
  }

  const nuevaCantidad = Number(cantidad);
  productos.actualizarCantidad(codigo, nuevaCantidad);
  await supabaseClient.actualizarStock(codigo, nuevaCantidad);

  const mensaje = `Stock del producto ${codigo} actualizado correctamente`;
  log("ActualizarStock", mensaje);
  return { estado: true, mensaje, cantidadActualizada: nuevaCantidad };
}

function calcularValorInventario(codigo) {
  const validacionCodigo = validarCodigo(codigo);
  if (!validacionCodigo.valido) {
    log("CalcularValorInventario", `RECHAZADO (${validacionCodigo.mensaje})`);
    return { estado: false, mensaje: validacionCodigo.mensaje };
  }

  const producto = productos.buscarPorCodigo(codigo);
  if (!producto) {
    const mensaje = `El producto con el código ${codigo} no existe`;
    log("CalcularValorInventario", mensaje);
    return { estado: false, mensaje };
  }

  const valorTotal = producto.precio * producto.cantidad;
  log("CalcularValorInventario", `${producto.codigo} = ${valorTotal}`);
  return {
    estado: true,
    mensaje: "Cálculo realizado correctamente",
    nombre: producto.nombre,
    precio: producto.precio,
    cantidad: producto.cantidad,
    valorTotal,
  };
}

async function eliminarProducto(codigo) {
  const validacionCodigo = validarCodigo(codigo);
  if (!validacionCodigo.valido) {
    log("EliminarProducto", `RECHAZADO (${validacionCodigo.mensaje})`);
    return { estado: false, mensaje: validacionCodigo.mensaje };
  }

  if (!productos.existe(codigo)) {
    const mensaje = `El producto con el código ${codigo} no existe`;
    log("EliminarProducto", mensaje);
    return { estado: false, mensaje };
  }

  productos.eliminar(codigo);
  await supabaseClient.eliminarProducto(codigo);

  const mensaje = `Producto ${codigo} eliminado correctamente`;
  log("EliminarProducto", mensaje);
  return { estado: true, mensaje };
}

module.exports = {
  registrarProducto,
  consultarProducto,
  listarProductos,
  actualizarStock,
  calcularValorInventario,
  eliminarProducto,
  NOMBRE_INSTANCIA,
};
