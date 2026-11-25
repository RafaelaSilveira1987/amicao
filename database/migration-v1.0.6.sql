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
