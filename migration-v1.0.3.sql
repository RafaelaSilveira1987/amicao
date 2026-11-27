-- Amicao v1.0.3: corrige referencia ambigua ao nome do objeto Storage.
begin;
alter policy amicao_storage_insert on storage.objects
with check (
  bucket_id = 'amicao-images' and public.is_active()
  and exists (select 1 from public.records r
    where r.id::text = split_part(storage.objects.name, '/', 1)
      and r.deleted_at is null)
);
alter policy amicao_storage_read on storage.objects
using (
  bucket_id = 'amicao-images' and public.is_active()
  and exists (select 1 from public.records r
    where r.id::text = split_part(storage.objects.name, '/', 1))
);
commit;
