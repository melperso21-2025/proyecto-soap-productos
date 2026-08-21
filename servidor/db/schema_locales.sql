-- Locales (sucursales) y stock de cada producto por local.
-- Mejora adicional, expuesta solo por la API REST (GET/POST /api/locales,
-- GET/POST /api/productos/:codigo/stock-local) -- no toca el WSDL/SOAP
-- calificado, que sigue exactamente igual a como estaba.
-- Ejecutar en: Supabase Dashboard > SQL Editor > New query > Run.

create table if not exists locales (
  id          bigint generated always as identity primary key,
  nombre      text not null unique,
  lat         double precision not null,
  lng         double precision not null,
  creado_en   timestamptz not null default now()
);

alter table locales enable row level security;

drop policy if exists "locales_acceso_total" on locales;
create policy "locales_acceso_total"
  on locales
  for all
  to anon, authenticated
  using (true)
  with check (true);

-- Cantidad de un producto en un local especifico. Es informacion aparte del
-- "cantidad" total que maneja ActualizarStock por SOAP -- no se sincronizan
-- automaticamente, para no tocar la logica ya calificada de las 6 operaciones.
create table if not exists stock_local (
  id                bigint generated always as identity primary key,
  codigo_producto   text not null references productos(codigo) on delete cascade,
  local_id          bigint not null references locales(id) on delete cascade,
  cantidad          integer not null check (cantidad >= 0),
  actualizado_en    timestamptz not null default now(),
  unique (codigo_producto, local_id)
);

alter table stock_local enable row level security;

drop policy if exists "stock_local_acceso_total" on stock_local;
create policy "stock_local_acceso_total"
  on stock_local
  for all
  to anon, authenticated
  using (true)
  with check (true);

-- Un par de locales de ejemplo para no arrancar con el mapa vacio.
insert into locales (nombre, lat, lng) values
  ('Local Quito Norte', -0.1807, -78.4678),
  ('Local Guayaquil Centro', -2.1710, -79.9224),
  ('Local Cuenca', -2.9001, -79.0059)
on conflict do nothing;
