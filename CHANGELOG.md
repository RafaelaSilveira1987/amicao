## v1.0.6.3 — busca geral
- Busca e filtros exclusivos da Visão geral, na seção Busca de Cadastro, abrangendo adoção e restrição.
- Listas exibem seus registros sem busca ou filtros, mantendo paginação e ações. Parâmetros de busca são ignorados nas listas.
- Sem migration ou alteração de configuração. Homologar as três páginas e a busca no WAMP antes do deploy.

## v1.0.4.4
- Metadados Open Graph e Twitter Card no HTML público do login e favicon.
- Capa institucional JPEG 1200 × 630 usando logo original; URL pública de produção.
- Sem alterações no Dockerfile, banco ou regras de negócio.

## v1.0.4.3
- Rodapé institucional com crédito Desenvolvido por RS Digital Lab e link para o site, no login e nas páginas autenticadas.
- CSS responsivo e cache busting. Mantém Dockerfile e SQL da v1.0.4.2.

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

## v1.0.4.1
- Corrige deploy Apache: habilita mod_rewrite, configura DocumentRoot literal em public, bloqueia raiz privada, desativa leitura de .htaccess no container e define ServerName. Sem mudanças no PHP, JS ou SQL.

## v1.0.4.2
- Alias Apache para /amicao/ e redirecionamento 308 para caminho com barra final.
- Cookie de sessão exclusivo AMICAOSESSID.
- Sem alteração de regras de negócio ou banco.

## v1.0.5
- Dashboard: 3 chamadas previstas em sessão válida, consulta de miniatura embutida, RPC de totais com RLS e índices.
- cURL reutilizado, bloqueio de sessão liberado em GET autenticado, Server-Timing, logo compactada, cache de assets e OPcache no Docker, mensagens de login.
- Migration obrigatória para banco existente. Homologação real pendente.

## v1.0.6
- Histórico com data local e nomes preservados.
- Novo cadastro apenas na visão geral.
- Criação administrativa de contas via Supabase Auth.

## v1.0.6.1
- Edição administrativa de nome, telefone, e-mail e senha opcional; sincronização Auth/perfil por migration.

## v1.0.6.2
- Nome ONG Amicão Muriaé na marca e metadados; título de login Central de adoção.
- Mostrar/ocultar a senha digitada no login, criação e edição de usuários.
- Lista de usuários compacta, responsiva, com identidade, contatos, perfil, status e ações alinhadas.
- Cache busting dos assets. Sem migration nova ou alterações de autenticação.

## v1.0.6.4
- Visão geral sem seção Busca de Cadastro e sem consulta de registros/miniaturas; indicadores e novo cadastro preservados.
- Busca por trecho do nome do tutor, sem distinção de maiúsculas/minúsculas, em cada lista, com paginação e limpar busca.
- Sem filtros por animal, raça ou cor. Sem migration.


## v1.0.6.5
- Removido o subtítulo Gestão de adoções do menu lateral.
- Visão geral compactada em desktop, com rolagem natural em telas menores e conteúdo excedente.
- Cache CSS atualizado. Sem migration.
