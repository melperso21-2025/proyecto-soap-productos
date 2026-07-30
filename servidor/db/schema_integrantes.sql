-- Tabla de integrantes del equipo, expuesta via GET /api/equipo.
-- Ejecutar en: Supabase Dashboard > SQL Editor > New query > Run.
-- (Es un script separado de schema.sql para no tener que re-ejecutar todo
-- lo que ya corriste antes.)

create table if not exists integrantes (
  id      bigint generated always as identity primary key,
  nombre  text not null unique,
  rol     text not null,
  orden   int not null default 0
);

alter table integrantes enable row level security;

drop policy if exists "integrantes_lectura_publica" on integrantes;
create policy "integrantes_lectura_publica"
  on integrantes
  for select
  to anon, authenticated
  using (true);

-- Datos del equipo actual (Diego ya no participa en el proyecto).
insert into integrantes (nombre, rol, orden) values
  ('Ismael',    'Servidor SOAP, API REST y front PHP', 1),
  ('Alexandra', 'Validaciones y pruebas SoapUI',        2),
  ('Israel',    'Cliente y front Python',               3)
on conflict do nothing;
