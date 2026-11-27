-- Diagnóstico somente leitura: não modifica registros nem permissões.
select id, public, file_size_limit, allowed_mime_types from storage.buckets where id='amicao-images';
select schemaname, tablename, policyname, cmd, roles from pg_policies where (schemaname='storage' and tablename='objects' and policyname like 'amicao_storage_%') or (schemaname='public' and tablename='record_images') order by schemaname, policyname;
select count(*) as arquivos_storage from storage.objects where bucket_id='amicao-images';
select count(*) as imagens_vinculadas from public.record_images;
