# Plano de Implementação — Webmail

## Decisões Definidas

| Item | Decisão |
|------|---------|
| Multi-domínio | Sim, todos os domínios do Modoboa |
| Customização | Cada domínio tem admin que configura cores, logo, etc. |
| Editor | TipTap (rico, MIT license, Vue 3 nativo) |
| Idioma | Português (pt-BR) inicial, preparado para i18n |

---

## Ambientes

| Ambiente | Diretório | URL | Banco de Dados |
|----------|-----------|-----|----------------|
| **DEV** | `/var/www/dev/webmail` | `webmail-dev.scriptorium.net.br` | `webmail_dev` |
| **PRD** | `/var/www/prd/webmail` | `webmail.scriptorium.net.br` | `webmail_prd` |

### Credenciais de Banco (MariaDB)

| Ambiente | Database | Usuário | Observação |
|----------|----------|---------|------------|
| DEV | `webmail_dev` | `webmail_dev` | Desenvolvimento e testes |
| PRD | `webmail_prd` | `webmail_prd` | Produção |

### Fluxo de Deploy

```
DEV (desenvolvimento) → Testes → PRD (produção)
```

---

## Stack Definida

| Camada | Tecnologia |
|--------|------------|
| Backend | Laravel 11 + PHP 8.3 |
| Frontend | Vue 3 + Inertia.js + Tailwind CSS |
| Editor | TipTap (ProseMirror) |
| IMAP | webklex/laravel-imap |
| SMTP | Laravel Mail (Symfony Mailer) |
| Banco de Dados | MariaDB (customizações por domínio) |
| Cache | Redis |
| Servidor | Nginx + PHP-FPM 8.3 |

---

## Arquitetura Multi-Tenant

```
              webmail-dev.scriptorium.net.br (DEV)
              webmail.scriptorium.net.br (PRD)
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                     NAVEGADOR                               │
│                   Vue 3 + Tailwind                          │
│         (tema/cores carregados do domínio do usuário)       │
└──────────────────────┬──────────────────────────────────────┘
                       │ Inertia.js (HTTP)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL 11                               │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │ AuthControl │ │ MailControl │ │ AdminControl│           │
│  └──────┬──────┘ └──────┬──────┘ └──────┬──────┘           │
│         │               │               │                   │
│  ┌──────▼───────────────▼───────────────▼──────┐           │
│  │              Services Layer                  │           │
│  │  ImapService │ DomainService │ BrandingService│          │
│  └──────────────────────┬───────────────────────┘           │
└─────────────────────────┼───────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│   Dovecot    │  │   Postfix    │  │   MariaDB    │
│  IMAP :993   │  │  SMTP :587   │  │  Branding    │
└──────────────┘  └──────────────┘  └──────────────┘
```

### Fluxo de Identificação do Domínio

```
1. Usuário acessa: webmail.exemplo.com
2. Faz login com: usuario@empresa.com.br
3. Sistema extrai domínio: empresa.com.br
4. Carrega branding do domínio (cores, logo)
5. Aplica tema na interface
```

---

## Modelo de Dados

### Tabelas (MariaDB)

```sql
-- Domínios e suas customizações
CREATE TABLE domains (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,      -- empresa.com.br
    display_name VARCHAR(255),              -- "Empresa S.A."
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Branding por domínio
CREATE TABLE domain_branding (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    domain_id BIGINT UNSIGNED NOT NULL,
    logo_path VARCHAR(255),                 -- /storage/logos/empresa.png
    favicon_path VARCHAR(255),
    primary_color VARCHAR(7) DEFAULT '#3B82F6',    -- Azul
    secondary_color VARCHAR(7) DEFAULT '#1E40AF',
    background_color VARCHAR(7) DEFAULT '#F9FAFB',
    sidebar_color VARCHAR(7) DEFAULT '#FFFFFF',
    custom_css TEXT,                        -- CSS adicional
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Admins de domínio (podem editar branding)
CREATE TABLE domain_admins (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    domain_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,            -- admin@empresa.com.br
    created_at TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE(domain_id, email)
);

-- Assinaturas de usuário
CREATE TABLE user_signatures (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) PRIMARY KEY,         -- usuario@empresa.com.br
    signature_html TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Sessão (Redis)

```php
// Dados do usuário logado
[
    'imap_host' => 'localhost',
    'imap_port' => 993,
    'imap_user' => 'user@empresa.com.br',
    'imap_pass' => encrypt('senha'),
    'domain' => 'empresa.com.br',
    'is_domain_admin' => true,
    'branding' => [
        'logo' => '/storage/logos/empresa.png',
        'primary_color' => '#3B82F6',
        // ...
    ]
]
```

---

## Funcionalidades

### MVP (Fase 1)

| Funcionalidade | Descrição |
|----------------|-----------|
| Autenticação | Login com credenciais IMAP (email + senha) |
| Detecção de domínio | Extrair domínio do email, carregar branding |
| Listar pastas | INBOX, Sent, Drafts, Trash, pastas customizadas |
| Listar mensagens | Paginação, ordenação por data |
| Ler mensagem | HTML/plain text, exibir anexos |
| Compor email | Editor TipTap completo |
| Responder/Encaminhar | Reply, Reply All, Forward |
| Excluir mensagem | Mover para Trash / expunge |
| Marcar como lido/não lido | Flags IMAP |

### Fase 2 — Recursos Adicionais

| Funcionalidade | Descrição |
|----------------|-----------|
| Busca | Busca por assunto, remetente, corpo |
| Anexos | Upload e download de anexos |
| Gerenciar pastas | Criar, renomear, excluir pastas |
| Contatos | Autocompletar baseado em emails enviados/recebidos |
| Assinatura | Assinatura HTML por usuário (TipTap) |

### Fase 3 — Admin de Domínio

| Funcionalidade | Descrição |
|----------------|-----------|
| Painel Admin | Acessível apenas para admins do domínio |
| Upload de Logo | Logo e favicon customizados |
| Cores | Primary, secondary, background, sidebar |
| CSS customizado | Ajustes finos de estilo |
| Preview | Visualizar mudanças antes de salvar |

### Fase 4 — Polimento

| Funcionalidade | Descrição |
|----------------|-----------|
| IMAP IDLE | Notificações push de novos emails |
| Dark mode | Tema escuro (configurável por domínio) |
| Multi-conta | Gerenciar múltiplas contas de email |

---

## Estrutura do Projeto

```
webmail/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── MailController.php
│   │   │   ├── FolderController.php
│   │   │   ├── AttachmentController.php
│   │   │   └── Admin/
│   │   │       └── BrandingController.php
│   │   ├── Middleware/
│   │   │   ├── ValidateImapSession.php
│   │   │   ├── LoadDomainBranding.php
│   │   │   └── DomainAdminOnly.php
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── ComposeMailRequest.php
│   │       └── BrandingRequest.php
│   │
│   ├── Models/
│   │   ├── Domain.php
│   │   ├── DomainBranding.php
│   │   ├── DomainAdmin.php
│   │   └── UserSignature.php
│   │
│   ├── Services/
│   │   ├── ImapService.php
│   │   ├── SmtpService.php
│   │   ├── DomainService.php
│   │   ├── BrandingService.php
│   │   ├── MessageParserService.php
│   │   └── CacheService.php
│   │
│   └── DTOs/
│       ├── MailMessageDTO.php
│       ├── FolderDTO.php
│       ├── AttachmentDTO.php
│       └── BrandingDTO.php
│
├── resources/
│   ├── js/
│   │   ├── app.js
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   │   └── Login.vue
│   │   │   ├── Mail/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Inbox.vue
│   │   │   │   ├── Message.vue
│   │   │   │   └── Compose.vue
│   │   │   ├── Settings/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Signature.vue
│   │   │   └── Admin/
│   │   │       ├── Branding.vue
│   │   │       └── Preview.vue
│   │   ├── Components/
│   │   │   ├── FolderTree.vue
│   │   │   ├── MessageList.vue
│   │   │   ├── MessageView.vue
│   │   │   ├── ComposeModal.vue
│   │   │   ├── AttachmentList.vue
│   │   │   └── Editor/
│   │   │       ├── TipTapEditor.vue
│   │   │       └── EditorToolbar.vue
│   │   ├── Composables/
│   │   │   ├── useBranding.js
│   │   │   └── useEditor.js
│   │   └── Layouts/
│   │       └── MailLayout.vue
│   │
│   └── css/
│       └── app.css
│
├── routes/
│   └── web.php
│
├── config/
│   └── imap.php
│
├── database/
│   └── migrations/
│       ├── create_domains_table.php
│       ├── create_domain_branding_table.php
│       ├── create_domain_admins_table.php
│       └── create_user_signatures_table.php
│
├── storage/
│   └── app/public/
│       └── logos/              # Logos dos domínios
│
└── docs/
    ├── PLANO_IMPLEMENTACAO.md
    ├── MODOBOA_BRANDING.md
    └── modoboa-instalacao.md
```

---

## Rotas

### Autenticação

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/login` | Tela de login |
| POST | `/login` | Autenticar via IMAP |
| POST | `/logout` | Encerrar sessão |

### Pastas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/folders` | Listar todas as pastas |
| POST | `/folders` | Criar pasta |
| PUT | `/folders/{name}` | Renomear pasta |
| DELETE | `/folders/{name}` | Excluir pasta |

### Mensagens

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/mail/{folder}` | Listar mensagens da pasta |
| GET | `/mail/{folder}/{uid}` | Ler mensagem específica |
| POST | `/mail/send` | Enviar email |
| PUT | `/mail/{folder}/{uid}/flags` | Marcar lido/não lido/starred |
| DELETE | `/mail/{folder}/{uid}` | Excluir mensagem |
| POST | `/mail/{folder}/{uid}/move` | Mover para outra pasta |

### Anexos

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/attachment/{folder}/{uid}/{part}` | Download de anexo |
| POST | `/attachment/upload` | Upload temporário |

### Configurações do Usuário

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/settings` | Página de configurações |
| GET | `/settings/signature` | Editar assinatura |
| PUT | `/settings/signature` | Salvar assinatura |

### Admin de Domínio

| Método | Rota | Middleware | Descrição |
|--------|------|------------|-----------|
| GET | `/admin/branding` | DomainAdminOnly | Painel de branding |
| PUT | `/admin/branding` | DomainAdminOnly | Salvar branding |
| POST | `/admin/branding/logo` | DomainAdminOnly | Upload de logo |
| GET | `/admin/branding/preview` | DomainAdminOnly | Preview do tema |

---

## Editor TipTap

### Extensões a instalar

```json
{
    "dependencies": {
        "@tiptap/vue-3": "^2.2",
        "@tiptap/starter-kit": "^2.2",
        "@tiptap/extension-color": "^2.2",
        "@tiptap/extension-text-style": "^2.2",
        "@tiptap/extension-highlight": "^2.2",
        "@tiptap/extension-link": "^2.2",
        "@tiptap/extension-image": "^2.2",
        "@tiptap/extension-table": "^2.2",
        "@tiptap/extension-table-row": "^2.2",
        "@tiptap/extension-table-cell": "^2.2",
        "@tiptap/extension-table-header": "^2.2",
        "@tiptap/extension-text-align": "^2.2",
        "@tiptap/extension-underline": "^2.2",
        "@tiptap/extension-placeholder": "^2.2"
    }
}
```

### Funcionalidades do Editor

| Recurso | Extensão |
|---------|----------|
| Negrito, Itálico, Sublinhado | starter-kit + underline |
| Cores de texto | color + text-style |
| Marcador (highlight) | highlight |
| Links | link |
| Imagens inline | image |
| Tabelas | table + row + cell + header |
| Alinhamento | text-align |
| Listas | starter-kit |
| Citação | starter-kit |

---

## Dependências

### Backend (composer.json)

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "inertiajs/inertia-laravel": "^1.0",
        "webklex/laravel-imap": "^6.0",
        "ezyang/htmlpurifier": "^4.17",
        "intervention/image": "^3.0"
    }
}
```

### Frontend (package.json)

```json
{
    "dependencies": {
        "vue": "^3.4",
        "@inertiajs/vue3": "^1.0",
        "@vueuse/core": "^10.0",
        "@tiptap/vue-3": "^2.2",
        "@tiptap/starter-kit": "^2.2",
        "@tiptap/extension-color": "^2.2",
        "@tiptap/extension-text-style": "^2.2",
        "@tiptap/extension-highlight": "^2.2",
        "@tiptap/extension-link": "^2.2",
        "@tiptap/extension-image": "^2.2",
        "@tiptap/extension-table": "^2.2",
        "@tiptap/extension-table-row": "^2.2",
        "@tiptap/extension-table-cell": "^2.2",
        "@tiptap/extension-table-header": "^2.2",
        "@tiptap/extension-text-align": "^2.2",
        "@tiptap/extension-underline": "^2.2",
        "@tiptap/extension-placeholder": "^2.2"
    },
    "devDependencies": {
        "tailwindcss": "^3.4",
        "@tailwindcss/typography": "^0.5",
        "vite": "^5.0",
        "@vitejs/plugin-vue": "^5.0"
    }
}
```

---

## Internacionalização (i18n)

Sistema preparado para múltiplos idiomas. Inicialmente apenas pt-BR.

### Estrutura

```
lang/
├── pt_BR/
│   ├── auth.php          # Login, logout, erros de autenticação
│   ├── mail.php          # Inbox, compose, folders, etc.
│   ├── admin.php         # Painel de branding
│   └── validation.php    # Mensagens de validação
│
└── en/                   # (futuro) Basta criar e traduzir
    ├── auth.php
    ├── mail.php
    ├── admin.php
    └── validation.php

resources/js/
└── lang/
    ├── pt_BR.json        # Traduções do frontend
    └── en.json           # (futuro)
```

### Uso no código

**Backend (Laravel):**
```php
// Controller
return __('mail.message_sent');

// Blade/Inertia
{{ __('mail.inbox') }}
```

**Frontend (Vue):**
```vue
<template>
  <span>{{ $t('mail.compose') }}</span>
</template>
```

### Adicionando novo idioma

1. Criar pasta `lang/en/` com arquivos traduzidos
2. Criar `resources/js/lang/en.json`
3. Adicionar idioma na configuração
4. Pronto — sistema detecta automaticamente

---

## Segurança

| Item | Implementação |
|------|---------------|
| Credenciais IMAP | Criptografadas na sessão com `encrypt()` |
| CSRF | Middleware padrão do Laravel |
| XSS | Vue escapa por padrão, HTMLPurifier para emails |
| Session Fixation | Regenerar session ID no login |
| Rate Limiting | Throttle no login (5 tentativas/minuto) |
| HTTPS | Obrigatório em produção |
| Upload de Logo | Validar tipo MIME, redimensionar com Intervention |
| Admin Access | Verificar se email está em domain_admins |

---

## Fases de Desenvolvimento

### Fase 1 — Fundação (MVP)

1. ✅ Setup do projeto Laravel + Vue + Inertia + Tailwind
2. ✅ Migrations do banco de dados
3. ✅ Autenticação IMAP + detecção de domínio
4. ✅ Middleware de branding (carregar tema do domínio)
5. ⏳ Listagem de pastas
6. ⏳ Listagem de mensagens
7. ⏳ Visualização de mensagem (HTML sanitizado)
8. ⏳ Editor TipTap básico
9. ⏳ Composição e envio de email
10. ⏳ Responder/Encaminhar
11. ⏳ Ações básicas (marcar lido, excluir)

### Fase 2 — Recursos Adicionais

12. Anexos (download e upload)
13. Busca IMAP
14. Gerenciamento de pastas
15. Assinatura HTML por usuário

### Fase 3 — Admin de Domínio

16. ✅ Painel de branding
17. ✅ Upload de logo/favicon
18. ✅ Configuração de cores
19. ✅ CSS customizado
20. ✅ Preview em tempo real

### Fase 4 — Polimento

21. IMAP IDLE (WebSocket)
22. Dark mode por domínio
23. Cache agressivo
24. Otimizações de performance

---

## Próximos Passos

1. ✅ Plano aprovado
2. ✅ Setup do projeto Laravel 12
3. ✅ Configurar Vue 3 + Inertia.js + Tailwind CSS v4
4. ✅ Instalar dependências (laravel-imap, htmlpurifier, intervention/image)
5. ✅ Criar migrations (domains, domain_branding, domain_admins, user_signatures)
6. ✅ Configurar Nginx (webmail-dev.scriptorium.net.br)
7. ✅ Criar Models (Domain, DomainBranding, DomainAdmin, UserSignature)
8. ✅ Implementar autenticação IMAP (ImapAuthService)
9. ✅ Criar AuthController com login/logout
10. ✅ Criar middleware de autenticação (imap.auth)
11. ⏳ Configurar DNS para webmail-dev.scriptorium.net.br
12. ⏳ Implementar listagem de pastas
13. ⏳ Implementar listagem de mensagens
14. ⏳ Implementar visualização de mensagem

---

## Credenciais (DEV)

| Item | Valor |
|------|-------|
| Database | `webmail_dev` |
| User | `webmail_dev` |
| Password | `6On4NVLYN2HwSmMWeVfUg8ur` |

---

---

## Fase 3 — Admin de Domínio (Implementação)

### Arquivos Criados

| Arquivo | Descrição |
|---------|-----------|
| `app/Http/Middleware/DomainAdminOnly.php` | Middleware que verifica se o usuário é admin do domínio |
| `app/Http/Requests/BrandingRequest.php` | Form Request com validação de cores e sanitização de CSS |
| `app/Http/Controllers/Admin/BrandingController.php` | Controller para gerenciar branding do domínio |
| `app/Services/ModoboaAdminService.php` | Serviço que consulta o banco do Modoboa para verificar admins |
| `resources/js/Pages/Admin/Branding.vue` | Página Vue para configuração de branding |

### Arquivos Modificados

| Arquivo | Modificação |
|---------|-------------|
| `routes/web.php` | Adicionadas rotas de admin |
| `bootstrap/app.php` | Registrado middleware `domain.admin` |
| `config/database.php` | Adicionada conexão `modoboa` (PostgreSQL read-only) |
| `.env` | Credenciais do banco Modoboa |
| `app/Http/Controllers/AuthController.php` | Usa ModoboaAdminService em vez de tabela local |
| `app/Models/Domain.php` | Removidos `isAdmin()` e `admins()` (dados vêm do Modoboa) |
| `resources/js/Pages/Mail/Inbox.vue` | Adicionado ícone de configurações para admins |
| `resources/js/Pages/Mail/Message.vue` | Adicionado ícone de configurações para admins |

### Rotas de Admin

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/admin/branding` | Página de configuração de branding |
| PUT | `/admin/branding` | Salvar cores e CSS |
| POST | `/admin/branding/logo` | Upload de logo |
| POST | `/admin/branding/favicon` | Upload de favicon |
| DELETE | `/admin/branding/logo` | Remover logo |
| DELETE | `/admin/branding/favicon` | Remover favicon |

### Funcionalidades Implementadas

1. **Upload de Logo**
   - Formatos: PNG, JPG, SVG, WebP
   - Tamanho máximo: 2MB
   - Redimensionamento automático para 400px de largura (exceto SVG)
   - Conversão para PNG

2. **Upload de Favicon**
   - Formatos: ICO, PNG
   - Tamanho máximo: 512KB

3. **Configuração de Cores**
   - Cor primária (botões, links, destaques)
   - Cor secundária (hover, elementos secundários)
   - Cor de fundo (background geral)
   - Cor da barra lateral

4. **CSS Customizado**
   - Máximo 10.000 caracteres
   - Sanitização automática (remove @import, URLs externas, expression(), javascript:, behavior, -moz-binding)

5. **Preview em Tempo Real**
   - Mostra como o webmail ficará com as cores selecionadas
   - Atualiza instantaneamente ao alterar qualquer cor

6. **Acesso Condicional**
   - Ícone de configurações (engrenagem) só aparece para admins do domínio
   - Middleware protege todas as rotas de admin

### Integração com Modoboa

A verificação de admin de domínio é feita diretamente no banco PostgreSQL do Modoboa (read-only).

**Conexão:** `config/database.php` → conexão `modoboa`

**Lógica (`ModoboaAdminService`):**
1. SuperAdmin (`core_user.is_superuser = true`) → admin de todos os domínios
2. DomainAdmin (grupo `DomainAdmins` em `auth_group`) com `core_objectaccess` ao domínio

**Tabela `domain_admins` do webmail:** Não é mais utilizada. Os dados de admin vêm exclusivamente do Modoboa.

### Storage

- Logos armazenados em: `storage/app/public/logos/`
- Favicons armazenados em: `storage/app/public/favicons/`
- Link simbólico: `public/storage → storage/app/public`

---

## Referências

- [TipTap Editor](https://tiptap.dev/docs/editor/getting-started/overview)
- [webklex/laravel-imap](https://github.com/Webklex/laravel-imap)
- [Inertia.js](https://inertiajs.com/)
- [Tailwind CSS](https://tailwindcss.com/)
