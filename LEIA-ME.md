# SPARK
### Quebre o Bloqueio. Acenda a Chama Criativa.

Rede social feita **por artistas, para artistas**, construída em torno de um
problema específico: o bloqueio criativo. Diferente do Instagram ou TikTok —
que foram feitos para o *consumo* do resultado — o Spark é feito para o
*processo* da criação.

---

## Como rodar

### Endereço

```
http://localhost/spark-app/
```

### Jeito rápido
Dê dois cliques em **`ABRIR SPARK.bat`**. Ele liga o Apache, liga o MySQL e
abre o navegador sozinho.

### Jeito manual
1. Copie a pasta `spark-app` para dentro de `C:\xampp\htdocs\`
2. Abra o **XAMPP Control Panel** e clique em *Start* no **Apache** e no **MySQL**
3. Importe o banco (só na primeira vez), pelo terminal:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database/bd.sql
   C:\xampp\mysql\bin\mysql.exe -u root < database/upgrade.sql
   ```
   Ou pelo phpMyAdmin: `http://localhost/phpmyadmin` → Importar → escolher os dois
   arquivos, nessa ordem.
4. Acesse **http://localhost/spark-app/**

> Requisitos: PHP 8.0+, MySQL/MariaDB 10.4+. Testado no XAMPP 8.0.30.

---

## ⚠️ Uma cópia só

O erro mais fácil de cometer com XAMPP é acabar com **várias cópias do projeto**
dentro do `htdocs` — um ZIP extraído numa pasta nova, um backup, uma versão
antiga. Você abre a errada, a tela está idêntica à de ontem, e parece que o
código não mudou. Aconteceu três vezes durante o desenvolvimento.

Duas defesas foram embutidas:

**1. Carimbo de versão.** Em *Configurações*, no rodapé, aparece:

```
código de   12/08/2026 15:08
pasta       /spark-app/
```

Se a pasta não for `/spark-app/`, é cópia errada. Se a data for antiga, é código
velho. Acaba a adivinhação.

**2. Cache-busting.** CSS e JS saem com a data do arquivo na URL
(`app.css?v=1786538…`). Quando o arquivo muda, a URL muda e o navegador é
obrigado a rebaixar. Nunca mais é preciso `Ctrl+Shift+R`.

> Não extraia o ZIP dentro do `htdocs` se o projeto já estiver instalado ali.
> Extraia em outro lugar (Documentos, Área de Trabalho) ou substitua a pasta
> `spark-app` inteira.

---

## Os três pilares

### 1. Feed Inteligente
Não é ordem cronológica. Cada post recebe uma nota calculada em SQL a partir de:

| Sinal | Peso | Por quê |
|---|---|---|
| Afinidade com as tags | × 1,6 | O gosto que o usuário demonstrou na prática |
| Mesma área criativa | +18 | Produtor vê sample; pintor vê paleta |
| Autor que eu sigo | +30 | Vínculo explícito vale mais que tudo |
| Curtidas / comentários | ×2,5 / ×4 | Engajamento real da rede |
| Horas desde a publicação | −0,9/h | Impede o feed de estagnar |
| Já visto antes | −22 | Evita repetição |

O perfil de gosto é alimentado por `interacoes` + `afinidade_usuario_tag`:
cada visualização, curtida ou comentário soma pontos nas tags daquele conteúdo.

**Onde ver:** `api/feed.php`

### 2. Botão Conexão
Um clique conecta o artista a outro que está no momento criativo **complementar**
— não igual. Quem está *bloqueado* precisa de quem está *em fluxo*; quem quer
*colaborar* precisa de outro querendo o mesmo.

A escolha considera complementaridade de estado, mesma área, tags em comum,
presença recente e um amortecedor que evita repetir a mesma pessoa por 7 dias.
Ao confirmar, a DM abre sozinha com uma mensagem quebra-gelo.

**Onde ver:** `api/conexao.php`

### 3. Fóruns Artísticos
Oito espaços temáticos (Bloqueio Criativo, Produção Musical, Artes Visuais,
Design & UI, Escrita, Colaborações, Feedback, Geral) com tópicos, respostas e
notificação para o autor.

**Onde ver:** `api/forum.php`

---

## Funcionalidades

- **Autenticação** — cadastro em 3 etapas, login por @ ou e-mail, hash bcrypt,
  regeneração de sessão, logout completo
- **Publicações** — texto, imagem, vídeo, áudio e PDF; `#hashtags` viram tags reais
- **Player de áudio** com forma de onda navegável (clique para pular)
- **Curtidas, comentários e @menções** — todos gerando notificação
- **Seguidores** com contadores reais
- **Notificações reais** vindas do banco, com contador de não lidas
- **Direct** com prévia, contagem de não lidas, marcação de lida e separador por dia
- **Explorar** — tags em alta, artistas sugeridos e mosaico
- **Busca global** — artistas, posts e tags ao mesmo tempo
- **Perfil** — abas de publicações, curtidas e salvos; pastas de referência
- **Estado criativo** — cinco estados que alimentam o Botão Conexão
- **Spark Pro** — página de planos (receita 1 do modelo de negócio)
- **Tema claro e escuro**, layout responsivo e atalhos (`/` busca, `C` conexão)
- **Mostrar/ocultar senha** com medidor de força, contador de caracteres nos
  textos longos, lupa nas imagens e botão de voltar ao topo

---

## Identidade visual

### Paleta

Três cores, e tudo deriva delas:

| Cor | Hex | Papel |
|---|---|---|
| Grafite | `#121214` | base — fundo no tema escuro, tinta no claro |
| Osso | `#dbd9cf` | texto e acento quente — tinta no escuro, papel no claro |
| Violeta | `#7f00ff` | a marca |

O tema claro **inverte** os dois primeiros em vez de trocar de paleta: o osso
vira papel, o grafite vira tinta. Continuam sendo as mesmas três cores.

Os degraus (`--fg-dim`, `--fg-mute`, `--surface-*`) são calibrados por contraste,
não por gosto — todo texto medido passa em WCAG AA nos dois temas. O violeta puro
nunca é usado como texto: dá 2,98:1 sobre o grafite. Para texto existem
`--spark-txt` (`#a64dff` no escuro, `#5200a4` no claro) e `--ember-txt`.

### A marca

O raio é um "S" construído só com ângulos retos e diagonais de 45°, para ler como
letra e como raio ao mesmo tempo. Vive em `logo_marca()` no `config/config.php`,
usa `currentColor` e serve o trilho, o login, a página Pro, a cortina e o favicon —
um desenho só, um lugar só para mudar.

A assinatura é o raio no lugar do S, seguido de **PARK**. A inclinação vem de um
`skewX(-9deg)` no CSS, não de uma fonte itálica: assim os cortes ficam retos e o
desenho continua geométrico.

### Movimento

`assets/js/movimento.js` traz três coisas:

- **Rolagem suave** com [Lenis](https://github.com/darkroomengineering/lenis), via
  importmap ES. As áreas que têm rolagem própria (Direct, modais, resultados de
  busca) são excluídas para não brigar com ela.
- **Cortina de abertura** — grafite cheio com a assinatura e uma barra de
  progresso; sai deslizando para cima em 850 ms e destrava a rolagem.
- **Revelação por máscara** — títulos são fatiados em palavras, cada uma dentro de
  uma caixa que corta o transbordo, e entram deslizando de baixo em cascata.

Se o CDN do Lenis não responder, tudo continua funcionando — só volta a rolagem
nativa. E há duas redes de segurança que revelam qualquer texto que tenha ficado
escondido, para nada sumir da tela.

---

## Vidro — "Liquid Glass"

A interface imita o vidro real empilhando camadas:

1. **Refração** — `backdrop-filter: blur() saturate()` distorce o que está atrás
2. **Corpo** — gradiente translúcido, mais claro no topo, como vidro grosso
3. **Anel especular** — a borda é um gradiente recortado por `mask-composite`,
   brilhante no canto superior e discreto embaixo
4. **Luz do cursor** — brilho radial que segue o ponteiro dentro do painel,
   via `--mx` / `--my`

Nada disso aparece sem algo para refratar: o fundo tem uma **aurora** de três
manchas que se movem lentamente, mais um granulado que tira o aspecto plástico
dos gradientes.

### O vidro tem dois níveis — e isso é o que mantém tudo fluido

`backdrop-filter` é caríssimo: cada elemento que o usa vira uma camada de
composição e re-borra tudo que está atrás, a cada quadro. Aninhar dois é pior.
Aplicá-lo em todo post, cartão, comentário e balão trava o navegador — foi o
erro da primeira versão.

A Apple não faz assim: no Liquid Glass o vidro é a **moldura**, e o conteúdo
repousa sobre ela. Aqui seguimos a mesma regra:

| Nível | Quem | Técnica |
|---|---|---|
| **Cromo** | barra lateral, topo, modais, popovers, painéis do Direct | `backdrop-filter` de verdade |
| **Carta** | posts, cartões, notificações, tópicos | gradiente translúcido + anel especular + sombra |

O cartão não borra o fundo, mas a aurora atravessa sua transparência e o tinge —
o olho lê como vidro fosco, a um décimo do custo. Com 5 posts na tela são
**7 elementos** com `backdrop-filter` em vez de mais de 60.

Outras regras de custo:

- Em laço, só `transform` e `opacity` são animados. Nada de animar `box-shadow`,
  `filter` ou `background-position` para sempre.
- A aurora usa gradiente radial (já nasce suave) em vez de `filter: blur`, e
  anima só `transform` — o compositor resolve sem repintar.
- O halo dos estados criativos existe só no seletor do topo e nas pílulas do
  modal, nunca nos avatares do feed (seriam dezenas de animações simultâneas).
- Em telas pequenas o raio do blur cai e a terceira mancha da aurora some.
- `prefers-reduced-motion` desliga tudo, aurora inclusive.

**Onde ver:** `assets/css/app.css` e `assets/js/vidro.js`

---

## O visualizador de áudio

Post de áudio ganha uma fita de ondas que reage ao som de verdade — sinal vindo
da Web Audio API (`AnalyserNode`, domínio do tempo), não uma animação decorativa.

**Por que canvas e não elementos no DOM:** uma faixa com 48 barrinhas seria 48
nós recebendo `transform` a cada quadro — quase 3.000 mutações de estilo por
segundo, por faixa. Um canvas é uma superfície só: o traçado inteiro sai numa
chamada e o navegador nunca recalcula layout.

Como é desenhado:

- 9 linhas empilhadas, cada uma com amplitude e opacidade menores e um
  deslocamento de fase — é isso que abre o leque e dá o efeito de rastro
- Curvas suavizadas entre pontos médios (`quadraticCurveTo`), sem bicos
- Cada quadro puxa 30% em direção ao alvo, então a onda flui em vez de tremer
- Brilho barato: um traço largo e translúcido por baixo do fino, em vez de
  `shadowBlur`
- Gradiente âmbar no trecho já tocado, roxo no que falta, com cabeça de leitura
- O nível médio vira a variável CSS `--nivel`, que faz o halo do botão e o
  brilho do fundo pulsarem junto com a música

**Só a faixa que está tocando roda o laço.** As outras desenham a onda de
repouso uma vez e ficam paradas. O laço também para quando a aba sai de vista.

Clique na onda para pular para aquele ponto.

**Onde ver:** `assets/js/audio.js`

---

## Segurança

| Proteção | Como |
|---|---|
| SQL Injection | 100% PDO com prepared statements; nenhuma concatenação de entrada |
| XSS | `htmlspecialchars` no PHP e `esc()` no JavaScript, em toda saída |
| CSRF | Token por sessão exigido em **toda** rota de escrita |
| Fixação de sessão | `session_regenerate_id(true)` no login e no cadastro |
| Upload malicioso | MIME lido do conteúdo com `finfo`, não do cabeçalho do navegador; nome de arquivo gerado pelo servidor |
| Enumeração de contas | Login responde a mesma mensagem para usuário inexistente e senha errada |
| Acesso indevido | Toda rota confere dono/participante antes de ler ou escrever |
| Cookie de sessão | `HttpOnly` + `SameSite=Lax` |

---

## Estrutura

```
spark-app/
├── index.php              Aplicação (shell + estado inicial)
├── login.php              Entrada: login e cadastro em 3 etapas
├── logout.php
├── pro.php                Planos do Spark Pro
│
├── config/
│   ├── config.php         Sessão, banco, CSRF, helpers, notificações
│   └── posts.php          Consultas de post reaproveitadas
│
├── api/                   API JSON
│   ├── feed.php           ← Pilar 1: algoritmo do feed
│   ├── conexao.php        ← Pilar 2: Botão Conexão
│   ├── forum.php          ← Pilar 3: fóruns
│   ├── post.php           Criar / apagar / ver
│   ├── interagir.php      Curtir, salvar, seguir, estado criativo
│   ├── comentarios.php
│   ├── notificacoes.php
│   ├── direct.php         Mensagens privadas
│   ├── explorar.php       Explorar e busca
│   └── perfil.php         Perfil, foto e senha
│
├── assets/
│   ├── css/app.css        Design system Liquid Glass completo
│   └── js/
│       ├── vidro.js       Luz do cursor, ondinhas, senha, lupa, aurora
│       ├── audio.js       Visualizador de onda (Web Audio + canvas)
│       └── app.js         Roteador, renderização e chamadas à API
│
├── database/
│   ├── bd.sql             Schema original
│   └── upgrade.sql        Perfil de artista, conexões, notificações, índices
│
├── midias_upload/         Arquivos publicados
└── profile_pics/          Fotos de perfil
```

---

## Banco de dados

22 tabelas em InnoDB com integridade referencial. As principais:

- `usuario` — inclui `area_criativa`, `ferramenta`, `estado_criativo`, `plano`
- `midias`, `midia_tags`, `tags` — conteúdo e classificação
- `interacoes` + `afinidade_usuario_tag` — o combustível do algoritmo
- `curtidas`, `comentarios`, `seguidores` — o grafo social
- `conexoes` — cada encontro do Botão Conexão
- `conversas`, `conversa_participantes`, `mensagens`, `mensagens_status` — o Direct
- `forum_categorias`, `forum_topicos`, `forum_respostas`
- `notificacoes`

---

## Modelo de negócio

Três receitas que escalam juntas, **sem venda de dados** (a venda de dados
pessoais é vedada pela LGPD, Lei 13.709/2018, e afasta investidores):

1. **Spark Pro** — assinatura de R$ 29,90/mês
2. **Publicidade contextual** — marcas do universo criativo aparecem pelo
   contexto de uso, nunca por perfil pessoal
3. **Marketplace** — comissão sobre colaborações fechadas na plataforma

---

## Problemas comuns

**"Erro ao conectar com o banco de dados"**
O MySQL está desligado. Abra o XAMPP Control Panel e clique em *Start* no MySQL.

**O MySQL não liga (fica verde e volta a apagar)**
As tabelas de privilégio do próprio MySQL podem estar corrompidas. Solução:
renomeie `C:\xampp\mysql\data` para `data_old`, copie `C:\xampp\mysql\backup`
para `data`, e devolva para dentro da nova `data` as pastas dos seus bancos
(`spark`, etc.) mais o arquivo `ibdata1` vindos de `data_old`.

**Porta 80 ocupada**
Normalmente é o Skype ou o IIS. Feche o programa ou mude a porta do Apache no
`httpd.conf`.
