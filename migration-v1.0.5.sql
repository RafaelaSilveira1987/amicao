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
