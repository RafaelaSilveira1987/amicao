begin;
create table public.profiles (id uuid primary key references auth.users(id) on delete cascade, name text, role text not null default 'user' check(role in ('admin','user')), active boolean not null default false);
create function public.is_active() returns boolean language sql stable security definer set search_path='' as $$ select exists(select 1 from public.profiles where id=auth.uid() and active) $$;
create function public.is_admin() returns boolean language sql stable security definer set search_path='' as $$ select exists(select 1 from public.profiles where id=auth.uid() and active and role='admin') $$;
revoke all on function public.is_active(), public.is_admin() from public; grant execute on function public.is_active(), public.is_admin() to authenticated;
create function public.new_profile() returns trigger language plpgsql security definer set search_path='' as $$ begin insert into public.profiles(id,name) values(new.id,new.raw_user_meta_data->>'name'); return new; end $$;
create trigger amicao_new_user after insert on auth.users for each row execute function public.new_profile();
insert into public.profiles(id,name) select id,raw_user_meta_data->>'name' from auth.users on conflict do nothing;
create table public.records(id uuid primary key default gen_random_uuid(), category text not null default 'adocao' check(category in ('adocao','restricao')), name text, cpf text, address text, phone text, social text, profession text, workplace text, age text, animal_type text, breed text, size text, color text, neutered text, vaccinated text, found_origin text, current_with text, reason text, created_at timestamptz not null default now(), updated_at timestamptz not null default now(), deleted_at timestamptz);
create table public.record_images(id uuid primary key default gen_random_uuid(), record_id uuid not null references public.records(id), path text not null unique, created_at timestamptz default now());
create table public.record_history(id bigint generated always as identity primary key, record_id uuid not null references public.records(id), actor uuid, action text not null, old_category text, new_category text, changed_fields text[], created_at timestamptz not null default now());
create function public.audit_record() returns trigger language plpgsql security definer set search_path='' as $$ declare fields text[]; begin if tg_op='INSERT' then insert into public.record_history(record_id,actor,action,new_category) values(new.id,auth.uid(),'inclusao',new.category); else select array_agg(k) into fields from jsonb_each(to_jsonb(new)) as j(k,v) where v is distinct from to_jsonb(old)->k and k<>'updated_at'; if fields is not null then insert into public.record_history(record_id,actor,action,old_category,new_category,changed_fields) values(new.id,auth.uid(),case when new.deleted_at is distinct from old.deleted_at then 'arquivamento' when new.category<>old.category then 'transferencia' else 'edicao' end,old.category,new.category,fields); end if; end if; return new; end $$;
create function public.guard_record() returns trigger language plpgsql set search_path='' as $$ begin if new.deleted_at is distinct from old.deleted_at and not public.is_admin() then raise exception 'Apenas administradores podem arquivar'; end if; new.id=old.id; new.created_at=old.created_at; new.updated_at=now(); return new; end $$;
create trigger guard_record before update on public.records for each row execute function public.guard_record();
create trigger audit_record after insert or update on public.records for each row execute function public.audit_record();
alter table public.profiles enable row level security; alter table public.records enable row level security; alter table public.record_images enable row level security; alter table public.record_history enable row level security;
revoke all on public.profiles,public.records,public.record_images,public.record_history from anon,authenticated;
grant select on public.profiles,public.records,public.record_images,public.record_history to authenticated;
grant update(name,role,active) on public.profiles to authenticated;
grant insert(category,name,cpf,address,phone,social,profession,workplace,age,animal_type,breed,size,color,neutered,vaccinated,found_origin,current_with,reason), update(category,name,cpf,address,phone,social,profession,workplace,age,animal_type,breed,size,color,neutered,vaccinated,found_origin,current_with,reason,deleted_at) on public.records to authenticated;
grant insert(record_id,path),delete on public.record_images to authenticated;
create policy profile_read on public.profiles for select to authenticated using(id=auth.uid() or public.is_admin());
create policy profile_admin on public.profiles for update to authenticated using(public.is_admin() and id<>auth.uid()) with check(public.is_admin() and id<>auth.uid());
create policy record_read on public.records for select to authenticated using(public.is_active() and (deleted_at is null or public.is_admin()));
create policy record_insert on public.records for insert to authenticated with check(public.is_active() and deleted_at is null);
create policy record_update on public.records for update to authenticated using(public.is_active() and deleted_at is null) with check(public.is_active() and (deleted_at is null or public.is_admin()));
create policy history_read on public.record_history for select to authenticated using(public.is_active() and exists(select 1 from public.records r where r.id=record_id));
create policy image_read on public.record_images for select to authenticated using(public.is_active() and exists(select 1 from public.records r where r.id=record_id));
create policy image_insert on public.record_images for insert to authenticated with check(public.is_active() and split_part(path,'/',1)=record_id::text and exists(select 1 from public.records r where r.id=record_id and r.deleted_at is null));
create policy image_delete on public.record_images for delete to authenticated using(public.is_active() and exists(select 1 from public.records r where r.id=record_id and r.deleted_at is null));
insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types) values('amicao-images','amicao-images',false,5242880,array['image/jpeg','image/png','image/webp']);
create policy amicao_storage_read on storage.objects for select to authenticated using(bucket_id='amicao-images' and public.is_active() and exists(select 1 from public.records r where r.id::text=split_part(storage.objects.name,'/',1)));
create policy amicao_storage_insert on storage.objects for insert to authenticated with check(bucket_id='amicao-images' and public.is_active() and exists(select 1 from public.records r where r.id::text=split_part(storage.objects.name,'/',1) and r.deleted_at is null));
create policy amicao_storage_delete on storage.objects for delete to authenticated using(bucket_id='amicao-images' and public.is_active() and exists(select 1 from public.records r where r.id::text=split_part(storage.objects.name,'/',1) and r.deleted_at is null));
commit;

BEGIN;
CREATE OR REPLACE FUNCTION public.amicao_dashboard_counts()
RETURNS jsonb LANGUAGE sql STABLE SECURITY INVOKER SET search_path = '' AS $$
 SELECT jsonb_build_object(
  'adocao', count(*) FILTER (WHERE category='adocao'),
  'restricao', count(*) FILTER (WHERE category='restricao'),
  'usuarios_ativos', CASE WHEN public.is_admin() THEN (SELECT count(*) FROM public.profiles WHERE active) ELSE NULL END
 ) FROM public.records WHERE deleted_at IS NULL;
$$;
REVOKE ALL ON FUNCTION public.amicao_dashboard_counts() FROM PUBLIC, anon;
GRANT EXECUTE ON FUNCTION public.amicao_dashboard_counts() TO authenticated;
CREATE INDEX IF NOT EXISTS amicao_records_active_updated_idx ON public.records (updated_at DESC,id DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS amicao_records_active_category_updated_idx ON public.records (category,updated_at DESC,id DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS amicao_record_images_latest_idx ON public.record_images (record_id,created_at DESC,id DESC);
COMMIT;
BEGIN;
ALTER TABLE public.profiles ADD COLUMN IF NOT EXISTS phone text, ADD COLUMN IF NOT EXISTS email text;
UPDATE public.profiles p SET email=u.email FROM auth.users u WHERE u.id=p.id AND p.email IS DISTINCT FROM u.email;
GRANT UPDATE(phone) ON public.profiles TO authenticated;
CREATE OR REPLACE FUNCTION public.new_profile() RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $$
BEGIN
 INSERT INTO public.profiles(id,name,email,phone) VALUES(new.id,new.raw_user_meta_data->>'name',new.email,new.raw_user_meta_data->>'phone');
 RETURN new;
END $$;
ALTER TABLE public.record_history ADD COLUMN IF NOT EXISTS record_name text, ADD COLUMN IF NOT EXISTS actor_name text, ADD COLUMN IF NOT EXISTS names_reconstructed boolean;
UPDATE public.record_history h SET record_name=(SELECT r.name FROM public.records r WHERE r.id=h.record_id), actor_name=(SELECT p.name FROM public.profiles p WHERE p.id=h.actor), names_reconstructed=true WHERE h.names_reconstructed IS NULL;
ALTER TABLE public.record_history ALTER COLUMN names_reconstructed SET DEFAULT false, ALTER COLUMN names_reconstructed SET NOT NULL;
CREATE OR REPLACE FUNCTION public.audit_record() RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $$
DECLARE fields text[]; responsible text;
BEGIN
 SELECT p.name INTO responsible FROM public.profiles p WHERE p.id=auth.uid();
 IF TG_OP='INSERT' THEN
 INSERT INTO public.record_history(record_id,actor,action,new_category,record_name,actor_name) VALUES(new.id,auth.uid(),'inclusao',new.category,new.name,responsible);
 ELSE
 SELECT array_agg(k) INTO fields FROM jsonb_each(to_jsonb(new)) AS j(k,v) WHERE v IS DISTINCT FROM to_jsonb(old)->k AND k<>'updated_at';
 IF fields IS NOT NULL THEN
 INSERT INTO public.record_history(record_id,actor,action,old_category,new_category,changed_fields,record_name,actor_name) VALUES(new.id,auth.uid(),CASE WHEN new.deleted_at IS DISTINCT FROM old.deleted_at THEN 'arquivamento' WHEN new.category<>old.category THEN 'transferencia' ELSE 'edicao' END,old.category,new.category,fields,new.name,responsible);
 END IF;
 END IF;
 RETURN new;
END $$;
COMMIT;
BEGIN;
CREATE OR REPLACE FUNCTION public.amicao_sync_auth_profile() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER SET search_path='' AS $$
BEGIN
 IF new.email IS DISTINCT FROM old.email THEN
  UPDATE public.profiles SET email=new.email WHERE id=new.id;
 END IF;
 IF (new.raw_app_meta_data->'amicao_profile') IS DISTINCT FROM (old.raw_app_meta_data->'amicao_profile') AND jsonb_typeof(new.raw_app_meta_data->'amicao_profile')='object' THEN
  UPDATE public.profiles SET name=new.raw_app_meta_data->'amicao_profile'->>'name', phone=new.raw_app_meta_data->'amicao_profile'->>'phone' WHERE id=new.id;
 END IF;
 RETURN new;
END $$;
REVOKE ALL ON FUNCTION public.amicao_sync_auth_profile() FROM PUBLIC, anon, authenticated;
DROP TRIGGER IF EXISTS amicao_sync_auth_profile ON auth.users;
CREATE TRIGGER amicao_sync_auth_profile AFTER UPDATE OF email,raw_app_meta_data ON auth.users FOR EACH ROW EXECUTE FUNCTION public.amicao_sync_auth_profile();
COMMIT;
