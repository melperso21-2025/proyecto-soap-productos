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
    .select("codigo, nombre, categoria, precio, cantidad");
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

module.exports = {
  habilitado,
  cargarProductos,
  insertarProducto,
  actualizarStock,
  eliminarProducto,
  listarIntegrantes,
};
