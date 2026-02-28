<?php
// ============================================================
// SEGREDO LUSITANO - Funções Auxiliares
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// ---------- LOCAIS ----------
function get_locais(array $filtros = [], int $limite = 12, int $offset = 0): array {
    $where = ['l.estado = "aprovado"'];
    $params = [];
    if (!empty($filtros['regiao'])) { $where[] = 'l.regiao_id = ?'; $params[] = $filtros['regiao']; }
    if (!empty($filtros['categoria'])) { $where[] = 'l.categoria_id = ?'; $params[] = $filtros['categoria']; }
    if (!empty($filtros['dificuldade'])) { $where[] = 'l.dificuldade = ?'; $params[] = $filtros['dificuldade']; }
    if (!empty($filtros['pesquisa'])) { $where[] = 'l.nome LIKE ?'; $params[] = '%' . $filtros['pesquisa'] . '%'; }

    // Whitelist allowed ordering options to prevent SQL injection
    $ordem_input = $filtros['ordem'] ?? 'recente';
    $ordem_permitida = ['likes', 'vistas', 'recente'];
    if (!in_array($ordem_input, $ordem_permitida, true)) {
        $ordem_input = 'recente';
    }

    $order = match($ordem_input) {
        'likes'  => '(SELECT COUNT(*) FROM likes WHERE local_id = l.id) DESC',
        'vistas' => 'l.vistas DESC',
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
    $st = db()->prepare(
        'SELECT l.*, c.nome AS categoria_nome, c.icone AS categoria_icone,
                r.nome AS regiao_nome, u.username, u.nome AS autor_nome,
                (SELECT COUNT(*) FROM likes WHERE local_id = l.id) AS total_likes,
                (SELECT COUNT(*) FROM comentarios WHERE local_id = l.id) AS total_comentarios
         FROM locais l
         JOIN categorias c ON c.id = l.categoria_id
         JOIN regioes r    ON r.id = l.regiao_id
         JOIN utilizadores u ON u.id = l.utilizador_id
         WHERE l.id = ?'
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
    db()->prepare('DELETE FROM locais WHERE id = ?')->execute([$id]);
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
    if ($st->fetch()) {
        db()->prepare('DELETE FROM likes WHERE local_id=? AND utilizador_id=?')->execute([$local_id, $user_id]);
        $liked = false;
    } else {
        db()->prepare('INSERT INTO likes (local_id,utilizador_id) VALUES (?,?)')->execute([$local_id, $user_id]);
        add_pontos($user_id, PONTOS_LIKE);
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
         WHERE cm.local_id = ? AND cm.denunciado = 0
         ORDER BY cm.criado_em ASC'
    );
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function add_comentario(int $local_id, int $user_id, string $texto): int {
    $st = db()->prepare('INSERT INTO comentarios (local_id,utilizador_id,texto) VALUES (?,?,?)');
    $st->execute([$local_id, $user_id, $texto]);
    add_pontos($user_id, PONTOS_COMENTARIO);
    return (int)db()->lastInsertId();
}

// ---------- FOTOS ----------
function get_fotos(int $local_id): array {
    $st = db()->prepare('SELECT * FROM fotos WHERE local_id = ? AND denunciada = 0 ORDER BY criado_em ASC');
    $st->execute([$local_id]);
    return $st->fetchAll();
}

function upload_foto(array $file, int $local_id, int $user_id): string|false {
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
                (SELECT COUNT(*) FROM comentarios WHERE utilizador_id = u.id) AS total_comentarios
         FROM utilizadores u WHERE u.ativo = 1 AND u.role = "user"
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
    $st = db()->prepare('SELECT * FROM denuncias WHERE resolvida = 0 ORDER BY criado_em DESC');
    $st->execute();
    return $st->fetchAll();
}

function reportar(string $tipo, int $ref_id, int $user_id, string $motivo): void {
    $st = db()->prepare('INSERT INTO denuncias (tipo,referencia_id,utilizador_id,motivo) VALUES (?,?,?,?)');
    $st->execute([$tipo, $ref_id, $user_id, $motivo]);
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

function count_locais(array $filtros = []): int {
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
