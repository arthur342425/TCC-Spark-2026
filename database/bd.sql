-- =========================================================
-- SPARK - Script completo do banco de dados
-- Contém: estrutura original + fóruns + interações (curtidas/
-- seguidores) + sistema de tags/afinidade (algoritmo de gosto)
-- + sistema de mensagens diretas (DM) estilo Instagram
-- =========================================================

CREATE DATABASE IF NOT EXISTS spark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE spark;

-- =========================================================
-- 1. ESTRUTURA ORIGINAL (usuário, mídia, pastas, comentários)
-- =========================================================

CREATE TABLE IF NOT EXISTS tipos_user (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome_tipo VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS usuario (
  id_usuario INT PRIMARY KEY AUTO_INCREMENT,
  nome_usuario VARCHAR(100) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  bio VARCHAR(500),
  foto_perfil_url VARCHAR(500),
  idtipos_user INT,
  ativo BOOLEAN DEFAULT TRUE,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  pais VARCHAR(100),
  FOREIGN KEY (idtipos_user) REFERENCES tipos_user (id)
);

CREATE TABLE IF NOT EXISTS Pastas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idusuario INT,
  nome VARCHAR(100),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario)
);

CREATE TABLE IF NOT EXISTS Tipos_midia (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tipo_midia VARCHAR(50)
);

-- midias: adicionados mime_type, tamanho e duração (para suportar
-- imagem / vídeo / documento / áudio como anexo de verdade)
CREATE TABLE IF NOT EXISTS midias (
  id_midia INT PRIMARY KEY AUTO_INCREMENT,
  idusuario INT,
  idTipos_midia INT,
  titulo VARCHAR(255),
  descricao VARCHAR(1000),
  midia_url VARCHAR(500),
  mime_type VARCHAR(100),
  tamanho_bytes BIGINT,
  duracao_segundos INT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idTipos_midia) REFERENCES Tipos_midia (id)
);

CREATE TABLE IF NOT EXISTS midias_em_pasta (
  idPastas INT,
  idmidias INT,
  posicao INT,
  PRIMARY KEY (idPastas, idmidias),
  FOREIGN KEY (idPastas) REFERENCES Pastas (id),
  FOREIGN KEY (idmidias) REFERENCES midias (id_midia)
);

CREATE TABLE IF NOT EXISTS comentarios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idusuario INT,
  idmidias INT,
  texto VARCHAR(1000),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idmidias) REFERENCES midias (id_midia)
);

-- =========================================================
-- 2. INTERAÇÕES ENTRE USUÁRIOS (curtidas e seguidores)
-- =========================================================

CREATE TABLE IF NOT EXISTS curtidas (
  idusuario INT,
  idmidias INT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idusuario, idmidias),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idmidias) REFERENCES midias (id_midia)
);

CREATE TABLE IF NOT EXISTS seguidores (
  idseguidor INT,
  idseguido INT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idseguidor, idseguido),
  FOREIGN KEY (idseguidor) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idseguido) REFERENCES usuario (id_usuario)
);

-- =========================================================
-- 3. FÓRUNS
-- =========================================================

CREATE TABLE IF NOT EXISTS forum_categorias (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS forum_topicos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idcategoria INT,
  idusuario INT,
  titulo VARCHAR(255),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idcategoria) REFERENCES forum_categorias (id),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario)
);

CREATE TABLE IF NOT EXISTS forum_respostas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idtopico INT,
  idusuario INT,
  texto TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idtopico) REFERENCES forum_topicos (id),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario)
);

-- =========================================================
-- 4. TAGS + ALGORITMO DE AFINIDADE (perfil de gosto do usuário)
-- =========================================================

CREATE TABLE IF NOT EXISTS tags (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(50) UNIQUE
);

CREATE TABLE IF NOT EXISTS midia_tags (
  idmidias INT,
  idtag INT,
  PRIMARY KEY (idmidias, idtag),
  FOREIGN KEY (idmidias) REFERENCES midias (id_midia),
  FOREIGN KEY (idtag) REFERENCES tags (id)
);

-- registra cada interação do usuário com uma mídia (o "combustível"
-- do algoritmo: visualização, curtida, comentário, compartilhamento
-- ou "pulou" o conteúdo)
CREATE TABLE IF NOT EXISTS interacoes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idusuario INT,
  idmidias INT,
  tipo_interacao ENUM('visualizacao','curtida','comentario','compartilhamento','pulou') NOT NULL,
  tempo_assistido_seg INT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idmidias) REFERENCES midias (id_midia)
);

-- pontuação acumulada do usuário por tag; é essa tabela que o feed
-- consulta pra decidir o que mostrar primeiro pra cada usuário
CREATE TABLE IF NOT EXISTS afinidade_usuario_tag (
  idusuario INT,
  idtag INT,
  pontuacao INT DEFAULT 0,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (idusuario, idtag),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idtag) REFERENCES tags (id)
);

-- =========================================================
-- 5. MENSAGENS DIRETAS (DM estilo Instagram)
-- =========================================================

-- uma conversa pode ser individual (1 pra 1) ou em grupo;
-- em conversas individuais o campo "nome" fica nulo
CREATE TABLE IF NOT EXISTS conversas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tipo ENUM('individual','grupo') NOT NULL DEFAULT 'individual',
  nome VARCHAR(100) NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- quem participa de cada conversa (permite grupo com N pessoas)
CREATE TABLE IF NOT EXISTS conversa_participantes (
  idconversa INT,
  idusuario INT,
  entrou_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (idconversa, idusuario),
  FOREIGN KEY (idconversa) REFERENCES conversas (id),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario)
);

-- cada mensagem enviada dentro de uma conversa; pode ser só texto
-- ou vir com uma mídia anexada (foto, vídeo, áudio, documento)
CREATE TABLE IF NOT EXISTS mensagens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  idconversa INT,
  idusuario_remetente INT,
  texto VARCHAR(2000) NULL,
  idmidia_anexo INT NULL,
  enviado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  editado BOOLEAN DEFAULT FALSE,
  apagado BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (idconversa) REFERENCES conversas (id),
  FOREIGN KEY (idusuario_remetente) REFERENCES usuario (id_usuario),
  FOREIGN KEY (idmidia_anexo) REFERENCES midias (id_midia)
);

-- status de leitura por participante, igual ao "visto"/"lido" do Instagram
CREATE TABLE IF NOT EXISTS mensagens_status (
  idmensagem INT,
  idusuario INT,
  lida BOOLEAN DEFAULT FALSE,
  lida_em DATETIME NULL,
  PRIMARY KEY (idmensagem, idusuario),
  FOREIGN KEY (idmensagem) REFERENCES mensagens (id),
  FOREIGN KEY (idusuario) REFERENCES usuario (id_usuario)
);

-- =========================================================
-- 6. DADOS INICIAIS
-- =========================================================

INSERT IGNORE INTO tipos_user (id, nome_tipo) VALUES (1, 'admin');
INSERT IGNORE INTO tipos_user (id, nome_tipo) VALUES (2, 'usuario');

INSERT IGNORE INTO Tipos_midia (id, tipo_midia) VALUES (1, 'imagem');
INSERT IGNORE INTO Tipos_midia (id, tipo_midia) VALUES (2, 'video');
INSERT IGNORE INTO Tipos_midia (id, tipo_midia) VALUES (3, 'documento');
INSERT IGNORE INTO Tipos_midia (id, tipo_midia) VALUES (4, 'audio');

INSERT IGNORE INTO forum_categorias (id, nome) VALUES (1, 'Geral');
