-- Esquema de la tabla "productos" para el proyecto Supabase.
-- Ejecutar en: Supabase Dashboard > SQL Editor > New query > Run.

create table if not exists productos (
  codigo      text primary key,
  nombre      text not null,
  categoria   text not null,
  precio      numeric(10, 2) not null check (precio > 0),
  cantidad    integer not null check (cantidad >= 0),
  creado_en   timestamptz not null default now(),
  actualizado_en timestamptz not null default now()
);

-- Mantiene "actualizado_en" al día en cada UPDATE.
create or replace function actualizar_timestamp()
returns trigger as $$
begin
  new.actualizado_en = now();
  return new;
end;
$$ language plpgsql;

drop trigger if exists trg_productos_actualizado on productos;
create trigger trg_productos_actualizado
  before update on productos
  for each row
  execute function actualizar_timestamp();

-- Row Level Security: proyecto académico, no maneja datos sensibles de
-- usuarios reales. Se habilita RLS pero con una política abierta para que
-- el servidor (usando la anon key) pueda leer y escribir sin fricción.
alter table productos enable row level security;

drop policy if exists "productos_acceso_total" on productos;
create policy "productos_acceso_total"
  on productos
  for all
  to anon, authenticated
  using (true)
  with check (true);
