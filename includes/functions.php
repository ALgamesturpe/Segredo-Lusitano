<?php
// SEGREDO LUSITANO - Funções Auxiliares
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// ---------- GEOLOCALIZAÇÃO ----------
function get_client_ip(): string {
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
    _migrar_localizacao();
    $geo = geolocate_ip(get_client_ip());
    if ($geo['pais']) {
        db()->prepare('UPDATE utilizadores SET pais_registo=?, regiao_registo=?, cidade_registo=? WHERE id=?')
           ->execute([$geo['pais'], $geo['regiao'], $geo['cidade'], $user_id]);
    }
}

// ---------- DENUNCIAS / MODERACAO ----------
function motivos_denuncia(): array {
    return [
        'spam' => 'Spam',
        'discurso_ofensivo' => 'Discurso ofensivo',
        'conteudo_inapropriado' => 'Conteudo inapropriado',
        'informacao_falsa' => 'Informacao falsa',
    ];
}

function motivo_denuncia_label(string $motivo): string {
    $motivos = motivos_denuncia();
    return $motivos[$motivo] ?? $motivo;
}

function local_nome_publico(array $local): string {
    return (string)$local['nome'];
}

function local_descricao_publica(array $local): string {
    return (string)$local['descricao'];
}

function comentario_autor_publico(array $comentario): string {
    return ((int)($comentario['denunciado'] ?? 0) === 1) ? '[removed]' : (string)$comentario['autor_nome'];
}

function comentario_texto_publico(array $comentario): string {
    return ((int)($comentario['denunciado'] ?? 0) === 1) ? '[removed]' : (string)$comentario['texto'];
}

function apagar_upload_local(string $ficheiro): void {
    if ($ficheiro === '') return;
    $path = UPLOAD_DIR . $ficheiro;
    if (is_file($path)) {
        @unlink($path);
    }
}

function limpar_imagens_local(int $local_id): void {
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
    $st = db()->prepare('SELECT bloqueado FROM locais WHERE id = ?');
    $st->execute([$local_id]);
    $val = $st->fetchColumn();
    return ((int)$val) === 1;
}

function local_bloqueado_deve_ser_eliminado(int $local_id): bool {
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
    $where = ['l.estado = "aprovado"', 'l.bloqueado = 0', 'l.apagado_em IS NULL'];
    $params = [];
    if (!empty($filtros['regiao'])) { $where[] = 'l.regiao_id = ?'; $params[] = $filtros['regiao']; }
    if (!empty($filtros['categoria'])) { $where[] = 'l.categoria_id = ?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['dificuldade'])) { $where[] = 'l.dificuldade = ?'; $params[] = $filtros['dificuldade']; }
    if (!empty($filtros['pesquisa'])) { $where[] = 'l.nome LIKE ?'; $params[] = '%' . $filtros['pesquisa'] . '%'; }

    $dist_select = '';
    $dist_having = '';
    $dist_params = [];

    $lat = (float)($filtros['lat'] ?? 0);
    $lng = (float)($filtros['lng'] ?? 0);
    if ($lat != 0 || $lng != 0) {
        $raio = (int)($filtros['raio'] ?? 50);
        if (!in_array($raio, [10, 25, 50, 100, 200], true)) $raio = 50;
        $haversine = '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(l.latitude)) *
            COS(RADIANS(l.longitude) - RADIANS(?)) +
            SIN(RADIANS(?)) * SIN(RADIANS(l.latitude))
        ))))';
        $dist_select = ', ' . $haversine . ' AS distancia';
        $dist_having = ' HAVING distancia <= ' . $raio;
        $dist_params = [$lat, $lng, $lat];
        $order = 'distancia ASC';
    } else {
        $ordem_input = $filtros['ordem'] ?? 'recente';
        if (!in_array($ordem_input, ['likes', 'vistas', 'recente', 'antigo'], true)) {
            $ordem_input = 'recente';
        }
        $order = match($ordem_input) {
            'likes'  => '(SELECT COUNT(*) FROM likes WHERE local_id = l.id) DESC',
            'vistas' => 'l.vistas DESC',
            'antigo' => 'l.criado_em ASC',
            default  => 'l.criado_em DESC'
        };
    }

    $sql = 'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
                   r.nome AS regiao_nome, u.username, u.nome AS autor_nome,
                   (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                   (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios,
                   (SELECT COUNT(*) FROM favoritos WHERE local_id = l.id) AS total_guardados'
         . $dist_select .
          ' FROM locais l
            JOIN categorias c ON c.id = l.categoria_id
            JOIN regioes r    ON r.id = l.regiao_id
            JOIN utilizadores u ON u.id = l.utilizador_id
            WHERE ' . implode(' AND ', $where) .
            $dist_having .
           ' ORDER BY ' . $order .
           ' LIMIT ' . (int)$limite . ' OFFSET ' . (int)$offset;
    $st = db()->prepare($sql);
    $st->execute(array_merge($dist_params, $params));
    return $st->fetchAll();
}

function get_local(int $id): ?array {
    $st = db()->prepare(
        'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
                r.nome AS regiao_nome, u.username, u.nome AS autor_nome, u.avatar,
                (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios,
                (SELECT COUNT(*) FROM favoritos WHERE local_id = l.id) AS total_guardados
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
    db()->prepare('UPDATE locais SET apagado_em = NOW() WHERE id = ?')->execute([$id]);
}

function incrementar_vistas(int $local_id): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'viewed_' . $local_id;
    if (!isset($_SESSION[$key])) {
        db()->prepare('UPDATE locais SET vistas = vistas + 1 WHERE id = ?')->execute([$local_id]);
        $_SESSION[$key] = true;
    }
}

// ---------- LIKES ----------
function toggle_like(int $local_id, int $user_id): array {
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
    $st = db()->prepare('SELECT id FROM likes WHERE local_id=? AND utilizador_id=?');
    $st->execute([$local_id, $user_id]);
    return (bool)$st->fetch();
}

// ---------- COMENTÁRIOS ----------
function get_comentarios(int $local_id): array {
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
        criar_notificacao($dono_id, $user_id, 'comentario', $local_id);
    }
    return (int)db()->lastInsertId();
}

// ---------- FOTOS ----------
function get_fotos(int $local_id): array {
    $st = db()->prepare('SELECT * FROM fotos WHERE local_id = ? AND denunciada = 0 ORDER BY criado_em ASC');
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function upload_foto(array $file, int $local_id, int $user_id): string|false {
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
    db()->prepare('UPDATE locais SET estado=? WHERE id=?')->execute([$estado, $id]);
    if ($estado === 'aprovado') {
        $st = db()->prepare('SELECT utilizador_id FROM locais WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) add_pontos($row['utilizador_id'], PONTOS_LOCAL);
    }
}

function get_denuncias(): array {
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
    $st = db()->query('SELECT * FROM categorias ORDER BY nome');
    return $st->fetchAll();
}

function get_regioes(): array {
    $st = db()->query('SELECT * FROM regioes ORDER BY nome');
    return $st->fetchAll();
}

// ---------- FAVORITOS ----------
function _migrar_favoritos(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec('
        CREATE TABLE IF NOT EXISTS favoritos (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            utilizador_id INT NOT NULL,
            local_id      INT NOT NULL,
            criado_em     DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_fav (utilizador_id, local_id),
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
            FOREIGN KEY (local_id)      REFERENCES locais(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function toggle_favorito(int $local_id, int $user_id): array {
    _migrar_favoritos();
    $st = db()->prepare('SELECT id FROM favoritos WHERE local_id=? AND utilizador_id=?');
    $st->execute([$local_id, $user_id]);
    if ($st->fetch()) {
        db()->prepare('DELETE FROM favoritos WHERE local_id=? AND utilizador_id=?')->execute([$local_id, $user_id]);
        $guardado = false;
    } else {
        db()->prepare('INSERT INTO favoritos (local_id,utilizador_id) VALUES (?,?)')->execute([$local_id, $user_id]);
        $guardado = true;
    }
    $st2 = db()->prepare('SELECT COUNT(*) FROM favoritos WHERE local_id=?');
    $st2->execute([$local_id]);
    return ['guardado' => $guardado, 'total' => (int)$st2->fetchColumn()];
}

function user_guardou(int $local_id, int $user_id): bool {
    _migrar_favoritos();
    $st = db()->prepare('SELECT id FROM favoritos WHERE local_id=? AND utilizador_id=?');
    $st->execute([$local_id, $user_id]);
    return (bool)$st->fetch();
}

// ---------- UTILITÁRIOS DE DATA ----------
function tempo_atras(string $data): string {
    $diff = time() - strtotime($data);
    if ($diff < 60)     return 'agora mesmo';
    if ($diff < 3600)   return 'há ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'há ' . floor($diff / 3600) . 'h';
    if ($diff < 604800) { $d = floor($diff / 86400); return 'há ' . $d . ' dia' . ($d > 1 ? 's' : ''); }
    return date('d/m/Y', strtotime($data));
}

// ---------- STORIES ----------
function _migrar_stories(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec('
        CREATE TABLE IF NOT EXISTS stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            utilizador_id INT NOT NULL,
            local_id INT NULL,
            texto TEXT NOT NULL,
            foto VARCHAR(255) NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expira_em TIMESTAMP NOT NULL,
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
            FOREIGN KEY (local_id) REFERENCES locais(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    $dir = dirname(UPLOAD_DIR) . '/stories/';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
}

function get_stories(int $limite = 20, int $offset = 0): array {
    _migrar_stories();
    _migrar_story_interacoes();
    $st = db()->prepare(
        'SELECT s.*, u.username, u.nome AS autor_nome, u.avatar AS autor_avatar,
                l.nome AS local_nome,
                (SELECT COUNT(*) FROM story_reacoes WHERE story_id = s.id) AS total_reacoes,
                (SELECT COUNT(*) FROM story_comentarios WHERE story_id = s.id) AS total_comentarios_count
         FROM stories s
         JOIN utilizadores u ON u.id = s.utilizador_id
         LEFT JOIN locais l ON l.id = s.local_id
         WHERE s.expira_em > NOW()
         ORDER BY s.criado_em DESC
         LIMIT ? OFFSET ?'
    );
    $st->execute([$limite, $offset]);
    return $st->fetchAll();
}

function count_stories(): int {
    _migrar_stories();
    return (int)db()->query('SELECT COUNT(*) FROM stories WHERE expira_em > NOW()')->fetchColumn();
}

function add_story(int $user_id, string $texto, ?int $local_id, ?string $foto): int {
    _migrar_stories();
    $st = db()->prepare(
        'INSERT INTO stories (utilizador_id, local_id, texto, foto, expira_em)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    );
    $st->execute([$user_id, $local_id, $texto, $foto]);
    return (int)db()->lastInsertId();
}

// ---------- STORY INTERAÇÕES (reações + comentários) ----------
function _migrar_story_interacoes(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    _migrar_stories();
    db()->exec('
        CREATE TABLE IF NOT EXISTS story_reacoes (
            story_id INT NOT NULL,
            utilizador_id INT NOT NULL,
            emoji VARCHAR(10) NOT NULL DEFAULT "❤️",
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (story_id, utilizador_id),
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    db()->exec('
        CREATE TABLE IF NOT EXISTS story_comentarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            utilizador_id INT NOT NULL,
            texto VARCHAR(500) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function get_story_bubbles(): array {
    _migrar_stories();
    $st = db()->query(
        'SELECT u.id, u.username, u.nome, u.avatar,
                COUNT(s.id) AS total,
                MAX(s.criado_em) AS ultimo_story
         FROM stories s
         JOIN utilizadores u ON u.id = s.utilizador_id
         WHERE s.expira_em > NOW()
         GROUP BY u.id, u.username, u.nome, u.avatar
         ORDER BY MAX(s.criado_em) DESC
         LIMIT 30'
    );
    return $st->fetchAll();
}

function get_stories_por_user(int $user_id): array {
    _migrar_stories();
    $st = db()->prepare(
        'SELECT s.*, u.username, u.nome AS autor_nome, u.avatar AS autor_avatar, l.nome AS local_nome
         FROM stories s
         JOIN utilizadores u ON u.id = s.utilizador_id
         LEFT JOIN locais l ON l.id = s.local_id
         WHERE s.utilizador_id = ? AND s.expira_em > NOW()
         ORDER BY s.criado_em DESC'
    );
    $st->execute([$user_id]);
    return $st->fetchAll();
}

function get_story_reacoes(int $story_id): array {
    _migrar_story_interacoes();
    $st = db()->prepare('SELECT emoji, COUNT(*) AS total FROM story_reacoes WHERE story_id = ? GROUP BY emoji ORDER BY total DESC');
    $st->execute([$story_id]);
    return $st->fetchAll();
}

function get_minha_reacao_story(int $story_id, int $user_id): ?string {
    _migrar_story_interacoes();
    $st = db()->prepare('SELECT emoji FROM story_reacoes WHERE story_id = ? AND utilizador_id = ?');
    $st->execute([$story_id, $user_id]);
    $row = $st->fetch();
    return $row ? $row['emoji'] : null;
}

function toggle_story_reacao(int $story_id, int $user_id, string $emoji): array {
    $validos = ['❤️', '👍', '😮', '🔥'];
    if (!in_array($emoji, $validos, true)) return ['ok' => false];
    $atual = get_minha_reacao_story($story_id, $user_id);
    if ($atual === $emoji) {
        db()->prepare('DELETE FROM story_reacoes WHERE story_id = ? AND utilizador_id = ?')->execute([$story_id, $user_id]);
        $reagiu = false;
        $emoji_ativo = null;
    } else {
        db()->prepare('INSERT INTO story_reacoes (story_id, utilizador_id, emoji) VALUES (?,?,?) ON DUPLICATE KEY UPDATE emoji=?')
            ->execute([$story_id, $user_id, $emoji, $emoji]);
        $reagiu = true;
        $emoji_ativo = $emoji;
    }
    return ['ok' => true, 'reagiu' => $reagiu, 'emoji' => $emoji_ativo, 'reacoes' => get_story_reacoes($story_id)];
}

function get_story_comentarios(int $story_id): array {
    _migrar_story_interacoes();
    $st = db()->prepare(
        'SELECT c.*, u.username, u.nome, u.avatar
         FROM story_comentarios c
         JOIN utilizadores u ON u.id = c.utilizador_id
         WHERE c.story_id = ?
         ORDER BY c.criado_em ASC LIMIT 50'
    );
    $st->execute([$story_id]);
    return $st->fetchAll();
}

function add_story_comentario(int $story_id, int $user_id, string $texto): array {
    _migrar_story_interacoes();
    $texto = trim($texto);
    if (strlen($texto) < 1 || strlen($texto) > 500) return ['ok' => false, 'erro' => 'Texto inválido'];
    $st = db()->prepare('SELECT id FROM stories WHERE id = ? AND expira_em > NOW()');
    $st->execute([$story_id]);
    if (!$st->fetch()) return ['ok' => false, 'erro' => 'Story não encontrado'];
    db()->prepare('INSERT INTO story_comentarios (story_id, utilizador_id, texto) VALUES (?,?,?)')->execute([$story_id, $user_id, $texto]);
    $id = (int)db()->lastInsertId();
    $c = db()->prepare('SELECT c.*, u.username, u.nome, u.avatar FROM story_comentarios c JOIN utilizadores u ON u.id = c.utilizador_id WHERE c.id = ?');
    $c->execute([$id]);
    return ['ok' => true, 'comentario' => $c->fetch()];
}

// ---------- ATUALIZAÇÕES DE LOCAL ----------
function _migrar_atualizacoes_local(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec('
        CREATE TABLE IF NOT EXISTS atualizacoes_local (
            id INT AUTO_INCREMENT PRIMARY KEY,
            local_id INT NOT NULL,
            utilizador_id INT NOT NULL,
            texto VARCHAR(280) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expira_em TIMESTAMP NOT NULL,
            FOREIGN KEY (local_id) REFERENCES locais(id) ON DELETE CASCADE,
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function get_atualizacoes_local(int $local_id): array {
    _migrar_atualizacoes_local();
    $st = db()->prepare(
        'SELECT a.*, u.username, u.nome AS autor_nome
         FROM atualizacoes_local a
         JOIN utilizadores u ON u.id = a.utilizador_id
         WHERE a.local_id = ? AND a.expira_em > NOW()
         ORDER BY a.criado_em DESC
         LIMIT 10'
    );
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function add_atualizacao_local(int $local_id, int $user_id, string $texto): int {
    _migrar_atualizacoes_local();
    $st = db()->prepare(
        'INSERT INTO atualizacoes_local (local_id, utilizador_id, texto, expira_em)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
    );
    $st->execute([$local_id, $user_id, $texto]);
    return (int)db()->lastInsertId();
}

// ---------- NOTIFICAÇÕES ----------
function _migrar_notificacoes(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec('
        CREATE TABLE IF NOT EXISTS notificacoes (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            utilizador_id INT NOT NULL,
            ator_id       INT NOT NULL,
            tipo          ENUM(\'like\',\'comentario\',\'seguidor\',\'checkin\') NOT NULL,
            local_id      INT NULL,
            lida          TINYINT(1) DEFAULT 0,
            criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
            FOREIGN KEY (ator_id)       REFERENCES utilizadores(id) ON DELETE CASCADE,
            FOREIGN KEY (local_id)      REFERENCES locais(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function criar_notificacao(int $para, int $ator, string $tipo, ?int $local_id = null): void {
    if ($para === $ator) return;
    _migrar_notificacoes();
    // Evitar duplicados recentes (mesma ação nas últimas 24h)
    $st = db()->prepare(
        'SELECT id FROM notificacoes
         WHERE utilizador_id=? AND ator_id=? AND tipo=? AND (local_id<=>?)
           AND criado_em > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $st->execute([$para, $ator, $tipo, $local_id]);
    if ($st->fetch()) return;
    db()->prepare(
        'INSERT INTO notificacoes (utilizador_id, ator_id, tipo, local_id) VALUES (?,?,?,?)'
    )->execute([$para, $ator, $tipo, $local_id]);
}

function get_notificacoes(int $user_id, int $limite = 40): array {
    _migrar_notificacoes();
    $st = db()->prepare(
        'SELECT n.*, u.username AS ator_username, u.nome AS ator_nome, u.avatar AS ator_avatar,
                l.nome AS local_nome
         FROM notificacoes n
         JOIN utilizadores u ON u.id = n.ator_id
         LEFT JOIN locais l ON l.id = n.local_id
         WHERE n.utilizador_id = ?
         ORDER BY n.criado_em DESC
         LIMIT ?'
    );
    $st->execute([$user_id, $limite]);
    return $st->fetchAll();
}

function count_notificacoes_nao_lidas(int $user_id): int {
    _migrar_notificacoes();
    $st = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE utilizador_id=? AND lida=0');
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

function marcar_notificacoes_lidas(int $user_id, ?int $notif_id = null): void {
    _migrar_notificacoes();
    if ($notif_id) {
        db()->prepare('UPDATE notificacoes SET lida=1 WHERE id=? AND utilizador_id=?')
           ->execute([$notif_id, $user_id]);
    } else {
        db()->prepare('UPDATE notificacoes SET lida=1 WHERE utilizador_id=?')
           ->execute([$user_id]);
    }
}

// ---------- CHECK-INS ----------
function _migrar_checkins(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    db()->exec('
        CREATE TABLE IF NOT EXISTS checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            utilizador_id INT NOT NULL,
            local_id INT NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_checkin (utilizador_id, local_id),
            FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
            FOREIGN KEY (local_id) REFERENCES locais(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

function user_fez_checkin(int $local_id, int $user_id): bool {
    _migrar_checkins();
    $st = db()->prepare('SELECT id FROM checkins WHERE local_id = ? AND utilizador_id = ?');
    $st->execute([$local_id, $user_id]);
    return (bool)$st->fetch();
}

function get_total_checkins(int $user_id): int {
    _migrar_checkins();
    $st = db()->prepare('SELECT COUNT(*) FROM checkins WHERE utilizador_id = ?');
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

// ---------- LISTAS ----------
function count_locais(array $filtros = []): int {
    $where = ['l.estado = "aprovado"', 'l.bloqueado = 0', 'l.apagado_em IS NULL'];
    $params = [];
    if (!empty($filtros['regiao']))     { $where[] = 'l.regiao_id = ?';    $params[] = $filtros['regiao']; }
    if (!empty($filtros['categoria']))  { $where[] = 'l.categoria_id = ?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['dificuldade'])){ $where[] = 'l.dificuldade = ?';  $params[] = $filtros['dificuldade']; }
    if (!empty($filtros['pesquisa']))   { $where[] = 'l.nome LIKE ?';      $params[] = '%' . $filtros['pesquisa'] . '%'; }

    $dist_params = [];
    $lat = (float)($filtros['lat'] ?? 0);
    $lng = (float)($filtros['lng'] ?? 0);
    if ($lat != 0 || $lng != 0) {
        $raio = (int)($filtros['raio'] ?? 50);
        if (!in_array($raio, [10, 25, 50, 100, 200], true)) $raio = 50;
        $where[] = '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(l.latitude)) *
            COS(RADIANS(l.longitude) - RADIANS(?)) +
            SIN(RADIANS(?)) * SIN(RADIANS(l.latitude))
        )))) <= ' . $raio;
        $dist_params = [$lat, $lng, $lat];
    }

    $st = db()->prepare('SELECT COUNT(*) FROM locais l WHERE ' . implode(' AND ', $where));
    $st->execute(array_merge($params, $dist_params));
    return (int)$st->fetchColumn();
}
