// Almacen en memoria: es la fuente de verdad durante la ejecucion del servidor
// (cumple el requisito obligatorio de la tarea). Supabase, si esta configurado,
// solo se usa para persistir/hidratar este arreglo (ver db/supabaseClient.js).

let productos = [];

function listar() {
  return productos;
}

function buscarPorCodigo(codigo) {
  return productos.find((p) => p.codigo === codigo);
}

function existe(codigo) {
  return Boolean(buscarPorCodigo(codigo));
}

function agregar(producto) {
  productos.push(producto);
  return producto;
}

function actualizarCantidad(codigo, nuevaCantidad) {
  const producto = buscarPorCodigo(codigo);
  if (!producto) return null;
  producto.cantidad = nuevaCantidad;
  return producto;
}

function eliminar(codigo) {
  const indice = productos.findIndex((p) => p.codigo === codigo);
  if (indice === -1) return false;
  productos.splice(indice, 1);
  return true;
}

// Usado al arrancar el servidor para hidratar la memoria desde Supabase (si aplica).
function cargarDesde(listaInicial) {
  productos = Array.isArray(listaInicial) ? listaInicial : [];
}

module.exports = {
  listar,
  buscarPorCodigo,
  existe,
  agregar,
  actualizarCantidad,
  eliminar,
  cargarDesde,
};
