# Webmail Project

## Diretrizes de desenvolvimento

- Segurança deve ser sempre prioridade.
- Antes de executar qualquer coisa que não seja trivial, declare explicitamente suas suposições.
    Formato:
    ```
    SUPOSIÇÕES QUE ESTOU FAZENDO:
    1. [suposição]
    2. [suposição]
    → Corrija-me agora ou prosseguirei com estas.
    ```
    Nunca preencha requisitos ambíguos sem justificativa. O modo de falha mais comum é fazer suposições erradas e prosseguir com elas sem verificação. Exponha a incerteza desde o início.

- Ao se deparar com inconsistências, requisitos conflitantes ou especificações pouco claras:
    1. PARE. Não prossiga com base em palpites.
    2. Nomeie a confusão específica.
    3. Apresente a alternativa ou faça a pergunta esclarecedora.
    4. Aguarde a resolução antes de continuar.
- Você não é uma máquina de dizer sim. Quando a abordagem da pessoa apresentar problemas claros:
    - Aponte o problema diretamente
    - Explique a desvantagem concreta
    - Proponha uma alternativa
    - Aceite a decisão dela, caso ela a rejeite
    Bajulação é um erro grave. Dizer "Claro!" seguido de uma má ideia não ajuda ninguém.

- Toque apenas no que for solicitado.

    NÃO:
    - Remova comentários que você não entende
    - "Limpe" código que não tem relação com a tarefa
    - Refatore sistemas adjacentes como efeitos colaterais
    - Exclua código que pareça não ser usado sem aprovação explícita
    Seu trabalho exige precisão cirúrgica, não reformas não solicitadas.

- Após refatorar ou implementar alterações:
    - Identifique o código que agora está inacessível
    - Liste-o explicitamente
    - Pergunte: "Devo remover estes elementos agora não utilizados: [lista]?"
    Não deixe código morto. Não exclua sem perguntar.

- Sem abstrações desnecessárias
- Sem generalizações prematuras
- Sem truques engenhosos sem comentários explicando o porquê
- Estilo consistente com a base de código existente
- Nomes de variáveis ​​significativos (sem `temp`, `data`, `result` sem contexto)

- Seja direto sobre os problemas
- Quantifique sempre que possível ("isso adiciona ~200ms de latência", não "isso pode ser mais lento")
- Quando estiver com dificuldades, diga isso e descreva o que você já tentou
- Não esconda a incerteza por trás de uma linguagem confiante

O humano está monitorando você em uma IDE. Ele pode ver tudo. Ele detectará seus erros. Seu trabalho é minimizar os erros que ele precisa detectar, enquanto maximiza o trabalho útil que você produz.

Você tem energia ilimitada. O humano não. Use sua persistência com sabedoria — concentre-se em problemas difíceis, mas não se concentre no problema errado por não ter definido claramente o objetivo.

## Ambiente do Servidor

### Sistema Operacional
- **OS**: Ubuntu 24.04.3 LTS (Noble Numbat)
- **Kernel**: 6.8.0-94-generic
- **Arquitetura**: x86_64

### Hardware
- **CPU**: 2 cores
- **Memória RAM**: 7.8 GB (5.2 GB em uso)
- **Swap**: 2.0 GB
- **Disco**: 96 GB (21 GB usado, 76 GB disponível)

---

## Stack de Desenvolvimento

### PHP
- **Versão**: PHP 8.3.30 (CLI)
- **Engine**: Zend Engine v4.3.30
- **OPcache**: Habilitado
- **FPM**: php8.3-fpm (ativo)
- **Módulos principais**:
  - Banco de dados: mysqli, pdo_mysql, pdo_pgsql, pgsql, pdo_sqlite, sqlite3
  - Cache: redis, igbinary
  - Web: curl, json, soap, sockets
  - Processamento: gd, mbstring, intl, bcmath
  - Segurança: openssl, sodium

### Composer
- **Versão**: 2.9.4

### Node.js
- **Node**: v24.13.0
- **NPM**: 11.6.2

### Python
- **Versão**: Python 3.12.3
- **PIP**: 24.0

### Git
- **Versão**: 2.43.0

---

## Bancos de Dados

### MariaDB
- **Versão**: 10.11.14
- **Status**: Ativo (mariadb.service)

### PostgreSQL
- **Versão**: 18.1
- **Cluster**: 18-main (ativo)

### Redis
- **Versão**: 7.0.15

---

## Servidor Web

### Nginx
- **Versão**: 1.29.4
- **Status**: Ativo
- **Sites habilitados**:
  - academico-dev / academico-prd
  - bingoplay-dev / bingoplay-prd
  - siscar-dev / siscar-prd
  - mail.bingoplay.app.br
  - mail.ista.com.br
  - mail.siscar.app.br
  - autoconfig.scriptorium.net.br
  - vps1.scriptorium.net.br

---

## Servidor de Email (Modoboa)

### Modoboa
- **Versão**: 2.6.5
- **Localização**: /srv/modoboa
- **Virtualenv**: /srv/modoboa/env
- **Instância**: /srv/modoboa/instance
- **Usuário**: modoboa
- **WSGI**: uWSGI (ativo)

### Componentes de Email
- **Postfix**: MTA (Mail Transport Agent) - ativo
- **Dovecot**: IMAP/POP3 server - ativo

---

## Serviços Ativos

| Serviço | Descrição | Status |
|---------|-----------|--------|
| nginx | Servidor Web | Ativo |
| php8.3-fpm | PHP FastCGI | Ativo |
| mariadb | Banco de dados | Ativo |
| postgresql@18-main | Banco de dados | Ativo |
| postfix | Servidor SMTP | Ativo |
| dovecot | Servidor IMAP/POP3 | Ativo |
| uwsgi | Python WSGI Server | Ativo |

---

## Projeto Webmail

### Status Atual
**Fase 1 — MVP:** Concluído (listagem de pastas, mensagens, visualização)
**Fase 3 — Admin:** Concluído (painel de branding)

### Stack do Projeto

| Componente | Tecnologia | Versão |
|------------|------------|--------|
| Backend | Laravel | 12.x |
| Frontend | Vue 3 + Inertia.js | 3.4 / 2.0 |
| CSS | Tailwind CSS | 4.x |
| IMAP | webklex/laravel-imap | 6.2 |
| Sanitização | HTMLPurifier | 4.19 |
| Imagens | Intervention/Image | 3.11 |

### Ambientes

| Ambiente | URL | Diretório |
|----------|-----|-----------|
| DEV | `webmail-dev.scriptorium.net.br` | `/var/www/dev/webmail` |
| PRD | `webmail.scriptorium.net.br` | `/var/www/prd/webmail` |

### Banco de Dados (DEV)

```
Database: webmail_dev
User: webmail_dev
Password: 6On4NVLYN2HwSmMWeVfUg8ur
```

### Tabelas Criadas

- `domains` — Domínios de email
- `domain_branding` — Customização visual por domínio
- `domain_admins` — (legada, não utilizada - admins vêm do Modoboa)
- `user_signatures` — Assinaturas de email por usuário

### Arquivos Importantes

**Controllers:**
- `app/Http/Controllers/AuthController.php` — Controller de autenticação
- `app/Http/Controllers/MailController.php` — Controller de email (pastas, mensagens)
- `app/Http/Controllers/Admin/BrandingController.php` — Controller de branding do domínio

**Services:**
- `app/Services/ImapAuthService.php` — Serviço de autenticação IMAP
- `app/Services/ImapService.php` — Serviço de operações IMAP
- `app/Services/ModoboaAdminService.php` — Consulta admins de domínio no banco do Modoboa

**Middlewares:**
- `app/Http/Middleware/EnsureImapAuthenticated.php` — Verifica autenticação IMAP
- `app/Http/Middleware/DomainAdminOnly.php` — Verifica se é admin do domínio

**Páginas Vue:**
- `resources/js/Pages/Auth/Login.vue` — Página de login
- `resources/js/Pages/Mail/Inbox.vue` — Página de caixa de entrada
- `resources/js/Pages/Mail/Message.vue` — Visualização de mensagem
- `resources/js/Pages/Admin/Branding.vue` — Painel de customização do domínio

**Componentes Vue:**
- `resources/js/Components/FolderList.vue` — Lista de pastas
- `resources/js/Components/MessageList.vue` — Lista de mensagens
- `resources/js/Components/Pagination.vue` — Paginação

**Configuração:**
- `config/imap.php` — Configuração do IMAP
- `resources/views/app.blade.php` — Layout Inertia

### Implementado

**Fase 1 — MVP:**
- [x] Models: Domain, DomainBranding, UserSignature
- [x] Serviço de autenticação IMAP (ImapAuthService)
- [x] Serviço de operações IMAP (ImapService)
- [x] AuthController com login/logout
- [x] MailController com listagem e visualização
- [x] Middleware de autenticação (imap.auth)
- [x] Página de Login (Vue)
- [x] Página de Inbox com paginação (Vue)
- [x] Página de visualização de mensagem (Vue)
- [x] Listagem de pastas IMAP
- [x] Listagem de mensagens com paginação
- [x] Visualização de mensagem (HTML sanitizado)
- [x] Marcar como lido/não lido
- [x] Mover mensagem para outra pasta
- [x] Excluir mensagem
- [x] Rate limiting no login (5 tentativas/minuto)
- [x] Session fixation protection

**Fase 3 — Admin de Domínio:**
- [x] Middleware DomainAdminOnly
- [x] BrandingController (CRUD completo)
- [x] Página de branding com preview em tempo real
- [x] Upload de logo (PNG, JPG, SVG, WebP até 2MB)
- [x] Upload de favicon (ICO, PNG até 512KB)
- [x] Configuração de cores (primária, secundária, fundo, sidebar)
- [x] CSS customizado com sanitização
- [x] Ícone de configurações no header para admins
- [x] Integração com Modoboa para verificar admins (conexão PostgreSQL read-only)

### Pendências

- [ ] Compor/enviar email (Editor TipTap)
- [ ] Responder/Encaminhar
- [ ] Download de anexos
- [ ] Busca IMAP
- [ ] Gerenciamento de pastas
- [ ] Assinatura HTML por usuário
- [ ] IMAP IDLE (notificações push)
- [ ] Dark mode
