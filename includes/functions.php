<?php
// SEGREDO LUSITANO - Funções Auxiliares
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// ---------- GEOLOCALIZAÇÃO ----------
function get_client_ip(): string {
    // Descobre o IP real do visitante, mesmo quando vem atrás de um proxy.
    $locais = ['127.0.0.1', '::1', 'localhost'];
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if ($ip && !in_array($ip, $locais, true)) return $ip;
        }
    }
    // Fallback para localhost: busca IP público do servidor (só em dev)
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $pub = @file_get_contents('https://api.ipify.org', false, $ctx);
    return $pub ? trim($pub) : '';
}

function geolocate_ip(string $ip): array {
    // Usa uma API externa para descobrir o país, região e cidade a partir de um endereço IP.
    $vazio = ['pais' => null, 'regiao' => null, 'cidade' => null];
    if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)) return $vazio;
    try {
        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,regionName,city", false, $ctx);
        if (!$json) return $vazio;
        $d = json_decode($json, true);
        if (!$d || ($d['status'] ?? '') !== 'success') return $vazio;
        return ['pais' => $d['country'] ?? null, 'regiao' => $d['regionName'] ?? null, 'cidade' => $d['city'] ?? null];
    } catch (\Throwable $e) { return $vazio; }
}

function guardar_localizacao_registo(int $user_id): void {
    // Guarda o país, região e cidade do utilizador no momento do registo, com base no IP.
    _migrar_localizacao();
    $geo = geolocate_ip(get_client_ip());
    if ($geo['pais']) {
        db()->prepare('UPDATE utilizadores SET pais_registo=?, regiao_registo=?, cidade_registo=? WHERE id=?')
           ->execute([$geo['pais'], $geo['regiao'], $geo['cidade'], $user_id]);
    }
}

// ---------- DENUNCIAS / MODERACAO ----------
function motivos_denuncia(): array {
    // Devolve a lista de motivos disponíveis para denunciar conteúdo.
    return [
        'spam' => 'Spam',
        'discurso_ofensivo' => 'Discurso ofensivo',
        'conteudo_inapropriado' => 'Conteudo inapropriado',
        'informacao_falsa' => 'Informacao falsa',
    ];
}

function motivo_denuncia_label(string $motivo): string {
    // Converte o código do motivo (ex: "spam") para o texto legível (ex: "Spam").
    $motivos = motivos_denuncia();
    return $motivos[$motivo] ?? $motivo;
}

function local_nome_publico(array $local): string {
    // Devolve o nome do local para mostrar ao público.
    return (string)$local['nome'];
}

function local_descricao_publica(array $local): string {
    // Devolve a descrição do local para mostrar ao público.
    return (string)$local['descricao'];
}

function comentario_autor_publico(array $comentario): string {
    // Devolve o nome do autor do comentário. Se foi denunciado, mostra [removed] em vez do nome.
    return ((int)($comentario['denunciado'] ?? 0) === 1) ? '[removed]' : (string)$comentario['autor_nome'];
}

function comentario_texto_publico(array $comentario): string {
    // Devolve o texto do comentário. Se foi denunciado, mostra [removed] em vez do conteúdo.
    return ((int)($comentario['denunciado'] ?? 0) === 1) ? '[removed]' : (string)$comentario['texto'];
}

function apagar_upload_local(string $ficheiro): void {
    // Apaga um ficheiro da pasta de uploads, se existir.
    if ($ficheiro === '') return;
    $path = UPLOAD_DIR . $ficheiro;
    if (is_file($path)) {
        @unlink($path);
    }
}

function limpar_imagens_local(int $local_id): void {
    // Apaga todas as imagens de um local — a foto de capa e as da galeria — e remove os registos da BD.
    $stCapa = db()->prepare('SELECT foto_capa FROM locais WHERE id = ?');
    $stCapa->execute([$local_id]);
    $capa = $stCapa->fetchColumn();
    if (is_string($capa) && $capa !== '') {
        apagar_upload_local($capa);
    }

    $stFotos = db()->prepare('SELECT ficheiro FROM fotos WHERE local_id = ?');
    $stFotos->execute([$local_id]);
    foreach ($stFotos->fetchAll() as $row) {
        apagar_upload_local((string)($row['ficheiro'] ?? ''));
    }

    db()->prepare('DELETE FROM fotos WHERE local_id = ?')->execute([$local_id]);
    db()->prepare('UPDATE locais SET foto_capa = NULL WHERE id = ?')->execute([$local_id]);
}

function local_bloqueado(int $local_id): bool {
    // Verifica se um local está bloqueado pelo administrador.
    $st = db()->prepare('SELECT bloqueado FROM locais WHERE id = ?');
    $st->execute([$local_id]);
    $val = $st->fetchColumn();
    return ((int)$val) === 1;
}

function local_bloqueado_deve_ser_eliminado(int $local_id): bool {
    // Verifica se um local bloqueado já não tem comentários ativos e pode ser eliminado de vez.
    $st = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN denunciado = 0 THEN 1 ELSE 0 END) AS ativos
         FROM comentarios
         WHERE local_id = ?'
    );
    $st->execute([$local_id]);
    $row = $st->fetch();
    $ativos = (int)($row['ativos'] ?? 0);
    return $ativos === 0;
}

function resolver_denuncias_local_e_comentarios(int $local_id): void {
    // Marca como resolvidas todas as denúncias abertas de um local e dos seus comentários.
    db()->prepare('UPDATE denuncias SET resolvida=1 WHERE tipo="local" AND referencia_id=? AND resolvida=0')->execute([$local_id]);
    db()->prepare(
        'UPDATE denuncias
         SET resolvida=1
         WHERE tipo="comentario"
           AND referencia_id IN (SELECT id FROM comentarios WHERE local_id = ?)
           AND resolvida=0'
    )->execute([$local_id]);
}

function ensure_moderacao_schema(): void {
    // Garante que a base de dados tem todas as colunas e índices necessários para a moderação. Só corre uma vez.
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $colAR = db()->query("SHOW COLUMNS FROM mensagens LIKE 'apagada_por_receptor'")->fetch();
        if (!$colAR) {
            db()->exec('ALTER TABLE mensagens ADD COLUMN apagada_por_receptor TINYINT(1) DEFAULT 0');
        }

        $colAT = db()->query("SHOW COLUMNS FROM mensagens LIKE 'apagada_para_todos'")->fetch();
        if (!$colAT) {
            db()->exec('ALTER TABLE mensagens ADD COLUMN apagada_para_todos TINYINT(1) DEFAULT 0');
        }

    try {
        $col = db()->query("SHOW COLUMNS FROM locais LIKE 'bloqueado'")->fetch();
        if (!$col) {
            db()->exec('ALTER TABLE locais ADD COLUMN bloqueado TINYINT(1) DEFAULT 0 AFTER estado');
        }

        $col2 = db()->query("SHOW COLUMNS FROM locais LIKE 'apagado_em'")->fetch();
        if (!$col2) {
            db()->exec('ALTER TABLE locais ADD COLUMN apagado_em DATETIME DEFAULT NULL');
        }

        $idx = db()->query("SHOW INDEX FROM denuncias WHERE Key_name = 'idx_denuncias_abertas'")->fetch();
        if (!$idx) {
            db()->exec('ALTER TABLE denuncias ADD INDEX idx_denuncias_abertas (resolvida, tipo, referencia_id)');
        }

        $colT = db()->query("SHOW COLUMNS FROM utilizadores LIKE 'termos_aceites_em'")->fetch();
        if (!$colT) {
            db()->exec('ALTER TABLE utilizadores ADD COLUMN termos_aceites_em DATETIME DEFAULT NULL');
        }

        $colF = db()->query("SHOW COLUMNS FROM mensagens LIKE 'ficheiro'")->fetch();
        if (!$colF) {
            db()->exec('ALTER TABLE mensagens ADD COLUMN ficheiro VARCHAR(255) NULL DEFAULT NULL');
        }

        $colCF = db()->query("SHOW COLUMNS FROM comentarios LIKE 'ficheiro'")->fetch();
        if (!$colCF) {
            db()->exec('ALTER TABLE comentarios ADD COLUMN ficheiro VARCHAR(255) NULL DEFAULT NULL');
        }

        // Migrar regiões para as 5 pretendidas: Norte, Centro, Sul, Açores, Madeira
        $sul = db()->query("SELECT id FROM regioes WHERE nome = 'Sul'")->fetch();
        if (!$sul) {
            db()->exec("INSERT INTO regioes (nome) VALUES ('Sul')");
            $sul_id = (int)db()->lastInsertId();
            $old = db()->query("SELECT id FROM regioes WHERE nome IN ('Lisboa e Vale do Tejo','Alentejo','Algarve')")->fetchAll(PDO::FETCH_COLUMN);
            if ($old) {
                $ids = implode(',', array_map('intval', $old));
                db()->exec("UPDATE locais SET regiao_id = $sul_id WHERE regiao_id IN ($ids)");
                db()->exec("DELETE FROM regioes WHERE id IN ($ids)");
            }
        }
    } catch (Throwable $e) {
        // Falha de permissao/migracao nao deve derrubar a aplicacao inteira.
    }
}

ensure_moderacao_schema();

// ---------- LOCAIS ----------
function get_locais(array $filtros = [], int $limite = 12, int $offset = 0): array {
    // Vai buscar à BD os locais aprovados. Aceita filtros de região, categoria, dificuldade e pesquisa, com paginação.
    $where = ['l.estado = "aprovado"', 'l.bloqueado = 0', 'l.apagado_em IS NULL'];
    $params = [];
    if (!empty($filtros['regiao'])) { $where[] = 'l.regiao_id = ?'; $params[] = $filtros['regiao']; }
    if (!empty($filtros['categoria'])) { $where[] = 'l.categoria_id = ?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['dificuldade'])) { $where[] = 'l.dificuldade = ?'; $params[] = $filtros['dificuldade']; }
    if (!empty($filtros['pesquisa'])) { $where[] = 'l.nome LIKE ?'; $params[] = '%' . $filtros['pesquisa'] . '%'; }

    // Whitelist allowed ordering options to prevent SQL injection
    $ordem_input = $filtros['ordem'] ?? 'recente';
    $ordem_permitida = ['likes', 'vistas', 'recente', 'antigo'];
    if (!in_array($ordem_input, $ordem_permitida, true)) {
        $ordem_input = 'recente';
    }

    $order = match($ordem_input) {
        'likes'  => '(SELECT COUNT(*) FROM likes WHERE local_id = l.id) DESC',
        'vistas' => 'l.vistas DESC',
        'antigo' => 'l.criado_em ASC',
        default  => 'l.criado_em DESC'
    };
    $sql = 'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
                   r.nome AS regiao_nome, u.username, u.nome AS autor_nome,
                   (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                   (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios
            FROM locais l
            JOIN categorias c ON c.id = l.categoria_id
            JOIN regioes r    ON r.id = l.regiao_id
            JOIN utilizadores u ON u.id = l.utilizador_id
            WHERE ' . implode(' AND ', $where) .
           ' ORDER BY ' . $order .
           ' LIMIT ' . (int)$limite . ' OFFSET ' . (int)$offset;
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function get_local(int $id): ?array {
    // Vai buscar os dados completos de um local pelo ID. Devolve null se não existir.
    $st = db()->prepare(
        'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
                r.nome AS regiao_nome, u.username, u.nome AS autor_nome, u.avatar,
                (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios
         FROM locais l
         JOIN categorias c ON c.id = l.categoria_id
         JOIN regioes r    ON r.id = l.regiao_id
         JOIN utilizadores u ON u.id = l.utilizador_id
         WHERE l.id = ? AND l.apagado_em IS NULL'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function save_local(array $data, ?int $id = null): int|false {
    // Guarda um local na base de dados. Se tiver ID, atualiza; se não tiver, cria um novo.
    if ($id) {
        $st = db()->prepare(
            'UPDATE locais SET nome=?, descricao=?, categoria_id=?, regiao_id=?,
             latitude=?, longitude=?, dificuldade=?, foto_capa=COALESCE(?,foto_capa)
             WHERE id=?'
        );
        $st->execute([
            $data['nome'], $data['descricao'], $data['categoria_id'], $data['regiao_id'],
            $data['latitude'], $data['longitude'], $data['dificuldade'],
            $data['foto_capa'] ?? null, $id
        ]);
        return $id;
    }
    $st = db()->prepare(
        'INSERT INTO locais (utilizador_id,categoria_id,regiao_id,nome,descricao,
          latitude,longitude,dificuldade,foto_capa,estado)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $data['utilizador_id'], $data['categoria_id'], $data['regiao_id'],
        $data['nome'], $data['descricao'], $data['latitude'], $data['longitude'],
        $data['dificuldade'], $data['foto_capa'] ?? null,
        'aprovado'  // publicação automática sem necessidade de aprovação
    ]);
    return (int)db()->lastInsertId();
}

function delete_local(int $id): void {
    // Marca um local como apagado com a data atual, sem o remover fisicamente da base de dados.
    db()->prepare('UPDATE locais SET apagado_em = NOW() WHERE id = ?')->execute([$id]);
}

function incrementar_vistas(int $local_id): void {
    // Aumenta o contador de visitas de um local, mas só conta uma vez por sessão.
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'viewed_' . $local_id;
    if (!isset($_SESSION[$key])) {
        db()->prepare('UPDATE locais SET vistas = vistas + 1 WHERE id = ?')->execute([$local_id]);
        $_SESSION[$key] = true;
    }
}

// ---------- LIKES ----------
function toggle_like(int $local_id, int $user_id): array {
    // Dá ou retira um like a um local. Atualiza os pontos do dono conforme o resultado.
    $st = db()->prepare('SELECT id FROM likes WHERE local_id=? AND utilizador_id=?');
    $st->execute([$local_id, $user_id]);

    $st_owner = db()->prepare('SELECT utilizador_id FROM locais WHERE id=?');
    $st_owner->execute([$local_id]);
    $owner_id = (int)$st_owner->fetchColumn();

    if ($st->fetch()) {
        db()->prepare('DELETE FROM likes WHERE local_id=? AND utilizador_id=?')->execute([$local_id, $user_id]);
        if ($owner_id) add_pontos($owner_id, -PONTOS_LIKE);
        $liked = false;
    } else {
        db()->prepare('INSERT INTO likes (local_id,utilizador_id) VALUES (?,?)')->execute([$local_id, $user_id]);
        if ($owner_id) add_pontos($owner_id, PONTOS_LIKE);
        $liked = true;
    }
    $st2 = db()->prepare('SELECT COUNT(*) FROM likes WHERE local_id=?');
    $st2->execute([$local_id]);
    return ['liked' => $liked, 'total' => (int)$st2->fetchColumn()];
}

function user_liked(int $local_id, int $user_id): bool {
    // Verifica se um utilizador já deu like a um local específico.
    $st = db()->prepare('SELECT id FROM likes WHERE local_id=? AND utilizador_id=?');
    $st->execute([$local_id, $user_id]);
    return (bool)$st->fetch();
}

// ---------- COMENTÁRIOS ----------
function get_comentarios(int $local_id): array {
    // Vai buscar todos os comentários de um local, ordenados do mais antigo para o mais recente.
    $st = db()->prepare(
        'SELECT cm.*, u.username, u.nome AS autor_nome, u.avatar
         FROM comentarios cm
         JOIN utilizadores u ON u.id = cm.utilizador_id
         WHERE cm.local_id = ?
         ORDER BY cm.criado_em ASC'
    );
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function add_comentario(int $local_id, int $user_id, string $texto, ?string $ficheiro = null): int {
    // Adiciona um comentário a um local e dá pontos ao dono do local. Não faz nada se o local estiver bloqueado.
    if (local_bloqueado($local_id)) {
        return 0;
    }
    $st = db()->prepare('INSERT INTO comentarios (local_id,utilizador_id,texto,ficheiro) VALUES (?,?,?,?)');
    $st->execute([$local_id, $user_id, $texto, $ficheiro]);
    $stDono = db()->prepare('SELECT utilizador_id FROM locais WHERE id = ?');
    $stDono->execute([$local_id]);
    $dono_id = (int)$stDono->fetchColumn();
    if ($dono_id && $dono_id !== (int)$user_id) {
        add_pontos($dono_id, PONTOS_COMENTARIO);
    }
    return (int)db()->lastInsertId();
}

// ---------- FOTOS ----------
function get_fotos(int $local_id): array {
    // Vai buscar as fotos de um local que não foram denunciadas.
    $st = db()->prepare('SELECT * FROM fotos WHERE local_id = ? AND denunciada = 0 ORDER BY criado_em ASC');
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function upload_foto(array $file, int $local_id, int $user_id): string|false {
    // Faz o upload de uma foto para um local, valida o tipo de ficheiro e guarda o registo na BD.
    if (local_bloqueado($local_id)) return false;
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($file['type'], $allowed)) return false;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nome = uniqid('foto_') . '.' . $ext;
    $dest = UPLOAD_DIR . $nome;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    db()->prepare('INSERT INTO fotos (local_id,utilizador_id,ficheiro) VALUES (?,?,?)')->execute([$local_id,$user_id,$nome]);
    return $nome;
}

// ---------- RANKING ----------
function get_ranking(int $limite = 10): array {
    // Vai buscar os utilizadores com mais pontos para o ranking.
    $st = db()->prepare(
        'SELECT u.id, u.username, u.nome, u.avatar, u.pontos,
                (SELECT COUNT(*) FROM locais WHERE utilizador_id = u.id AND estado = "aprovado") AS total_locais,
                (SELECT COUNT(*) FROM comentarios c JOIN locais l ON c.local_id = l.id WHERE l.utilizador_id = u.id) AS total_comentarios
         FROM utilizadores u WHERE u.ativo = 1 AND u.role = "user" AND u.pontos > 0
         ORDER BY u.pontos DESC LIMIT ?'
    );
    $st->execute([$limite]);
    return $st->fetchAll();
}

// ---------- MODERAÇÃO ----------
function get_pendentes(): array {
    // Vai buscar os locais que estão à espera de aprovação pelo administrador.
    $st = db()->prepare(
        'SELECT l.*, u.username, c.nome AS categoria_nome, r.nome AS regiao_nome
         FROM locais l JOIN utilizadores u ON u.id = l.utilizador_id
         JOIN categorias c ON c.id = l.categoria_id
         JOIN regioes r ON r.id = l.regiao_id
         WHERE l.estado = "pendente" ORDER BY l.criado_em ASC'
    );
    $st->execute();
    return $st->fetchAll();
}

function moderar_local(int $id, string $estado): void {
    // Aprova ou rejeita um local. Se for aprovado, dá pontos ao utilizador que o criou.
    db()->prepare('UPDATE locais SET estado=? WHERE id=?')->execute([$estado, $id]);
    if ($estado === 'aprovado') {
        $st = db()->prepare('SELECT utilizador_id FROM locais WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) add_pontos($row['utilizador_id'], PONTOS_LOCAL);
    }
}

function get_denuncias(): array {
    // Vai buscar todas as denúncias ainda por resolver, com informação detalhada sobre o conteúdo denunciado.
    $st = db()->prepare(
        'SELECT d.*, u.username AS denunciante_username,
                CASE
                    WHEN d.tipo = "local"      THEN COALESCE(l.bloqueado, 0)
                    WHEN d.tipo = "comentario" THEN COALESCE(c.denunciado, 0)
                    ELSE 0
                END AS alvo_bloqueado,
                CASE
                    WHEN d.tipo = "local"      THEN COALESCE(l.nome, "[indisponivel]")
                    WHEN d.tipo = "comentario" THEN COALESCE(c.texto, "[indisponivel]")
                    WHEN d.tipo = "foto"       THEN COALESCE(f.ficheiro, "[indisponivel]")
                    ELSE "[indisponivel]"
                END AS alvo_conteudo,
                CASE
                    WHEN d.tipo = "local"      THEN COALESCE(l.descricao, "[indisponivel]")
                    WHEN d.tipo = "comentario" THEN COALESCE(c.texto, "[indisponivel]")
                    WHEN d.tipo = "foto"       THEN COALESCE(f.ficheiro, "[indisponivel]")
                    ELSE "[indisponivel]"
                END AS alvo_conteudo_completo,
                CASE
                    WHEN d.tipo = "local"      THEN l.id
                    WHEN d.tipo = "comentario" THEN c.local_id
                    WHEN d.tipo = "foto"       THEN f.local_id
                    ELSE NULL
                END AS alvo_local_id,
                CASE
                    WHEN d.tipo = "local"      THEN l.foto_capa
                    WHEN d.tipo = "foto"       THEN f.ficheiro
                    ELSE NULL
                END AS alvo_foto_capa,
                CASE
                    WHEN d.tipo = "local"      THEN l.dificuldade
                    ELSE NULL
                END AS alvo_dificuldade,
                CASE
                    WHEN d.tipo = "local"      THEN l.vistas
                    ELSE NULL
                END AS alvo_vistas,
                CASE
                    WHEN d.tipo = "local"      THEN cat.nome
                    WHEN d.tipo = "comentario" THEN lc.nome
                    WHEN d.tipo = "foto"       THEN lf.nome
                    ELSE NULL
                END AS alvo_local_nome,
                CASE
                    WHEN d.tipo = "local"      THEN cat.nome
                    WHEN d.tipo = "comentario" THEN catc.nome
                    WHEN d.tipo = "foto"       THEN catf.nome
                    ELSE NULL
                END AS alvo_categoria
         FROM denuncias d
         JOIN utilizadores u ON u.id = d.utilizador_id
         LEFT JOIN locais      l    ON d.tipo = "local"      AND l.id = d.referencia_id
         LEFT JOIN categorias  cat  ON d.tipo = "local"      AND cat.id = l.categoria_id
         LEFT JOIN comentarios c    ON d.tipo = "comentario" AND c.id = d.referencia_id
         LEFT JOIN locais      lc   ON d.tipo = "comentario" AND lc.id = c.local_id
         LEFT JOIN categorias  catc ON d.tipo = "comentario" AND catc.id = lc.categoria_id
         LEFT JOIN fotos       f    ON d.tipo = "foto"       AND f.id = d.referencia_id
         LEFT JOIN locais      lf   ON d.tipo = "foto"       AND lf.id = f.local_id
         LEFT JOIN categorias  catf ON d.tipo = "foto"       AND catf.id = lf.categoria_id
         WHERE d.resolvida = 0
         ORDER BY d.criado_em DESC'
    );
    $st->execute();
    return $st->fetchAll();
}

function reportar(string $tipo, int $ref_id, int $user_id, string $motivo): bool {
    // Submete uma denúncia contra um local, comentário ou foto. Não permite denunciar o próprio conteúdo nem duplicar denúncias.
    $tipos_validos = ['local', 'comentario', 'foto'];
    if (!in_array($tipo, $tipos_validos, true)) return false;

    $motivos_validos = array_keys(motivos_denuncia());
    if (!in_array($motivo, $motivos_validos, true)) return false;

    if ($tipo === 'local') {
        $stAlvo = db()->prepare('SELECT utilizador_id FROM locais WHERE id = ?');
    } elseif ($tipo === 'foto') {
        $stAlvo = db()->prepare('SELECT utilizador_id FROM fotos WHERE id = ?');
    } else {
        $stAlvo = db()->prepare('SELECT utilizador_id FROM comentarios WHERE id = ?');
    }
    $stAlvo->execute([$ref_id]);
    $alvo = $stAlvo->fetch();
    if (!$alvo) return false;

    // Nao permite denunciar o proprio conteudo.
    if ((int)$alvo['utilizador_id'] === $user_id) return false;

    // Nao permite denuncia aberta duplicada para o mesmo item pelo mesmo user.
    $stDup = db()->prepare('SELECT id FROM denuncias WHERE tipo=? AND referencia_id=? AND utilizador_id=? AND resolvida=0 LIMIT 1');
    $stDup->execute([$tipo, $ref_id, $user_id]);
    if ($stDup->fetch()) return false;

    $st = db()->prepare('INSERT INTO denuncias (tipo,referencia_id,utilizador_id,motivo) VALUES (?,?,?,?)');
    $st->execute([$tipo, $ref_id, $user_id, $motivo]);
    return true;
}

function moderar_denuncias_item(string $tipo, int $ref_id, bool $bloquear): bool {
    // Bloqueia ou desbloqueia um item denunciado (local, comentário ou foto) e resolve as denúncias associadas.
    if (!in_array($tipo, ['local', 'comentario', 'foto'], true)) return false;

    if ($tipo === 'local') {
        $stLocal = db()->prepare('SELECT id FROM locais WHERE id = ?');
        $stLocal->execute([$ref_id]);
        if (!$stLocal->fetch()) return false;

        db()->prepare('UPDATE locais SET bloqueado=? WHERE id=?')->execute([$bloquear ? 1 : 0, $ref_id]);

        // Bloquear apenas marca o flag — não elimina conteúdo

        db()->prepare('UPDATE denuncias SET resolvida=1 WHERE tipo=? AND referencia_id=? AND resolvida=0')->execute([$tipo, $ref_id]);
        return true;
    }

    if ($tipo === 'foto') {
        $stFoto = db()->prepare('SELECT id, ficheiro FROM fotos WHERE id = ?');
        $stFoto->execute([$ref_id]);
        $foto = $stFoto->fetch();
        if (!$foto) return false;

        if ($bloquear) {
            // Eliminar o ficheiro físico e o registo da BD
            apagar_upload_local($foto['ficheiro']);
            db()->prepare('DELETE FROM fotos WHERE id = ?')->execute([$ref_id]);
        }

        db()->prepare('UPDATE denuncias SET resolvida=1 WHERE tipo=? AND referencia_id=? AND resolvida=0')->execute([$tipo, $ref_id]);
        return true;
    }
    
    $stCom = db()->prepare('SELECT id, local_id FROM comentarios WHERE id = ?');
    $stCom->execute([$ref_id]);
    $comentario = $stCom->fetch();
    if (!$comentario) return false;

    db()->prepare('UPDATE comentarios SET denunciado=? WHERE id=?')->execute([$bloquear ? 1 : 0, $ref_id]);

    db()->prepare('UPDATE denuncias SET resolvida=1 WHERE tipo=? AND referencia_id=? AND resolvida=0')->execute([$tipo, $ref_id]);

    if ($bloquear) {
        $localId = (int)$comentario['local_id'];
        if ($localId > 0 && local_bloqueado($localId) && local_bloqueado_deve_ser_eliminado($localId)) {
            resolver_denuncias_local_e_comentarios($localId);
            delete_local($localId);
        }
    }

    return true;
}

// ---------- LISTAS ----------
function get_categorias(): array {
    // Devolve todas as categorias disponíveis, ordenadas por nome.
    $st = db()->query('SELECT * FROM categorias ORDER BY nome');
    return $st->fetchAll();
}

function get_regioes(): array {
    // Devolve todas as regiões disponíveis, ordenadas por nome.
    $st = db()->query('SELECT * FROM regioes ORDER BY nome');
    return $st->fetchAll();
}

function count_locais(array $filtros = []): int {
    // Conta quantos locais existem com os filtros aplicados, usado para calcular a paginação.
    $where = ['l.estado = "aprovado"'];
    $params = [];
    if (!empty($filtros['regiao']))     { $where[] = 'l.regiao_id = ?';    $params[] = $filtros['regiao']; }
    if (!empty($filtros['categoria']))  { $where[] = 'l.categoria_id = ?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['dificuldade'])){ $where[] = 'l.dificuldade = ?';  $params[] = $filtros['dificuldade']; }
    if (!empty($filtros['pesquisa']))   { $where[] = 'l.nome LIKE ?';      $params[] = '%' . $filtros['pesquisa'] . '%'; }
    $st = db()->prepare('SELECT COUNT(*) FROM locais l WHERE ' . implode(' AND ', $where));
    $st->execute($params);
    return (int)$st->fetchColumn();
}
