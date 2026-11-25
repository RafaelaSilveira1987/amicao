# v1.0.4
- Dashboard com totais exatos via PostgREST, perfil e navegação visual.
- Ações Visualizar e Editar separadas, miniaturas privadas e galeria.
- Filtros exatos por tipo de animal, raça e cor.
- Preserva upload, transferência e políticas corrigidas na v1.0.3. Sem migration nova.
- Validação de sintaxe local; homologação real pendente.

# v1.0.2
- Upload binário com Content-Type real da imagem, sem multipart.
- Classificação segura de erros do Storage e cache busting.
- Sem mudanças no banco ou políticas. Integração real pendente.

# v1.0.1
- Envio de imagens via multipart cURL, validação de erros do PHP, identificação da etapa de falha e leitura autenticada com Accept apropriado.
- Confirmações personalizadas para transferir e arquivar, preservando o botão de ação.
- Indicador de envio e tamanho de arquivo, cache busting.
- Sem alterações de schema ou RLS.
- Sintaxe PHP e JS validada; integração real pendente.

# v1.0.0
Base inicial: login, perfis, listas, cadastro opcional, edição, imagens privadas, transferência, arquivamento lógico, histórico e pesquisa. Identidade visual baseada na imagem enviada. SQL, Dockerfile e manual. Validação local de sintaxe PHP; integração real pendente.

## v1.0.3
- Corrige referência ambígua ao nome do objeto nas políticas de leitura e inclusão do Storage. Instalações existentes: executar database/migration-v1.0.3.sql.
