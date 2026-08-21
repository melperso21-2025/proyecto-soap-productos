// Capa de persistencia opcional (mejora adicional de la tarea).
// Si no hay credenciales de Supabase en el .env, el servidor sigue funcionando
// 100% en memoria: esto NUNCA debe bloquear el requisito obligatorio de la tarea.

require("dotenv").config();
const { createClient } = require("@supabase/supabase-js");

const { SUPABASE_URL, SUPABASE_KEY } = process.env;
const habilitado = Boolean(SUPABASE_URL && SUPABASE_KEY);

const supabase = habilitado ? createClient(SUPABASE_URL, SUPABASE_KEY) : null;

async function cargarProductos() {
  if (!habilitado) return [];
  const { data, error } = await supabase
    .from("productos")
    .select("codigo, nombre, categoria, precio, cantidad, origen");
  if (error) {
    console.warn("⚠ No se pudo cargar productos desde Supabase:", error.message);
    return [];
  }
  return data;
}

async function insertarProducto(producto) {
  if (!habilitado) return;
  const { error } = await supabase.from("productos").insert(producto);
  if (error) {
    console.warn("⚠ No se pudo persistir el nuevo producto en Supabase:", error.message);
  }
}

async function actualizarStock(codigo, cantidad) {
  if (!habilitado) return;
  const { error } = await supabase
    .from("productos")
    .update({ cantidad })
    .eq("codigo", codigo);
  if (error) {
    console.warn("⚠ No se pudo actualizar el stock en Supabase:", error.message);
  }
}

async function eliminarProducto(codigo) {
  if (!habilitado) return;
  const { error } = await supabase.from("productos").delete().eq("codigo", codigo);
  if (error) {
    console.warn("⚠ No se pudo eliminar el producto en Supabase:", error.message);
  }
}

// Respaldo si Supabase no esta configurado, para que /api/equipo nunca
// dependa de un servicio externo (mismo criterio que el resto del servidor).
const INTEGRANTES_RESPALDO = [
  { nombre: "Ismael", rol: "Servidor SOAP, API REST y front PHP" },
  { nombre: "Alexandra", rol: "Validaciones y pruebas SoapUI" },
  { nombre: "Israel", rol: "Cliente y front Python" },
];

async function listarIntegrantes() {
  if (!habilitado) return INTEGRANTES_RESPALDO;
  const { data, error } = await supabase
    .from("integrantes")
    .select("nombre, rol")
    .order("orden", { ascending: true });
  if (error) {
    console.warn("⚠ No se pudo cargar el equipo desde Supabase:", error.message);
    return INTEGRANTES_RESPALDO;
  }
  return data;
}

// ---------------------------------------------------------------
// Locales (sucursales) y stock por local -- mejora adicional, solo
// disponible via REST. Si Supabase no esta configurado, estas funciones
// devuelven listas vacias / un error explicito en vez de romper nada:
// esta funcionalidad SI depende de la base de datos (un local con su
// ubicacion geografica no tiene sentido como dato en memoria), pero el
// resto del servidor (las 6 operaciones SOAP) sigue intacto.

async function listarLocales() {
  if (!habilitado) return [];
  const { data, error } = await supabase
    .from("locales")
    .select("id, nombre, lat, lng")
    .order("nombre", { ascending: true });
  if (error) {
    console.warn("⚠ No se pudo cargar los locales desde Supabase:", error.message);
    return [];
  }
  return data;
}

async function crearLocal({ nombre, lat, lng }) {
  if (!habilitado) {
    return { estado: false, mensaje: "Supabase no esta configurado; los locales requieren base de datos." };
  }
  const { data, error } = await supabase
    .from("locales")
    .insert({ nombre, lat, lng })
    .select("id, nombre, lat, lng")
    .single();
  if (error) {
    return { estado: false, mensaje: `No se pudo crear el local: ${error.message}` };
  }
  return { estado: true, mensaje: `Local "${nombre}" creado correctamente`, local: data };
}

async function actualizarLocal(id, { nombre, lat, lng }) {
  if (!habilitado) {
    return { estado: false, mensaje: "Supabase no esta configurado; los locales requieren base de datos." };
  }
  const { data, error } = await supabase
    .from("locales")
    .update({ nombre, lat, lng })
    .eq("id", id)
    .select("id, nombre, lat, lng")
    .single();
  if (error) {
    return { estado: false, mensaje: `No se pudo actualizar el local: ${error.message}` };
  }
  return { estado: true, mensaje: `Local "${data.nombre}" actualizado correctamente`, local: data };
}

async function eliminarLocal(id) {
  if (!habilitado) {
    return { estado: false, mensaje: "Supabase no esta configurado; los locales requieren base de datos." };
  }
  const { error } = await supabase.from("locales").delete().eq("id", id);
  if (error) {
    return { estado: false, mensaje: `No se pudo eliminar el local: ${error.message}` };
  }
  return { estado: true, mensaje: "Local eliminado correctamente" };
}

// Todas las filas de stock_local sin filtrar -- usado para construir el
// mapa completo (todas las tiendas con sus productos) en una sola consulta,
// en vez de una consulta por tienda (evita N+1 desde los 3 fronts).
async function listarTodoStockLocal() {
  if (!habilitado) return [];
  const { data, error } = await supabase
    .from("stock_local")
    .select("local_id, codigo_producto, cantidad");
  if (error) {
    console.warn("⚠ No se pudo cargar el stock por local desde Supabase:", error.message);
    return [];
  }
  return data;
}

async function obtenerStockPorLocal(codigoProducto) {
  if (!habilitado) return [];
  const { data, error } = await supabase
    .from("stock_local")
    .select("cantidad, local_id, locales(id, nombre, lat, lng)")
    .eq("codigo_producto", codigoProducto);
  if (error) {
    console.warn("⚠ No se pudo cargar el stock por local desde Supabase:", error.message);
    return [];
  }
  return data
    .filter((fila) => fila.locales)
    .map((fila) => ({
      localId: fila.locales.id,
      nombre: fila.locales.nombre,
      lat: fila.locales.lat,
      lng: fila.locales.lng,
      cantidad: fila.cantidad,
    }));
}

async function actualizarStockLocal(codigoProducto, localId, cantidad) {
  if (!habilitado) {
    return { estado: false, mensaje: "Supabase no esta configurado; los locales requieren base de datos." };
  }
  const { error } = await supabase
    .from("stock_local")
    .upsert(
      { codigo_producto: codigoProducto, local_id: localId, cantidad },
      { onConflict: "codigo_producto,local_id" }
    );
  if (error) {
    return { estado: false, mensaje: `No se pudo actualizar el stock del local: ${error.message}` };
  }
  return { estado: true, mensaje: "Stock por local actualizado correctamente" };
}

module.exports = {
  habilitado,
  cargarProductos,
  insertarProducto,
  actualizarStock,
  eliminarProducto,
  listarIntegrantes,
  listarLocales,
  crearLocal,
  actualizarLocal,
  eliminarLocal,
  listarTodoStockLocal,
  obtenerStockPorLocal,
  actualizarStockLocal,
};
