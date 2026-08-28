-- =========================================================
-- SPARK — "Quebre o Bloqueio. Acenda a Chama Criativa."
-- Atualização para a versão profissional.
-- Roda por cima do bd.sql. Seguro de executar mais de uma vez.
-- =========================================================

CREATE DATABASE IF NOT EXISTS spark
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE spark;

-- =========================================================
-- 1. PERFIL DE ARTISTA
-- O feed inteligente e o Botão Conexão dependem desses campos.
-- =========================================================
ALTER TABLE usuario
  ADD COLUMN IF NOT EXISTS nome_exibicao   VARCHAR(100) NULL AFTER nome_usuario,
  ADD COLUMN IF NOT EXISTS area_criativa   ENUM('musica','visual','design','escrita','audiovisual','outro')
                                           NOT NULL DEFAULT 'outro' AFTER bio,
  ADD COLUMN IF NOT EXISTS ferramenta      VARCHAR(80)  NULL AFTER area_criativa,
  ADD COLUMN IF NOT EXISTS estado_criativo ENUM('fluxo','bloqueado','buscando_referencia','aberto_colab','observando')
                                           NOT NULL DEFAULT 'observando' AFTER ferramenta,
  ADD COLUMN IF NOT EXISTS estado_em       DATETIME NULL AFTER estado_criativo,
  ADD COLUMN IF NOT EXISTS cidade          VARCHAR(80)  NULL AFTER pais,
  ADD COLUMN IF NOT EXISTS plano           ENUM('free','pro') NOT NULL DEFAULT 'free' AFTER cidade,
  ADD COLUMN IF NOT EXISTS capa_url        VARCHAR(500) NULL AFTER foto_perfil_url,
  ADD COLUMN IF NOT EXISTS ultimo_acesso   DATETIME NULL AFTER criado_em;

CREATE INDEX IF NOT EXISTS idx_user_estado ON usuario (estado_criativo, estado_em);
CREATE INDEX IF NOT EXISTS idx_user_area   ON usuario (area_criativa);

-- =========================================================
-- 2. BOTÃO CONEXÃO
-- Cada clique registra um encontro entre dois artistas.
-- =========================================================
CREATE TABLE IF NOT EXISTS conexoes (
  id           INT PRIMARY KEY AUTO_INCREMENT,
  idusuario_a  INT NOT NULL,
  idusuario_b  INT NOT NULL,
  motivo       VARCHAR(120) NULL,   -- por que o algoritmo aproximou os dois
  idconversa   INT NULL,            -- DM aberta automaticamente
  criado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conexao_a (idusuario_a, criado_em),
  FOREIGN KEY (idusuario_a) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (idusuario_b) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (idconversa)  REFERENCES conversas (id)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 3. NOTIFICAÇÕES (antes eram fixas no JavaScript; agora são reais)
-- =========================================================
CREATE TABLE IF NOT EXISTS notificacoes (
  id        INT PRIMARY KEY AUTO_INCREMENT,
  idusuario INT NOT NULL,                -- quem recebe
  idator    INT NOT NULL,                -- quem causou
  tipo      ENUM('curtida','comentario','seguidor','mencao','resposta_forum','conexao') NOT NULL,
  idmidias  INT NULL,
  extra     VARCHAR(255) NULL,           -- trecho do comentário, título do tópico...
  lida      TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_dono (idusuario, lida, criado_em),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (idator)    REFERENCES usuario (id_usuario) ON DELETE CASCADE,
  FOREIGN KEY (idmidias)  REFERENCES midias  (id_midia)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 4. PASTAS E FÓRUNS
-- =========================================================
ALTER TABLE Pastas
  ADD COLUMN IF NOT EXISTS descricao VARCHAR(300) NULL AFTER nome,
  ADD COLUMN IF NOT EXISTS privada   TINYINT(1) NOT NULL DEFAULT 0 AFTER descricao;

ALTER TABLE forum_categorias
  ADD COLUMN IF NOT EXISTS descricao VARCHAR(300) NULL AFTER nome,
  ADD COLUMN IF NOT EXISTS icone     VARCHAR(50)  NULL AFTER descricao;

ALTER TABLE forum_topicos
  ADD COLUMN IF NOT EXISTS texto   TEXT NULL AFTER titulo,
  ADD COLUMN IF NOT EXISTS visitas INT NOT NULL DEFAULT 0 AFTER texto;

-- =========================================================
-- 5. ÍNDICES QUE FALTAVAM (feed e perfil ficam muito mais rápidos)
-- =========================================================
CREATE INDEX IF NOT EXISTS idx_midias_autor_data  ON midias (idusuario, criado_em);
CREATE INDEX IF NOT EXISTS idx_midias_data        ON midias (criado_em);
CREATE INDEX IF NOT EXISTS idx_curtidas_midia     ON curtidas (idmidias);
CREATE INDEX IF NOT EXISTS idx_coment_midia       ON comentarios (idmidias, criado_em);
CREATE INDEX IF NOT EXISTS idx_seguidores_seguido ON seguidores (idseguido);
CREATE INDEX IF NOT EXISTS idx_msg_conversa       ON mensagens (idconversa, enviado_em);
CREATE INDEX IF NOT EXISTS idx_interacoes_user    ON interacoes (idusuario, criado_em);

-- =========================================================
-- 6. CONTEÚDO INICIAL
-- =========================================================
INSERT IGNORE INTO tipos_user (id, nome_tipo) VALUES (1, 'admin'), (2, 'usuario');

INSERT IGNORE INTO Tipos_midia (id, tipo_midia) VALUES
  (1, 'imagem'), (2, 'video'), (3, 'documento'), (4, 'audio');

-- Tags nascem agrupadas pelas áreas criativas do pitch
INSERT IGNORE INTO tags (nome) VALUES
  ('sample'), ('loop'), ('beat'), ('mixagem'), ('composicao'), ('letra'),
  ('paleta'), ('pintura'), ('ilustracao'), ('sketch'), ('tipografia'),
  ('ui'), ('branding'), ('fotografia'), ('roteiro'), ('poesia'),
  ('colab'), ('referencia'), ('processo'), ('bloqueio');

INSERT IGNORE INTO forum_categorias (id, nome, descricao, icone) VALUES
  (1, 'Geral',              'Assuntos livres da comunidade',                  'fa-comments'),
  (2, 'Bloqueio Criativo',  'Como destravar quando nada sai',                 'fa-brain'),
  (3, 'Produção Musical',   'Samples, mixagem, composição e gêneros',         'fa-sliders'),
  (4, 'Artes Visuais',      'Técnicas, materiais, paletas e processo',        'fa-brush'),
  (5, 'Design & UI',        'Interfaces, tipografia e produto',               'fa-pen-nib'),
  (6, 'Escrita & Roteiro',  'Narrativa, letra, poesia e argumento',           'fa-feather'),
  (7, 'Colaborações',       'Procure parceiros e monte projetos',             'fa-handshake'),
  (8, 'Feedback',           'Peça e dê crítica construtiva no trabalho',      'fa-comment-dots');
