BEGIN;

ALTER TABLE public.records ADD COLUMN IF NOT EXISTS profession text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS workplace text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS age text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS size text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS neutered text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS vaccinated text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS found_origin text;
ALTER TABLE public.records ADD COLUMN IF NOT EXISTS current_with text;

GRANT INSERT(category,name,cpf,address,phone,social,profession,workplace,age,animal_type,breed,size,color,neutered,vaccinated,found_origin,current_with,reason),
      UPDATE(category,name,cpf,address,phone,social,profession,workplace,age,animal_type,breed,size,color,neutered,vaccinated,found_origin,current_with,reason,deleted_at)
ON public.records TO authenticated;

DROP POLICY IF EXISTS image_delete ON public.record_images;
CREATE POLICY image_delete
ON public.record_images
FOR DELETE TO authenticated
USING (
  public.is_active()
  AND EXISTS (
    SELECT 1 FROM public.records r
    WHERE r.id = record_id
      AND r.deleted_at IS NULL
  )
);

DROP POLICY IF EXISTS amicao_storage_delete ON storage.objects;
CREATE POLICY amicao_storage_delete
ON storage.objects
FOR DELETE TO authenticated
USING (
  bucket_id = 'amicao-images'
  AND public.is_active()
  AND EXISTS (
    SELECT 1 FROM public.records r
    WHERE r.id::text = split_part(storage.objects.name, '/', 1)
      AND r.deleted_at IS NULL
  )
);

COMMIT;
