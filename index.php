<?php 
session_start(); 
require 'conexao.php';

$stmt = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Spark</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #16161f;
  --surface: #1E1E2A;
  --surface2: #2A2A38;
  --surface3: #333344;
  --border: #33334a;
  --spark: #8B5CF6;
  --spark-hover: #7C3AED;
  --spark-dim: #5b3db5;
  --spark-glow: rgba(139,92,246,0.15);
  --spark-glow2: rgba(139,92,246,0.07);
  --text: #f0f0f5;
  --text2: #9090aa;
  --text3: #55556a;
  --accent: #a78bfa;
  --sidebar-w: 72px;
  --sidebar-w-expanded: 220px;
  --font-display: 'Syne', sans-serif;
  --font-body: 'DM Sans', sans-serif;
  --radius: 14px;
  --radius-sm: 8px;
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  overflow-x: hidden;
  min-height: 100vh;
}

/* ── SIDEBAR ── */
.sidebar {
  position: fixed;
  left: 0; top: 0;
  height: 100vh;
  width: var(--sidebar-w);
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px 0;
  z-index: 200;
  transition: width 0.25s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}

.sidebar:hover {
  width: var(--sidebar-w-expanded);
}

.sidebar:hover .nav-label { opacity: 1; transform: translateX(0); }

.logo-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 32px;
  padding: 0 16px;
  width: 100%;
  cursor: pointer;
  min-width: var(--sidebar-w-expanded);
}

.logo-icon {
  width: 36px;
  height: 36px;
  background: var(--spark);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
  flex-shrink: 0;
  box-shadow: 0 0 16px var(--spark-glow);
}

.logo-text {
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 22px;
  color: var(--text);
  letter-spacing: -0.5px;
  white-space: nowrap;
}

.logo-text span { color: var(--spark); }

.nav-links {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  flex: 1;
  padding: 0 8px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 12px;
  border-radius: var(--radius-sm);
  cursor: pointer;
  color: var(--text2);
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
  min-width: 0;
  position: relative;
}

.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: var(--spark-glow); color: var(--spark); }
.nav-item.active .nav-icon { color: var(--spark); }

.nav-icon {
  font-size: 20px;
  width: 22px;
  text-align: center;
  flex-shrink: 0;
}

.nav-label {
  font-size: 14px;
  font-weight: 500;
  opacity: 0;
  transform: translateX(-6px);
  transition: opacity 0.2s, transform 0.2s;
}

.sidebar-bottom {
  width: 100%;
  padding: 0 8px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.avatar-small {
  width: 28px;
  height: 28px;
  background: linear-gradient(135deg, var(--spark) 0%, var(--accent) 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 11px;
  color: white;
  flex-shrink: 0;
}

/* ── MAIN ── */
.content {
  margin-left: var(--sidebar-w);
  min-height: 100vh;
  transition: margin-left 0.25s cubic-bezier(.4,0,.2,1);
}

/* ── TOP BAR ── */
.top-bar {
  padding: 16px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  position: sticky;
  top: 0;
  background: rgba(10,10,10,0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
  z-index: 100;
}

.search-wrap {
  flex: 1;
  max-width: 440px;
  position: relative;
}

.search-wrap i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text3);
  font-size: 14px;
}

.search-wrap input {
  width: 100%;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 40px;
  padding: 9px 16px 9px 38px;
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s, background 0.2s;
}

.search-wrap input:focus {
  border-color: var(--spark);
  background: var(--surface3);
}

.search-wrap input::placeholder { color: var(--text3); }

.top-bar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }

.btn-create {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--spark);
  color: white;
  border: none;
  border-radius: 40px;
  padding: 9px 18px;
  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, transform 0.1s;
  box-shadow: 0 0 12px var(--spark-glow);
}

.btn-create:hover { background: var(--spark-hover); transform: translateY(-1px); }

/* ── SECTIONS ── */
.view-section { display: none; }
.view-section.active { display: block; }

/* ── MASONRY FEED ── */
.feed-wrapper { padding: 24px; }
.masonry-grid { column-count: 5; column-gap: 14px; }

@media (max-width: 1400px) { .masonry-grid { column-count: 4; } }
@media (max-width: 1100px) { .masonry-grid { column-count: 3; } }
@media (max-width: 750px)  { .masonry-grid { column-count: 2; } }

.card {
  break-inside: avoid;
  border-radius: var(--radius);
  margin-bottom: 14px;
  overflow: hidden;
  cursor: pointer;
  position: relative;
  background: var(--surface);
  border: 1px solid var(--border);
  transition: transform 0.2s, box-shadow 0.2s;
  animation: fadeUp 0.4s ease forwards;
  opacity: 0;
}

.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
  border-color: var(--surface3);
}

.card-img {
  width: 100%;
  display: block;
  background: linear-gradient(110deg, var(--surface) 8%, var(--surface2) 18%, var(--surface) 33%);
  background-size: 200% 100%;
  animation: shimmer 1.8s linear infinite;
}

.card:hover .card-overlay { opacity: 1; }

.card-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(0,0,0,0.75) 0%, transparent 50%);
  opacity: 0;
  transition: opacity 0.2s;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 14px;
}

.card-overlay-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.btn-save {
  background: var(--spark);
  color: white;
  border: none;
  border-radius: 40px;
  padding: 7px 16px;
  font-family: var(--font-body);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-save:hover { background: var(--spark-hover); }

.btn-icon-sm {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  backdrop-filter: blur(4px);
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: background 0.15s;
}
.btn-icon-sm:hover { background: rgba(255,255,255,0.25); }

@keyframes shimmer {
  to { background-position-x: -200%; }
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── LOADER ── */
.loader {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
  padding: 40px;
}

.dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--spark);
  animation: bounce 0.6s infinite alternate;
}
.dot:nth-child(2) { animation-delay: 0.15s; background: var(--accent); }
.dot:nth-child(3) { animation-delay: 0.3s;  background: var(--text3); }

@keyframes bounce {
  to { transform: translateY(-8px); opacity: 0.4; }
}

/* ── PROFILE ── */
.profile-container { max-width: 900px; margin: 0 auto; padding: 40px 24px 60px; }

.profile-hero {
  display: flex;
  gap: 40px;
  margin-bottom: 48px;
  align-items: center;
}

.avatar-ring {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  padding: 3px;
  background: linear-gradient(135deg, var(--spark), var(--accent), var(--spark-dim));
  flex-shrink: 0;
}

.avatar-large {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: var(--surface2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 40px;
  color: var(--spark);
  border: 3px solid var(--bg);
}

.profile-info { flex: 1; }

.profile-username {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 26px;
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.profile-stats {
  display: flex;
  gap: 28px;
  margin-bottom: 16px;
}

.stat-item { text-align: left; }
.stat-num { font-family: var(--font-display); font-weight: 700; font-size: 18px; color: var(--text); display: block; }
.stat-label { font-size: 12px; color: var(--text2); }

.profile-fullname {
  font-weight: 500;
  font-size: 15px;
  color: var(--text);
  margin-bottom: 4px;
}

.profile-bio { font-size: 14px; color: var(--text2); line-height: 1.6; margin-bottom: 16px; }

.profile-actions { display: flex; gap: 10px; align-items: center; margin-bottom: 0; }

.btn-outline {
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 40px;
  padding: 8px 18px;
  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.btn-outline:hover { background: var(--surface2); border-color: var(--text3); }

.btn-primary {
  background: var(--spark);
  border: none;
  color: white;
  border-radius: 40px;
  padding: 8px 18px;
  font-family: var(--font-body);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
  box-shadow: 0 0 10px var(--spark-glow);
}
.btn-primary:hover { background: var(--spark-hover); }

/* Tabs */
.profile-tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 28px;
}

.tab-btn {
  background: none;
  border: none;
  color: var(--text2);
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 14px;
  padding: 12px 18px;
  cursor: pointer;
  position: relative;
  transition: color 0.15s;
  letter-spacing: 0.2px;
}

.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--text); }

.tab-btn.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0; right: 0;
  height: 2px;
  background: var(--spark);
  border-radius: 2px 2px 0 0;
}

.tab-content { display: none; }
.tab-content.active { display: block; animation: fadeUp 0.3s ease; }

/* Boards */
.boards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.board-card {
  cursor: pointer;
  border-radius: var(--radius);
  overflow: hidden;
  background: var(--surface);
  border: 1px solid var(--border);
  transition: transform 0.2s, box-shadow 0.2s;
}

.board-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }

.board-preview {
  height: 140px;
  display: grid;
  grid-template-columns: 2fr 1fr;
  grid-template-rows: 1fr 1fr;
  gap: 2px;
  background: var(--bg);
}

.preview-main {
  grid-row: 1 / 3;
  background: var(--surface2);
}

.preview-side { background: var(--surface3); }

.board-info { padding: 12px 14px; }
.board-info h3 { font-family: var(--font-display); font-weight: 600; font-size: 14px; margin-bottom: 3px; }
.board-info span { font-size: 12px; color: var(--text2); }

/* Forums */
.forums-list { display: flex; flex-direction: column; gap: 12px; }

.forum-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 18px;
  transition: background 0.15s, border-color 0.15s;
  cursor: pointer;
}

.forum-card:hover { background: var(--surface2); border-color: var(--surface3); }

.forum-icon-wrap {
  width: 48px;
  height: 48px;
  background: var(--spark-glow);
  border: 1px solid rgba(230,0,35,0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--spark);
  font-size: 22px;
  flex-shrink: 0;
}

.forum-info { flex: 1; }
.forum-info h4 { font-family: var(--font-display); font-weight: 600; font-size: 14px; margin-bottom: 4px; }
.forum-info p { font-size: 13px; color: var(--text2); margin-bottom: 4px; }
.forum-info .forum-meta { font-size: 11px; color: var(--text3); }

/* ── SETTINGS ── */
.settings-container { max-width: 740px; margin: 0 auto; padding: 40px 24px; }

.settings-header {
  font-family: var(--font-display);
  font-weight: 700;
  font-size: 22px;
  margin-bottom: 28px;
  letter-spacing: -0.3px;
}

.settings-layout {
  display: flex;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 480px;
}

.settings-nav {
  width: 190px;
  border-right: 1px solid var(--border);
  padding: 16px 0;
  flex-shrink: 0;
}

.settings-nav-btn {
  display: block;
  width: 100%;
  background: none;
  border: none;
  color: var(--text2);
  text-align: left;
  padding: 11px 20px;
  font-family: var(--font-body);
  font-size: 14px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.settings-nav-btn:hover { background: var(--surface2); color: var(--text); }
.settings-nav-btn.active { color: var(--spark); background: var(--spark-glow2); font-weight: 500; }

.settings-body {
  flex: 1;
  padding: 28px 32px;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.field-group { display: flex; flex-direction: column; gap: 6px; }

.field-group label {
  font-size: 12px;
  font-weight: 500;
  color: var(--text2);
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

.field-group input,
.field-group textarea {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  color: var(--text);
  padding: 10px 14px;
  font-family: var(--font-body);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.field-group input:focus,
.field-group textarea:focus { border-color: var(--spark); }

.field-group textarea { height: 90px; resize: none; }

.settings-avatar-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 6px;
}

.avatar-settings {
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, var(--spark), #ff4d6a);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 800;
  font-size: 20px;
  color: white;
  flex-shrink: 0;
}

.change-photo-link {
  color: var(--spark);
  font-size: 13px;
  font-weight: 500;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  font-family: var(--font-body);
}

.save-row { padding-top: 4px; }

.btn-save-settings {
  background: var(--spark);
  border: none;
  color: white;
  border-radius: 40px;
  padding: 10px 26px;
  font-family: var(--font-body);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
  box-shadow: 0 0 10px var(--spark-glow);
}
.btn-save-settings:hover { background: var(--spark-hover); }

/* ── DIVIDER TAG ── */
.section-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--spark-glow2);
  border: 1px solid rgba(230,0,35,0.15);
  border-radius: 40px;
  padding: 5px 14px;
  font-family: var(--font-display);
  font-size: 12px;
  font-weight: 600;
  color: var(--spark);
  letter-spacing: 0.3px;
  margin-bottom: 20px;
}

.hidden { display: none !important; }
</style>
</head>
<body>

<nav class="sidebar" id="sidebar">
  <div class="logo-wrap" id="nav-home">
    <div class="logo-icon"><i class="fa-solid fa-bolt"></i></div>
    <span class="logo-text nav-label">Sp<span>a</span>rk</span>
  </div>

  <div class="nav-links">
    <a class="nav-item active" id="link-feed" href="#">
      <i class="fa-solid fa-house nav-icon"></i>
      <span class="nav-label">Início</span>
    </a>
    <a class="nav-item" href="#">
      <i class="fa-solid fa-magnifying-glass nav-icon"></i>
      <span class="nav-label">Explorar</span>
    </a>
    <a class="nav-item" href="#">
      <i class="fa-solid fa-paper-plane nav-icon"></i>
      <span class="nav-label">Mensagens</span>
    </a>
    <a class="nav-item" href="#">
      <i class="fa-solid fa-heart nav-icon"></i>
      <span class="nav-label">Notificações</span>
    </a>
    <a class="nav-item" href="#">
      <i class="fa-solid fa-square-plus nav-icon"></i>
      <span class="nav-label">Criar</span>
    </a>
  </div>

  <div class="sidebar-bottom">
    <a class="nav-item" id="link-profile" href="#">
      <div class="avatar-small" id="avatar-small-nav">S</div>
      <span class="nav-label">Perfil</span>
    </a>
    <a class="nav-item" id="link-settings" href="#">
      <i class="fa-solid fa-gear nav-icon"></i>
      <span class="nav-label">Configurações</span>
    </a>
  </div>
</nav>

<main class="content">

  <!-- TOP BAR -->
  <header class="top-bar" id="top-bar">
    <div class="search-wrap">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" placeholder="Pesquisar inspirações...">
    </div>
    <div class="top-bar-actions">
      <button class="btn-create">
        <i class="fa-solid fa-plus"></i> Criar
      </button>
    </div>
  </header>

  <!-- FEED -->
  <section id="feed-section" class="view-section active">
    <div class="feed-wrapper">
      <div class="section-tag"><i class="fa-solid fa-fire-flame-curved"></i> Em alta</div>
      <div id="feed" class="masonry-grid"></div>
      <div id="loader" class="loader">
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
      </div>
    </div>
  </section>

  <!-- PERFIL -->
  <section id="profile-section" class="view-section hidden">
    <div class="profile-container">
      <div class="profile-hero">
        <div class="avatar-ring">
          <div class="avatar-large" id="display-avatar">
            <?php if (!empty($user['foto_perfil_url'])): ?>
              <img src="<?= htmlspecialchars($user['foto_perfil_url']) ?>" 
                alt="Foto de perfil"
                style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
             <?php else: ?>
                <?= strtoupper(substr($user['nome_usuario'], 0, 1)) ?>
                <?php endif; ?>
             </div>
          </div>
        <div class="profile-info">
          <div class="profile-actions" style="margin-bottom:14px">
            <h2 class="profile-username" id="display-username" style="margin:0"><?= $user['nome_usuario'] ?></h2>
            <button class="btn-outline" id="edit-profile-btn" style="margin-left:12px">Editar perfil</button>
            <button class="btn-outline">Compartilhar</button>
            <i class="fa-solid fa-gear" id="gear-settings" style="color:var(--text2);font-size:18px;cursor:pointer;margin-left:4px"></i>
          </div>
          <div class="profile-stats">
            <div class="stat-item"><span class="stat-num">0</span><span class="stat-label">pins</span></div>
            <div class="stat-item"><span class="stat-num">0</span><span class="stat-label">seguidores</span></div>
            <div class="stat-item"><span class="stat-num">0</span><span class="stat-label">seguindo</span></div>
          </div>
          <p class="profile-bio" id="display-bio"><?= $user['bio']?></p>
        </div>
      </div>

      <div class="profile-tabs">
        <button class="tab-btn active" data-target="tab-pins">Pins</button>
        <button class="tab-btn" data-target="tab-boards">Pastas</button>
        <button class="tab-btn" data-target="tab-forums">Fóruns</button>
      </div>

      <div id="tab-pins" class="tab-content active">
        <div id="profile-pins" class="masonry-grid"></div>
      </div>
    </div>
  </section>

  <!-- CONFIGURAÇÕES -->
  <section id="settings-section" class="view-section hidden">
    <div class="settings-container">
      <h2 class="settings-header">Configurações</h2>
      <div class="settings-layout">
        <div class="settings-nav">
          <button class="settings-nav-btn active">Editar perfil</button>
        </div>
        <div class="settings-body">
          <div class="settings-avatar-row">
            <div class="avatar-settings" id="settings-avatar">S</div>
            <div>
              <p style="font-weight:500;margin-bottom:4px" id="settings-username-display"><?= $user['nome_usuario'] ?></p>
              <button class="change-photo-link">Alterar foto do perfil</button>
            </div>
          </div>
          <form method="post" action="update.php">
            <div class="field-group">
              <label> Novo nome de usuário</label>
              <input type="text" id="input-username" placeholder="Digite seu novo nome" name="nova_nome" >
            </div>
              <br>
            <div class="field-group">
              <label>Nova bio</label>
              <input type="text" id="input-bio" placeholder="Digite sua nova bio" name="nova_bio">
            </div>
              <br>
            <div class="field-group">
              <label>Nova senha</label>
              <input type="password" id="input-password" placeholder="Digite sua nova senha" name="nova_senha">
            </div>
              <br>
            <div class="save-row">
              <button class="btn-save-settings" type="submit" id="save-btn">Salvar alterações</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

</main>

<script>
const $ = id => document.getElementById(id);

const feedSection     = $('feed-section');
const profileSection  = $('profile-section');
const settingsSection = $('settings-section');
const topBar          = $('top-bar');
const feedGrid        = $('feed');
const profilePins     = $('profile-pins');
const loader          = $('loader');

let currentView = 'feed';
let isLoading   = false;

const heights = [180, 220, 260, 300, 340, 380];
const rnd = arr => arr[Math.floor(Math.random() * arr.length)];

function createCard() {
  const card = document.createElement('div');
  card.className = 'card';
  const h = rnd(heights);
  card.innerHTML = `
    <div class="card-img" style="height:${h}px"></div>
    <div class="card-overlay">
      <div class="card-overlay-actions">
        <button class="btn-save">Salvar</button>
        <button class="btn-icon-sm"><i class="fa-solid fa-ellipsis"></i></button>
      </div>
    </div>`;
  return card;
}

function loadItems(n, grid) {
  if (isLoading) return;
  isLoading = true;
  loader.style.opacity = '1';
  setTimeout(() => {
    for (let i = 0; i < n; i++) grid.appendChild(createCard());
    isLoading = false;
    loader.style.opacity = '0';
  }, 400);
}

function showSection(view) {
  [feedSection, profileSection, settingsSection].forEach(s => s.classList.add('hidden'));
  [$('link-feed')].forEach(l => l.classList.remove('active'));

  document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));

  if (view === 'feed') {
    feedSection.classList.remove('hidden');
    feedSection.classList.add('active');
    topBar.classList.remove('hidden');
    $('link-feed').classList.add('active');
  } else if (view === 'profile') {
    profileSection.classList.remove('hidden');
    profileSection.classList.add('active');
    topBar.classList.add('hidden');
    $('link-profile').classList.add('active');
    if (!profilePins.children.length) loadItems(12, profilePins);
  } else if (view === 'settings') {
    settingsSection.classList.remove('hidden');
    settingsSection.classList.add('active');
    topBar.classList.add('hidden');
    $('link-settings').classList.add('active');
  }
  currentView = view;
  window.scrollTo(0, 0);
}

$('link-feed').addEventListener('click', e => { e.preventDefault(); showSection('feed'); });
$('link-profile').addEventListener('click', e => { e.preventDefault(); showSection('profile'); });
$('link-settings').addEventListener('click', e => { e.preventDefault(); showSection('settings'); });
$('nav-home').addEventListener('click', () => showSection('feed'));
$('edit-profile-btn').addEventListener('click', () => showSection('settings'));
$('gear-settings').addEventListener('click', () => showSection('settings'));

// Tabs perfil
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.target).classList.add('active');
    if (btn.dataset.target === 'tab-pins' && !profilePins.children.length) loadItems(12, profilePins);
  });
});

// Salvar config
$('save-btn').addEventListener('click', () => {
  const name = $('input-name').value;
  const user = $('input-username').value;
  const bio  = $('input-bio').value;
  const init = user.charAt(0).toUpperCase();

  $('display-fullname').textContent = name;
  $('display-username').textContent = user;
  $('display-bio').innerHTML = bio.replace(/\n/g, '<br>');
  $('display-avatar').textContent = init;
  $('settings-avatar').textContent = init;
  $('avatar-small-nav').textContent = init;
  $('settings-username-display').textContent = user;

  const btn = $('save-btn');
  btn.textContent = 'Salvo ✓';
  setTimeout(() => {
    btn.textContent = 'Salvar alterações';
    showSection('profile');
  }, 900);
});

// Scroll infinito
window.addEventListener('scroll', () => {
  if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 600) {
    if (currentView === 'feed') loadItems(10, feedGrid);
    else if (currentView === 'profile') {
      const pinsTab = document.getElementById('tab-pins');
      if (pinsTab.classList.contains('active')) loadItems(8, profilePins);
    }
  }
});

// Init
loadItems(20, feedGrid);
</script>
</body>
</html>