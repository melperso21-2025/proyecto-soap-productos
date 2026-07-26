// Reglas de validacion exigidas por el enunciado (seccion 11).

function esVacio(valor) {
  return valor === undefined || valor === null || String(valor).trim() === "";
}

function validarRegistro({ codigo, nombre, categoria, precio, cantidad }) {
  if (esVacio(codigo)) {
    return { valido: false, mensaje: "El código del producto no puede estar vacío" };
  }
  if (esVacio(nombre)) {
    return { valido: false, mensaje: "El nombre del producto no puede estar vacío" };
  }
  if (esVacio(categoria)) {
    return { valido: false, mensaje: "La categoría del producto no puede estar vacía" };
  }

  const precioNum = Number(precio);
  if (Number.isNaN(precioNum) || precioNum <= 0) {
    return { valido: false, mensaje: "El precio debe ser un número mayor que cero" };
  }

  const cantidadNum = Number(cantidad);
  if (!Number.isInteger(cantidadNum) || cantidadNum < 0) {
    return { valido: false, mensaje: "La cantidad debe ser un número entero igual o mayor que cero" };
  }

  return { valido: true };
}

function validarCodigo(codigo) {
  if (esVacio(codigo)) {
    return { valido: false, mensaje: "El código del producto no puede estar vacío" };
  }
  return { valido: true };
}

function validarNuevaCantidad(cantidad) {
  const cantidadNum = Number(cantidad);
  if (!Number.isInteger(cantidadNum) || cantidadNum < 0) {
    return { valido: false, mensaje: "La cantidad debe ser un número entero igual o mayor que cero" };
  }
  return { valido: true };
}

module.exports = { validarRegistro, validarCodigo, validarNuevaCantidad };
