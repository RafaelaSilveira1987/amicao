<?php
require dirname(__DIR__) . '/app/bootstrap.php';
ob_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");

$fieldDefs = [
    'name' => ['label' => 'Nome'],
    'cpf' => ['label' => 'CPF', 'inputmode' => 'tel'],
    'address' => ['label' => 'Endereço', 'type' => 'textarea'],
    'phone' => ['label' => 'Telefone', 'inputmode' => 'tel'],
    'social' => ['label' => 'Rede social'],
    'profession' => ['label' => 'Profissão'],
    'workplace' => ['label' => 'Local de trabalho'],
    'age' => ['label' => 'Idade'],
    'animal_type' => ['label' => 'Tipo de animal'],
    'breed' => ['label' => 'Raça'],
    'size' => ['label' => 'Porte', 'options' => ['', 'Pequeno', 'Médio', 'Grande']],
    'color' => ['label' => 'Cor'],
    'neutered' => ['label' => 'Castrado?', 'options' => ['', 'Sim', 'Não']],
    'vaccinated' => ['label' => 'Vacinado?', 'options' => ['', 'Sim', 'Não']],
    'found_origin' => ['label' => 'De onde foi pego'],
    'current_with' => ['label' => 'Com quem está'],
    'reason' => ['label' => 'Observações da entrevista', 'type' => 'textarea'],
];
$fields = array_map(fn($v) => $v['label'], $fieldDefs);

$p = profile();
$page = $_GET['page'] ?? 'dashboard';
$error = '';
$admin = ($p['role'] ?? '') === 'admin';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
            throw new RuntimeException('Envio excede post_max_size do PHP. Configure 7M e reinicie o WAMP.');
        }
        checkcsrf();
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            session_regenerate_id(true);
            tokens(req('POST', '/auth/v1/token?grant_type=password', [
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
            ]));
            if (!profile()) {
                $_SESSION = [];
                throw new RuntimeException('Acesso ainda não liberado. Procure o administrador.');
            }
            redirect();
        }

        if (!$p) {
            throw new RuntimeException('Entre novamente.');
        }

        if ($action === 'logout') {
            try { req('POST', '/auth/v1/logout', null, $_SESSION['access']); } catch (Throwable $e) {}
            $_SESSION = [];
            session_destroy();
            redirect('login');
        }

        if ($action === 'save') {
            $data = [];
            foreach ($fieldDefs as $key => $meta) {
                $value = trim((string)($_POST[$key] ?? ''));
                if (strlen($value) > 10000) {
                    throw new RuntimeException('Campo muito longo.');
                }
                $data[$key] = $value === '' ? null : $value;
            }
            $id = $_POST['id'] ?? '';
            if ($id) {
                $id = uuid($id);
                $result = api('PATCH', 'records?id=eq.' . $id . '&deleted_at=is.null', $data);
            } else {
                $data['category'] = ($_POST['category'] ?? '') === 'restricao' ? 'restricao' : 'adocao';
                $result = api('POST', 'records', $data);
                $id = $result[0]['id'] ?? '';
            }
            if (!$result) {
                throw new RuntimeException('Cadastro não encontrado ou sem permissão.');
            }
            $_SESSION['flash'] = 'Cadastro salvo.';
            redirect('edit&id=' . $id);
        }

        if ($action === 'transfer' || $action === 'archive') {
            $id = uuid($_POST['id'] ?? '');
            $record = api('GET', 'records?id=eq.' . $id . '&deleted_at=is.null');
            if (!$record) {
                throw new RuntimeException('Cadastro não encontrado.');
            }
            $old = $record[0]['category'];
            if ($action === 'archive' && !$admin) {
                throw new RuntimeException('Acesso negado.');
            }
            $data = $action === 'archive'
                ? ['deleted_at' => gmdate('c')]
                : ['category' => $old === 'adocao' ? 'restricao' : 'adocao'];
            $result = api('PATCH', 'records?id=eq.' . $id . '&category=eq.' . $old . '&deleted_at=is.null', $data);
            if (!$result) {
                throw new RuntimeException('Registro alterado por outro usuário.');
            }
            $_SESSION['flash'] = $action === 'archive' ? 'Cadastro arquivado.' : 'Cadastro transferido.';
            redirect($old);
        }

        if ($action === 'upload') {
            $id = uuid($_POST['id'] ?? '');
            $existing = api('GET', 'record_images?record_id=eq.' . $id . '&select=id');
            if (count($existing) >= 2) {
                throw new RuntimeException('Cada cadastro pode ter no máximo 2 imagens. Remova uma imagem antes de enviar outra.');
            }
            $f = $_FILES['image'] ?? [];
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException(match ($f['error'] ?? UPLOAD_ERR_NO_FILE) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Arquivo acima do limite de upload do PHP. Configure upload_max_filesize=5M e post_max_size=7M.',
                    UPLOAD_ERR_PARTIAL => 'Envio incompleto. Tente novamente.',
                    UPLOAD_ERR_NO_FILE => 'Selecione uma imagem.',
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'O PHP não conseguiu gravar o arquivo temporário. Confira o WAMP.',
                    default => 'O PHP recusou o envio da imagem.',
                });
            }
            if (!is_uploaded_file($f['tmp_name']) || $f['size'] > 5242880 || $f['size'] === 0) {
                throw new RuntimeException('Selecione uma imagem válida de até 5 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
            if (!$ext || !getimagesize($f['tmp_name'])) {
                throw new RuntimeException('Formato de imagem inválido.');
            }
            $record = api('GET', 'records?id=eq.' . $id . '&deleted_at=is.null&select=id');
            if (!$record) {
                throw new RuntimeException('Cadastro indisponível para imagens.');
            }
            $path = $id . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
            $bytes = file_get_contents($f['tmp_name']);
            if ($bytes === false || strlen($bytes) !== (int)$f['size']) {
                throw new RuntimeException('Não foi possível ler a imagem temporária.');
            }
            req('POST', '/storage/v1/object/amicao-images/' . $path, $bytes, $_SESSION['access'], $mime, true);
            try {
                api('POST', 'record_images', ['record_id' => $id, 'path' => $path]);
            } catch (Throwable $e) {
                try { req('DELETE', '/storage/v1/object/amicao-images', ['prefixes' => [$path]], $_SESSION['access']); } catch (Throwable $ignored) {}
                throw $e;
            }
            $_SESSION['flash'] = 'Imagem adicionada.';
            redirect('edit&id=' . $id);
        }

        if ($action === 'delete_image') {
            $id = uuid($_POST['id'] ?? '');
            $imageId = uuid($_POST['image_id'] ?? '');
            $image = api('GET', 'record_images?id=eq.' . $imageId . '&record_id=eq.' . $id);
            if (!$image) {
                throw new RuntimeException('Imagem não encontrada.');
            }
            $path = $image[0]['path'];
            req('DELETE', '/storage/v1/object/amicao-images', ['prefixes' => [$path]], $_SESSION['access']);
            api('DELETE', 'record_images?id=eq.' . $imageId);
            $_SESSION['flash'] = 'Imagem removida.';
            redirect('edit&id=' . $id);
        }

        if ($action === 'create_user' && $admin) {
            create_team_user($_POST);
            $_SESSION['flash'] = 'Usuário criado e liberado.';
            redirect('users');
        }

        if ($action === 'edit_user' && $admin) {
            $reauth = edit_team_user($_POST, $p);
            if ($reauth) {
                $_SESSION = [];
                session_destroy();
                redirect('login');
            }
            $_SESSION['flash'] = 'Dados do usuário atualizados.';
            redirect('users');
        }

        if ($action === 'user' && $admin) {
            $id = uuid($_POST['id'] ?? '');
            $result = api('PATCH', 'profiles?id=eq.' . $id, [
                'role' => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user',
                'active' => isset($_POST['active']),
            ]);
            if (!$result) {
                throw new RuntimeException('Perfil não alterado.');
            }
            $_SESSION['flash'] = 'Permissões atualizadas.';
            redirect('users');
        }

        throw new RuntimeException('Ação não permitida.');
    }

    if ($p && $page === 'image') {
        $id = uuid($_GET['id'] ?? '');
        $image = api('GET', 'record_images?id=eq.' . $id);
        if (!$image) {
            throw new RuntimeException('Imagem indisponível.');
        }
        $path = $image[0]['path'];
        session_write_close();
        if (!preg_match('~^[a-f0-9-]{36}/[a-f0-9]{32}\.(jpg|png|webp)$~', $path)) {
            throw new RuntimeException('Imagem inválida.');
        }
        $bytes = req('GET', '/storage/v1/object/authenticated/amicao-images/' . $path, null, $_SESSION['access'], 'application/json', true);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Imagem inválida.');
        }
        header('Content-Type: ' . $mime);
        performance_headers();
        echo $bytes;
        exit;
    }

    if ($p && $page === 'export') {
        $category = ($_GET['category'] ?? '') === 'restricao' ? 'restricao' : 'adocao';
        $q = trim((string)($_GET['q'] ?? ''));
        $query = 'records?select=id,category,created_at,' . implode(',', array_keys($fieldDefs)) . '&deleted_at=is.null&category=eq.' . $category . '&order=name.asc';
        if ($q !== '') {
            $term = str_replace(['\\', '"', '*', '%', '_'], ['', '', '', '', ''], $q);
            $query .= $term !== '' ? '&name=' . rawurlencode('ilike.*' . $term . '*') : '&id=is.null';
        }
        $rows = api('GET', $query);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . ($category === 'adocao' ? 'lista-adocao' : 'lista-restricao') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, array_merge(['Lista'], array_values($fields), ['Criado em']), ';');
        foreach ($rows as $row) {
            fputcsv($out, array_merge([
                $row['category'] === 'adocao' ? 'Adoção responsável' : 'Restrição'
            ], array_map(fn($k) => (string)($row[$k] ?? ''), array_keys($fieldDefs)), [
                local_history_date($row['created_at'] ?? '')
            ]), ';');
        }
        fclose($out);
        performance_headers();
        exit;
    }
} catch (Throwable $e) {
    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível concluir a operação.';
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
if ($p && $_SERVER['REQUEST_METHOD'] === 'GET') {
    session_write_close();
}

function renderField(string $key, array $meta, array $record): string {
    $label = h($meta['label']);
    $value = h($record[$key] ?? '');
    $inputmode = isset($meta['inputmode']) ? ' inputmode="' . h($meta['inputmode']) . '"' : '';
    if (($meta['type'] ?? '') === 'textarea') {
        return '<label>' . $label . '<textarea name="' . h($key) . '" rows="3">' . $value . '</textarea></label>';
    }
    if (isset($meta['options'])) {
        $options = '<option value="">Selecione</option>';
        foreach ($meta['options'] as $option) {
            if ($option === '') continue;
            $selected = ($record[$key] ?? '') === $option ? ' selected' : '';
            $options .= '<option value="' . h($option) . '"' . $selected . '>' . h($option) . '</option>';
        }
        return '<label>' . $label . '<select name="' . h($key) . '">' . $options . '</select></label>';
    }
    return '<label>' . $label . '<input name="' . h($key) . '" value="' . $value . '"' . $inputmode . ' maxlength="1000"></label>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ONG Amicão Muriaé | Gestão de adoções</title>
<meta name="description" content="Central de gestão de adoções responsáveis e proteção animal.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="ONG Amicão Muriaé">
<meta property="og:title" content="ONG Amicão Muriaé | Gestão de adoções">
<meta property="og:description" content="Proteção, cuidado e responsabilidade.">
<meta property="og:url" content="https://rsdigitallab.com/amicao/">
<meta property="og:image" content="https://rsdigitallab.com/amicao/assets/og-cover.jpg">
<meta property="og:image:secure_url" content="https://rsdigitallab.com/amicao/assets/og-cover.jpg">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Logomarca ONG Amicão Muriaé e gestão de adoção responsável">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="ONG Amicão Muriaé | Gestão de adoções">
<meta name="twitter:description" content="Proteção, cuidado e responsabilidade.">
<meta name="twitter:image" content="https://rsdigitallab.com/amicao/assets/og-cover.jpg">
<link rel="icon" type="image/png" href="assets/logo.png?v=1070">
<link rel="stylesheet" href="assets/style.css?v=1070">
<script defer src="assets/app.js?v=1070"></script>
</head>
<body>
<?php if (!$p): ?>
<main class="login">
    <section class="panel">
        <img class="logo large" src="assets/logo.png?v=1070" alt="ONG Amicão Muriaé">
        <h1>Central de adoção</h1>
        <p>Acesso exclusivo à equipe autorizada.</p>
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <?= hidden() ?>
            <input type="hidden" name="action" value="login">
            <label>E-mail<input type="email" name="email" autocomplete="username" required></label>
            <div class="password-field">
                <label for="password-field-0">Senha</label>
                <div class="password-control">
                    <input id="password-field-0" type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" aria-controls="password-field-0" aria-label="Mostrar senha" aria-pressed="false" hidden>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="M3 3l18 18"/></svg>
                    </button>
                </div>
            </div>
            <button>Entrar</button>
        </form>
        <p class="muted">Para recuperar o acesso, procure o administrador.</p>
    </section>
    <footer class="site-footer">Desenvolvido por <a href="https://rsdigitallab.com/" target="_blank" rel="noopener noreferrer">RS Digital Lab</a></footer>
</main>
<?php else: ?>
<aside>
    <div class="brand">
        <img class="logo" src="assets/logo.png?v=1070" alt="ONG Amicão">
        <div><strong>ONG Amicão Muriaé</strong></div>
    </div>
    <nav>
        <?php foreach (['dashboard' => 'Visão geral', 'adocao' => 'Adoção responsável', 'restricao' => 'Lista de restrição', 'history' => 'Histórico'] + ($admin ? ['users' => 'Usuários'] : []) as $k => $label): ?>
            <a class="<?= ($page === $k || ($page === 'edit_user' && $k === 'users')) ? 'selected' : '' ?>" href="?page=<?= $k ?>"><span class="nav-icon" aria-hidden="true"><?= ['dashboard' => '⌂', 'adocao' => '♡', 'restricao' => '⚑', 'history' => '◷', 'users' => '♙'][$k] ?></span><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post"><?= hidden() ?><button class="secondary" name="action" value="logout">Sair</button></form>
</aside>
<main class="workspace<?= $page === 'dashboard' ? ' workspace-dashboard' : '' ?>">
    <header><span>Central de gestão</span><span><?= h($p['name'] ?: ($admin ? 'Administrador' : 'Usuário')) ?> <small class="role"><?= $admin ? 'Administrador' : 'Usuário' ?></small></span></header>
    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="success"><?= h($flash) ?></div><?php endif; ?>

    <?php try { ?>
        <?php if ($page === 'dashboard' || in_array($page, ['adocao', 'restricao'], true)): ?>
            <?php
            if ($page === 'dashboard') {
                $counts = dashboard_counts();
            } else {
                $category = $page;
                $q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
                $offset = max(0, (int)($_GET['offset'] ?? 0));
                $query = 'records?select=id,name,cpf,phone,social,animal_type,breed,color,size,category,updated_at,record_images(id)&record_images.order=created_at.desc,id.desc&record_images.limit=1&deleted_at=is.null&order=updated_at.desc,id.desc&limit=51&offset=' . $offset . '&category=eq.' . $category;
                if ($q !== '') {
                    $term = str_replace(['\\', '"', '*', '%', '_'], ['', '', '', '', ''], $q);
                    $query .= $term !== '' ? '&name=' . rawurlencode('ilike.*' . $term . '*') : '&id=is.null';
                }
                $rows = api('GET', $query);
                $more = count($rows) > 50;
                $rows = array_slice($rows, 0, 50);
                $thumbs = [];
                foreach ($rows as $row) {
                    if (!empty($row['record_images'][0]['id'])) {
                        $thumbs[$row['id']] = $row['record_images'][0]['id'];
                    }
                }
            }
            ?>

            <div class="heading">
                <div>
                    <h1><?= h(['dashboard' => 'Visão geral', 'adocao' => 'Adoção responsável', 'restricao' => 'Lista de restrição'][$page]) ?></h1>
                    <p>Proteção, cuidado e responsabilidade.</p>
                </div>
                <?php if ($page === 'dashboard'): ?>
                    <a class="button" href="?page=edit&category=adocao">+ Novo cadastro</a>
                <?php else: ?>
                    <div class="heading-actions">
                        <form class="search tutor-search" method="get" role="search">
                            <input type="hidden" name="page" value="<?= h($page) ?>">
                            <input type="search" name="q" aria-label="Nome do tutor" value="<?= h($q) ?>" placeholder="Palavra-chave do tutor">
                            <button type="submit">Buscar</button>
                            <?php if ($q !== ''): ?><a href="?page=<?= h($page) ?>">Limpar</a><?php endif; ?>
                        </form>
                        <a class="action-button" href="?page=export&category=<?= h($page) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>">Exportar Excel</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($page === 'dashboard'): ?>
                <section class="welcome"><div><small>ONG AMICÃO MURIAÉ</small><h2>Cuidar é transformar vidas.</h2><p>Organize os registros e acompanhe as adoções responsáveis.</p></div><img src="assets/logo.png?v=1070" alt="" class="logo large"></section>
                <div class="metrics">
                    <a class="metric adocao" href="?page=adocao"><span class="metric-icon">♡</span><span>Adoções responsáveis</span><strong><?= h($counts['adocao']) ?></strong><small>Consultar registros →</small></a>
                    <a class="metric restricao" href="?page=restricao"><span class="metric-icon">⚑</span><span>Lista de restrição</span><strong><?= h($counts['restricao']) ?></strong><small>Consultar registros →</small></a>
                    <div class="metric metric-static"><span class="metric-icon">▤</span><span>Total de cadastros</span><strong><?= h((int)$counts['adocao'] + (int)$counts['restricao']) ?></strong><small>Total geral</small></div>
                    <?php if ($admin): ?><a class="metric" href="?page=users"><span class="metric-icon">♙</span><span>Usuários ativos</span><strong><?= h($counts['usuarios_ativos'] ?? 0) ?></strong><small>Gerenciar usuários →</small></a><?php endif; ?>
                </div>
            <?php else: ?>
                <section class="panel">
                    <h2>Registros da lista</h2>
                    <div class="tablewrap">
                        <table>
                            <thead><tr><th>Imagem</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Rede social</th><th>Animal</th><th>Lista</th><th>Ações</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?php if (isset($thumbs[$r['id']])): ?><a href="?page=view&id=<?= h($r['id']) ?>"><img class="thumb" src="?page=image&id=<?= h($thumbs[$r['id']]) ?>" alt="Imagem do cadastro" loading="lazy"></a><?php else: ?><span class="thumb placeholder" aria-label="Sem imagem">♡</span><?php endif; ?></td>
                                    <td><?= h($r['name'] ?: 'Sem nome') ?></td>
                                    <td><?= h(cpfmask($r['cpf'])) ?></td>
                                    <td><?= h($r['phone'] ?: '—') ?></td>
                                    <td><?= h($r['social'] ?: '—') ?></td>
                                    <td><?= h(implode(' · ', array_filter([$r['animal_type'], $r['breed'], $r['size'], $r['color']])) ?: '—') ?></td>
                                    <td><span class="tag <?= h($r['category']) ?>"><?= $r['category'] === 'adocao' ? 'Adoção' : 'Restrição' ?></span></td>
                                    <td><div class="row-actions"><a class="action-button" href="?page=view&id=<?= h($r['id']) ?>">Visualizar</a><a class="action-button edit" href="?page=edit&id=<?= h($r['id']) ?>">Editar</a></div></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?><tr><td colspan="8">Nenhum registro encontrado.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <?php if ($offset): ?><a href="?<?= h(http_build_query(['page' => $page, 'offset' => max(0, $offset - 50), 'q' => $q])) ?>">← Anterior</a><?php endif; ?>
                        <?php if ($more): ?><a href="?<?= h(http_build_query(['page' => $page, 'offset' => $offset + 50, 'q' => $q])) ?>">Próxima →</a><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php elseif ($page === 'view'): ?>
            <?php
            $id = uuid($_GET['id'] ?? '');
            $r = api('GET', 'records?id=eq.' . $id . '&deleted_at=is.null')[0] ?? null;
            if (!$r) throw new RuntimeException('Cadastro indisponível.');
            ?>
            <div class="heading">
                <div><h1><?= h($r['name'] ?: 'Cadastro sem nome') ?></h1><p>Consulta de informações</p></div>
                <div class="row-actions"><a class="action-button" href="?page=<?= h($r['category']) ?>">← Voltar</a><a class="button" href="?page=edit&id=<?= h($id) ?>">Editar cadastro</a></div>
            </div>
            <section class="panel">
                <span class="tag <?= h($r['category']) ?>"><?= $r['category'] === 'adocao' ? 'Adoção responsável' : 'Restrição' ?></span>
                <dl class="details">
                    <?php foreach ($fields as $k => $label): ?>
                        <div><dt><?= h($label) ?></dt><dd><?= h($r[$k] ?? '—') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <section class="panel">
                <h2>Imagens</h2>
                <div class="photos">
                    <?php $images = api('GET', 'record_images?record_id=eq.' . $id . '&order=created_at.desc'); ?>
                    <?php foreach ($images as $im): ?>
                        <a class="gallery-link" href="?page=image&id=<?= h($im['id']) ?>" target="_blank" rel="noopener"><img src="?page=image&id=<?= h($im['id']) ?>" alt="Imagem do cadastro" loading="lazy"></a>
                    <?php endforeach; ?>
                    <?php if (!$images): ?><p>Nenhuma imagem cadastrada.</p><?php endif; ?>
                </div>
            </section>

        <?php elseif ($page === 'edit'): ?>
            <?php
            $id = isset($_GET['id']) ? uuid($_GET['id']) : '';
            $r = $id ? (api('GET', 'records?id=eq.' . $id . '&deleted_at=is.null')[0] ?? null) : [];
            if ($id && !$r) throw new RuntimeException('Cadastro indisponível.');
            $cat = $r['category'] ?? (($_GET['category'] ?? '') === 'restricao' ? 'restricao' : 'adocao');
            ?>
            <div class="heading"><div><h1><?= $id ? 'Editar cadastro' : 'Novo cadastro' ?></h1><p>Todos os campos informativos são opcionais.</p></div><a href="?page=<?= h($cat) ?>">← Voltar</a></div>
            <section class="panel">
                <form method="post">
                    <?= hidden() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= h($id) ?>">
                    <?php if (!$id): ?>
                        <label>Lista<select name="category"><option value="adocao" <?= $cat === 'adocao' ? 'selected' : '' ?>>Adoção responsável</option><option value="restricao" <?= $cat === 'restricao' ? 'selected' : '' ?>>Restrição</option></select></label>
                    <?php else: ?>
                        <p class="tag <?= h($cat) ?>"><?= $cat === 'adocao' ? 'Adoção responsável' : 'Restrição' ?></p>
                    <?php endif; ?>
                    <div class="formgrid">
                        <?php foreach ($fieldDefs as $key => $meta): ?>
                            <?= renderField($key, $meta, $r ?: []) ?>
                        <?php endforeach; ?>
                    </div>
                    <button>Salvar cadastro</button>
                </form>
            </section>
            <?php if ($id): ?>
                <?php $images = api('GET', 'record_images?record_id=eq.' . $id . '&order=created_at.desc'); ?>
                <section class="panel">
                    <div class="section-head"><h2>Imagens</h2><span class="muted"><?= count($images) ?>/2 imagem(ns)</span></div>
                    <div class="photos photo-cards">
                        <?php foreach ($images as $im): ?>
                            <div class="photo-card">
                                <a class="gallery-link" href="?page=image&id=<?= h($im['id']) ?>" target="_blank" rel="noopener"><img src="?page=image&id=<?= h($im['id']) ?>" alt="Imagem do cadastro" loading="lazy"></a>
                                <form method="post" class="photo-remove-form">
                                    <?= hidden() ?>
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="id" value="<?= h($id) ?>">
                                    <input type="hidden" name="image_id" value="<?= h($im['id']) ?>">
                                    <button type="submit" class="photo-remove" aria-label="Remover imagem">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$images): ?><p>Nenhuma imagem cadastrada.</p><?php endif; ?>
                    </div>
                    <?php if (count($images) < 2): ?>
                        <form method="post" enctype="multipart/form-data" class="upload-form">
                            <?= hidden() ?>
                            <input type="hidden" name="action" value="upload">
                            <input type="hidden" name="id" value="<?= h($id) ?>">
                            <label>Adicionar imagem (até 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required><small class="file-info" aria-live="polite">JPG, PNG ou WEBP · até 5 MB</small></label>
                            <button type="submit">Enviar imagem</button>
                        </form>
                    <?php else: ?>
                        <p class="muted">Limite de 2 imagens por cadastro atingido. Remova uma imagem para enviar outra.</p>
                    <?php endif; ?>
                </section>
                <section class="panel"><h2>Movimentação</h2><form method="post" class="actions" data-confirm="movimentacao"><?= hidden() ?><input type="hidden" name="id" value="<?= h($id) ?>"><button name="action" value="transfer">Transferir para <?= $cat === 'adocao' ? 'restrição' : 'adoção' ?></button><?php if ($admin): ?><button class="danger" name="action" value="archive">Arquivar cadastro</button><?php endif; ?></form></section>
            <?php endif; ?>

        <?php elseif ($page === 'history'): ?>
            <?php $rows = api('GET', 'record_history?order=created_at.desc&limit=100'); ?>
            <h1>Histórico</h1>
            <p>Últimas 100 movimentações visíveis. Horário de Brasília. Os nomes de eventos anteriores à atualização foram recuperados dos cadastros atuais, quando disponíveis.</p>
            <section class="panel tablewrap"><table><thead><tr><th>Data e hora (Brasília)</th><th>Cadastro</th><th>Ação</th><th>Origem / destino</th><th>Responsável</th></tr></thead><tbody><?php foreach ($rows as $r): ?><tr><td><?= h(local_history_date($r['created_at'])) ?></td><td><a href="?page=view&id=<?= h($r['record_id']) ?>"><?= h($r['record_name'] ?: 'Cadastro sem nome') ?></a></td><td><?= h(['inclusao' => 'Inclusão', 'edicao' => 'Edição', 'transferencia' => 'Transferência', 'arquivamento' => 'Arquivamento'][$r['action']] ?? $r['action']) ?></td><td><?= h(category_label($r['old_category']) . ' → ' . category_label($r['new_category'])) ?></td><td><?= h($r['actor_name'] ?: ($r['actor'] ? 'Usuário não identificado' : 'Sistema')) ?></td></tr><?php endforeach; ?></tbody></table></section>

        <?php elseif ($page === 'edit_user' && $admin): ?>
            <?php $id = uuid($_GET['id'] ?? ''); $u = api('GET', 'profiles?id=eq.' . $id)[0] ?? null; if (!$u) throw new RuntimeException('Usuário não encontrado.'); ?>
            <div class="heading"><div><h1>Editar usuário</h1><p>Atualize os dados da conta de acesso.</p></div><a class="action-button" href="?page=users">← Voltar</a></div>
            <section class="panel">
                <form method="post">
                    <?= hidden() ?>
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" value="<?= h($id) ?>">
                    <div class="formgrid">
                        <label>Nome<input name="name" required maxlength="200" value="<?= h($u['name'] ?? '') ?>"></label>
                        <label>Telefone<input name="phone" type="tel" maxlength="50" value="<?= h($u['phone'] ?? '') ?>"></label>
                        <label>E-mail<input name="email" type="email" required maxlength="254" value="<?= h($u['email'] ?? '') ?>"></label>
                        <div class="password-field"><label for="password-field-1">Nova senha (opcional)</label><div class="password-control"><input id="password-field-1" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Deixe em branco para manter"><button type="button" class="password-toggle" aria-controls="password-field-1" aria-label="Mostrar senha" aria-pressed="false" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="M3 3l18 18"/></svg></button></div></div>
                    </div>
                    <p class="muted">A alteração do e-mail muda o endereço de login. A senha atual nunca é exibida. Se alterar suas próprias credenciais, entre novamente.</p>
                    <div class="row-actions"><button>Salvar alterações</button><a class="action-button" href="?page=users">Cancelar</a></div>
                </form>
            </section>

        <?php elseif ($page === 'users' && $admin): ?>
            <h1>Usuários</h1>
            <p>Crie contas e gerencie as permissões da equipe.</p>
            <div class="row-actions users-toolbar"><button type="button" class="button" id="toggle-user-form" aria-expanded="false" aria-controls="new-user-panel">+ Novo usuário</button></div>
            <section class="panel collapsible" id="new-user-panel" hidden>
                <h2>Novo usuário</h2>
                <form method="post">
                    <?= hidden() ?>
                    <input type="hidden" name="action" value="create_user">
                    <div class="formgrid">
                        <label>Nome<input name="name" required maxlength="200" autocomplete="off"></label>
                        <label>Telefone<input name="phone" type="tel" maxlength="50" autocomplete="off"></label>
                        <label>E-mail<input name="email" type="email" required maxlength="254" autocomplete="off"></label>
                        <div class="password-field"><label for="password-field-2">Senha inicial</label><div class="password-control"><input id="password-field-2" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password"><button type="button" class="password-toggle" aria-controls="password-field-2" aria-label="Mostrar senha" aria-pressed="false" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="M3 3l18 18"/></svg></button></div></div>
                    </div>
                    <p class="muted">A conta será criada como usuário comum ativo. O perfil pode ser alterado abaixo.</p>
                    <button>Cadastrar usuário</button>
                </form>
            </section>
            <?php $team = api('GET', 'profiles?order=name.asc'); ?>
            <section class="panel team-panel">
                <div class="team-heading"><div><h2>Usuários cadastrados</h2><p>Gerencie os dados e os acessos da equipe.</p></div><span class="team-count"><?= count($team) ?> conta(s)</span></div>
                <div class="team-list">
                    <?php foreach ($team as $u): $self = $u['id'] === $p['id']; $isAdmin = $u['role'] === 'admin'; ?>
                        <article class="team-member">
                            <div class="team-identity"><span class="team-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></span><div class="team-contact"><strong><?= h($u['name'] ?: 'Sem nome') ?></strong><?php if ($self): ?><small class="team-own">Sua conta</small><?php endif; ?><span><?= h($u['email'] ?: 'E-mail não informado') ?></span><span><?= h($u['phone'] ?: 'Telefone não informado') ?></span></div></div>
                            <div class="team-badges"><span class="team-role <?= $isAdmin ? 'team-admin' : '' ?>"><?= $isAdmin ? 'Administrador' : 'Usuário' ?></span><span class="team-status <?= $u['active'] ? 'is-active' : 'is-inactive' ?>"><?= $u['active'] ? 'Ativo' : 'Inativo' ?></span></div>
                            <div class="team-actions"><a class="action-button" href="?page=edit_user&amp;id=<?= h($u['id']) ?>">Editar dados</a><?php if (!$self): ?><form method="post" class="team-permissions"><?= hidden() ?><input type="hidden" name="id" value="<?= h($u['id']) ?>"><label>Perfil de acesso<select name="role"><option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Usuário</option><option value="admin" <?= $isAdmin ? 'selected' : '' ?>>Administrador</option></select></label><label class="inline team-active"><input type="checkbox" name="active" <?= $u['active'] ? 'checked' : '' ?>> Ativo</label><button name="action" value="user">Salvar</button></form><?php endif; ?></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$team): ?><p>Nenhum usuário encontrado.</p><?php endif; ?>
                </div>
            </section>

        <?php else: ?>
            <h1>Página indisponível</h1>
        <?php endif; ?>
    <?php } catch (Throwable $e) { ?>
        <div class="error"><?= h($e instanceof RuntimeException ? $e->getMessage() : 'Erro ao carregar dados.') ?></div>
    <?php } ?>

    <footer class="site-footer">Desenvolvido por <a href="https://rsdigitallab.com/" target="_blank" rel="noopener noreferrer">RS Digital Lab</a></footer>
</main>
<?php endif; ?>

<dialog id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-description"><div class="dialog-content"><div class="dialog-symbol" aria-hidden="true">↔</div><h2 id="confirm-title">Confirmar movimentação</h2><p id="confirm-description"></p><div class="dialog-actions"><button type="button" class="secondary" id="confirm-cancel" autofocus>Cancelar</button><button type="button" id="confirm-accept">Confirmar</button></div></div></dialog>
<dialog id="gallery-dialog" aria-label="Visualização de imagem"><button class="secondary" type="button" id="gallery-close">Fechar ×</button><img alt="Imagem ampliada"></dialog>
</body>
</html>
<?php performance_headers(); ob_end_flush(); ?>
