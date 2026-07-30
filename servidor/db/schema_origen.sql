-- Agrega una columna para saber, en la tabla compartida "productos", desde
-- qué instancia local (Ismael / Alexandra / Israel) se registró cada fila.
-- Ejecutar en: Supabase Dashboard > SQL Editor > New query > Run.

alter table productos add column if not exists origen text;
