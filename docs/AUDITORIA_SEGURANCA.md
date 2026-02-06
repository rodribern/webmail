# Auditoria de Seguranca — Webmail

**Data:** 2026-02-06
**Escopo:** Aplicacao webmail completa (backend Laravel 12, frontend Vue 3 + Inertia, IMAP/SMTP)
**Metodologia:** Analise estatica de codigo + comparacao com OWASP Top 10 (2021) e melhores praticas 2025-2026

---

## Indice

1. [Resumo Executivo](#resumo-executivo)
2. [O que esta BEM FEITO](#o-que-esta-bem-feito)
3. [Vulnerabilidades Encontradas](#vulnerabilidades-encontradas)
   - [Criticas](#criticas)
   - [Altas](#altas)
   - [Medias](#medias)
   - [Baixas](#baixas)
4. [Plano de Acao Recomendado](#plano-de-acao-recomendado)
5. [Headers de Seguranca Recomendados (Nginx)](#headers-de-seguranca-recomendados-nginx)
6. [CSP Recomendada](#csp-recomendada)
7. [Rate Limiting Recomendado](#rate-limiting-recomendado)
8. [Referencias](#referencias)

---

## Resumo Executivo

A aplicacao possui uma **fundacao de seguranca solida**: CSRF, criptografia de senha na sessao, rate limiting no login e envio, sessao em banco de dados, cookies HttpOnly/SameSite. Os problemas encontrados sao **corrigiveis sem refatoracao arquitetural**.

**3 vulnerabilidades criticas** foram identificadas e **todas corrigidas em 2026-02-06**:
1. ~~SVG sem sanitizacao (XSS persistente via logo ou anexo)~~ → **CORRIGIDO**
2. ~~Headers de seguranca: Permissions-Policy ausente~~ → **CORRIGIDO** (demais headers ja existiam)
3. ~~Trust em todos os proxies (bypass de rate limiting)~~ → **CORRIGIDO**

**4 vulnerabilidades altas** — 3 corrigidas, 1 ja estava resolvida:
4. ~~SESSION_SECURE_COOKIE ausente~~ → **CORRIGIDO**
5. ~~SESSION_ENCRYPT desabilitado~~ → **Ja estava resolvido**
6. ~~Mensagens de excecao expostas ao usuario~~ → **CORRIGIDO**
7. ~~Rate limiting apenas por IP no login~~ → **CORRIGIDO**

| Severidade | Total | Corrigidos | Pendentes |
|------------|-------|------------|-----------|
| Critica    | 3     | 3          | 0         |
| Alta       | 4     | 4          | 0         |
| Media      | 8     | 8          | 0         |
| Baixa      | 4     | 0          | 4         |

---

## O que esta BEM FEITO

| Pratica | Arquivo | Linha |
|---------|---------|-------|
| Rate limiting no login (5/min por IP) | `app/Http/Controllers/AuthController.php` | 51-55 |
| Rate limiting no envio SMTP (30/hora) | `app/Services/SmtpService.php` | 133 |
| Regeneracao de sessao no login (anti-fixation) | `app/Http/Controllers/AuthController.php` | 78 |
| Senha criptografada na sessao (`encrypt()` AES-256-CBC) | `app/Http/Controllers/AuthController.php` | 99 |
| CSRF token em todas as chamadas fetch | `resources/views/app.blade.php` + componentes Vue | 6 |
| IMAP via SSL porta 993 | `app/Services/ImapAuthService.php` | 21-22 |
| SMTP via STARTTLS porta 587 | `app/Services/SmtpService.php` | 20 |
| Validacao de nomes de pasta (regex whitelist) | `app/Http/Controllers/MailController.php` | 388 |
| Sessao armazenada em banco de dados (nao file-based) | `config/session.php` | 21 |
| Cookie HttpOnly=true, SameSite=Lax | `config/session.php` | 185, 202 |
| UUID para nomes de anexos temporarios (anti-path traversal) | `app/Http/Controllers/ComposeController.php` | 202 |
| Anexos temporarios em pasta privada por sessao | `app/Http/Controllers/ComposeController.php` | 194-216 |
| Resampling de imagens no upload de logo (remove codigo embutido) | `app/Http/Controllers/Admin/BrandingController.php` | 127 |
| HTMLPurifier para sanitizar assinatura HTML | `app/Http/Controllers/SignatureController.php` | 44-57 |
| Mensagens genericas de erro no login (nao revela se email existe) | `app/Http/Controllers/AuthController.php` | 70 |
| Queries SQL parametrizadas | `app/Http/Controllers/AuthController.php` | 25 |
| Verificacao de admin contra Modoboa (fonte externa PostgreSQL) | `app/Services/ModoboaAdminService.php` | - |
| Credenciais nao aparecem em logs | Verificado via grep no codebase | - |
| Invalidacao de sessao no logout | `app/Http/Controllers/AuthController.php` | 116 |
| Iframe sandbox para exibicao de e-mail HTML (defesa primaria contra XSS) | `resources/js/Pages/Mail/Message.vue` | 528 |
| Sanitizacao regex de scripts como camada complementar ao sandbox | `app/Http/Controllers/MailController.php` | 180-192 |

> **Nota sobre exibicao de e-mails HTML:**
> O conteudo HTML de e-mails e renderizado dentro de um `<iframe sandbox="allow-same-origin">`.
> Esse sandbox (sem `allow-scripts`) bloqueia execucao de JavaScript no nivel do browser/SO —
> e a defesa primaria contra XSS em e-mails. A sanitizacao regex no backend (remocao de `<script>`,
> event handlers, `javascript:` URLs) atua como camada complementar (defense-in-depth).
> HTMLPurifier com whitelist estrita **nao e adequado** para e-mails recebidos porque destroi o layout
> de e-mails profissionais (convites Google Meet, newsletters, etc. perdem botoes, links e formatacao).
> HTMLPurifier e usado corretamente para conteudo que o *usuario cria* (assinatura).

---

## Vulnerabilidades Encontradas

### Criticas

#### CRIT-01: SVG sem sanitizacao (XSS persistente) — **CORRIGIDO**

- **Status:** ~~Critico~~ → **Resolvido em 2026-02-06**
- **OWASP:** A03:2021 — Injection

**Correcoes aplicadas:**
1. `enshrined/svg-sanitize` instalado e aplicado no upload de logo SVG (`BrandingController.php`)
2. `image/svg+xml` removido da lista de tipos inline em `downloadAttachment()` — SVGs agora forcam download
3. Headers `X-Content-Type-Options: nosniff` e `Content-Security-Policy: default-src 'none'` adicionados nas respostas de download de anexo

---

#### CRIT-02: Headers de seguranca — **CORRIGIDO**

- **Status:** ~~Critico~~ → **Resolvido**
- **OWASP:** A05:2021 — Security Misconfiguration

Os Nginx configs ja possuiam a maioria dos headers de seguranca. O unico ausente (`Permissions-Policy`) foi adicionado em 2026-02-06.

**Headers presentes em todos os 5 Nginx configs:**

| Header | Status |
|--------|--------|
| `Strict-Transport-Security` (HSTS) | Ja existia |
| `X-Content-Type-Options: nosniff` | Ja existia |
| `X-Frame-Options: SAMEORIGIN` | Ja existia |
| `X-Permitted-Cross-Domain-Policies: none` | Ja existia |
| `Referrer-Policy: strict-origin-when-cross-origin` | Ja existia |
| `X-XSS-Protection: 1; mode=block` | Ja existia |
| `Permissions-Policy` | **Adicionado em 2026-02-06** |

**Pendente (media prioridade):**
- `Content-Security-Policy` (CSP) — requer testes cuidadosos para nao quebrar o TipTap e Tailwind
- `Cache-Control: no-store` para paginas dinamicas (assets estaticos ja tem cache configurado)

---

#### CRIT-03: Trust em todos os proxies (bypass de rate limiting) — **CORRIGIDO**

- **Status:** ~~Critico~~ → **Resolvido em 2026-02-06**
- **Arquivo:** `bootstrap/app.php` linhas 16-23
- **OWASP:** A05:2021 — Security Misconfiguration

**Correcao aplicada:** `trustProxies` restrito para `['127.0.0.1', '::1']` (apenas Nginx local).

---

### Altas

#### ALT-01: `SESSION_SECURE_COOKIE` possivelmente nao definido — **CORRIGIDO**

- **Status:** ~~Alta~~ → **Resolvido em 2026-02-06**
- **Correcao aplicada:** `SESSION_SECURE_COOKIE=true` adicionado ao `.env` de DEV e PRD.

---

#### ALT-02: Criptografia de sessao desabilitada — **JA ESTAVA RESOLVIDO**

- **Status:** ~~Alta~~ → **Ja resolvido** (verificado em 2026-02-06)
- `SESSION_ENCRYPT=true` ja existia em ambos os `.env` (DEV e PRD).

---

#### ALT-03: Mensagens de excecao vazam para o usuario — **CORRIGIDO**

- **Status:** ~~Alta~~ → **Resolvido em 2026-02-06**

**Correcoes aplicadas:**
- `ImapAuthService.php`: `$e->getMessage()` substituido por mensagem generica + `\Log::error()` com detalhes
- `SmtpService.php`: idem
- `ImapService.php` (`getMessages`): idem
- `MailController.php` (`batchToggleSeen`): idem

---

#### ALT-04: Rate limiting do login apenas por IP — **CORRIGIDO**

- **Status:** ~~Alta~~ → **Resolvido em 2026-02-06**

**Correcao aplicada em `AuthController.php`:**
- Rate limit por IP mantido: 5 tentativas/minuto
- Rate limit por email adicionado: 10 tentativas/5 minutos (protege contra brute force distribuido)
- Ambos os rate limiters sao limpos em caso de login bem-sucedido

---

### ~~Medias~~ — CONCLUIDAS (2026-02-06)

#### MED-01: Sem validacao de tipo de arquivo em anexos — **CORRIGIDO**

- **Correcao aplicada:** Validacao de MIME type adicionada em `ComposeController::uploadAttachment()`. Tipos perigosos (`application/x-httpd-php`, `text/x-php`, `application/x-php`, `application/x-executable`, `application/x-sharedlib`) sao bloqueados.

---

#### MED-02: Sem rate limiting em endpoints de API — **CORRIGIDO**

- **Correcao aplicada em `routes/web.php`:**

| Endpoint | Throttle |
|----------|----------|
| searchMessages | 30/min |
| downloadAttachment | 60/min |
| suggestContacts | 5/min |
| createFolder/renameFolder/deleteFolder | 10/min |
| batch operations (seen/delete/move) | 10/min |
| uploadAttachment | 20/10min |

---

#### MED-03: Status de admin cacheado na sessao — **CORRIGIDO**

- **Correcao aplicada:** `DomainAdminOnly` middleware agora re-valida status de admin contra Modoboa a cada 15 minutos via `last_admin_check` timestamp na sessao.

---

#### MED-04: Sem timeout absoluto de sessao — **CORRIGIDO**

- **Correcao aplicada:**
  - `EnsureImapAuthenticated` middleware verifica `login_at` e invalida sessao apos 8 horas
  - `AuthController` armazena `login_at => now()` no momento do login

---

#### MED-05: Sem limite de destinatarios por e-mail — **CORRIGIDO**

- **Correcao aplicada:** `to`, `cc` e `bcc` agora tem `max:50` na validacao de `ComposeController::send()`.

---

#### MED-06: Sanitizacao de CSS por regex (branding) — **CORRIGIDO**

- **Correcao aplicada em `BrandingRequest.php`:**
  - `html_entity_decode()` aplicado antes dos filtros (previne bypass via encoding)
  - `@font-face` bloqueado (previne carregamento de fontes externas)
  - `data:` URLs bloqueadas
  - Pattern de `javascript:` com espacos intercalados detectado

---

#### MED-07: Caminho de arquivo vazando na resposta de upload — **CORRIGIDO**

- **Correcao aplicada:** Campo `path` removido da resposta JSON de `ComposeController::uploadAttachment()`.

---

#### MED-08: Sem cleanup automatico de anexos temporarios — **CORRIGIDO**

- **Correcao aplicada:** Scheduled task adicionada em `routes/console.php` que roda a cada hora e remove diretorios de anexos temporarios com mais de 24 horas.

**Correcao:** Criar command agendado:
```php
// Em app/Console/Kernel.php ou routes/console.php
Schedule::call(function () {
    $tempDir = storage_path('app/private/temp_attachments');
    foreach (glob("$tempDir/*") as $dir) {
        if (filemtime($dir) < now()->subHours(24)->timestamp) {
            File::deleteDirectory($dir);
        }
    }
})->hourly();
```

---

### Baixas

#### BAI-01: `APP_DEBUG=true` no `.env.example`

Se copiado para producao sem alterar, vazara stack traces, paths internos, e configuracoes.

**Correcao:** Verificar que `.env` de producao tem `APP_DEBUG=false`.

---

#### BAI-02: Sem audit logging de eventos de seguranca

Nao ha log dedicado para:
- Tentativas de login (sucesso e falha)
- Envios de e-mail
- Acoes de admin (branding)
- Hits de rate limit

**Correcao:** Criar canal de log dedicado e registrar eventos de seguranca.

---

#### BAI-03: Riscos residuais na exibicao de e-mail HTML (reclassificado)

- **Arquivos:**
  - `app/Http/Controllers/MailController.php` linhas 180-192 (sanitizacao regex)
  - `resources/js/Pages/Mail/Message.vue` linha 528 (iframe sandbox)
- **Severidade original:** Critica — **Reclassificado para Baixa**
- **Motivo da reclassificacao:** A defesa primaria contra XSS em e-mails e o `<iframe sandbox="allow-same-origin">` (sem `allow-scripts`), que bloqueia execucao de JavaScript no nivel do browser. A sanitizacao regex no backend e uma camada complementar. HTMLPurifier com whitelist estrita destruiria o layout de e-mails profissionais (convites Google Meet, newsletters, etc.), tornando-o inadequado para conteudo recebido.

**Riscos residuais (sem JavaScript, apenas visuais):**
- **Phishing via CSS:** Um e-mail pode usar `position: fixed` para sobrepor a UI do webmail com conteudo falso (sem formulario funcional, mas visualmente enganoso)
- **Tracking pixels:** `<img src="https://tracker.com/pixel.gif">` revela IP do usuario e confirma abertura do e-mail

**Nota:** Esses riscos residuais sao comuns a todos os webmails (Gmail, Outlook inclusos) e nao representam execucao de codigo.

---

#### BAI-04: Fonte Inter carregada do Bunny Fonts (dependencia externa)

- **Arquivo:** `resources/views/app.blade.php`

Recurso externo sem SRI (Subresource Integrity). Se o CDN for comprometido, CSS malicioso pode ser injetado.

**Correcao:** Self-hospedar a fonte Inter (baixar e servir de `public/fonts/`).

---

## Plano de Acao Recomendado

### ~~Fase 1 — Criticas~~ — CONCLUIDA (2026-02-06)

| # | Acao | Status |
|---|------|--------|
| 1 | Remover `image/svg+xml` da lista de tipos inline em `downloadAttachment()` | Feito |
| 2 | Sanitizar SVGs no upload de branding (`enshrined/svg-sanitize`) | Feito |
| 3 | Adicionar `Permissions-Policy` nos 5 Nginx configs (demais headers ja existiam) | Feito |
| 4 | Restringir `trustProxies` para `['127.0.0.1', '::1']` | Feito |

### ~~Fase 2 — Altas~~ — CONCLUIDA (2026-02-06)

| # | Acao | Status |
|---|------|--------|
| 5 | `SESSION_SECURE_COOKIE=true` em DEV e PRD | Feito |
| 6 | `SESSION_ENCRYPT=true` em DEV e PRD | Ja existia |
| 7 | Substituir `$e->getMessage()` por mensagens genericas (4 arquivos) | Feito |
| 8 | Rate limiting por email no login (10/5min) | Feito |
| 9 | Verificar `APP_DEBUG=false` em producao | Ja existia |

### ~~Fase 3 — Medias~~ — CONCLUIDA (2026-02-06)

| # | Acao | Status |
|---|------|--------|
| 10 | Throttle em endpoints de API (`routes/web.php`) | Feito |
| 11 | Validar MIME type em uploads de anexo | Feito |
| 12 | Limitar destinatarios por e-mail (max 50) | Feito |
| 13 | Timeout absoluto de sessao 8h (`EnsureImapAuthenticated`) | Feito |
| 14 | Scheduled task para limpar anexos temporarios (`routes/console.php`) | Feito |
| 15 | Remover `path` da resposta de upload de anexo | Feito |
| 16 | Re-validar status admin a cada 15min (`DomainAdminOnly`) | Feito |
| 17 | Melhorar sanitizacao CSS (decode entities, bloquear @font-face, data:) | Feito |

### Fase 4 — Pendente (Baixas)

| # | Acao | Esforco |
|---|------|---------|
| 18 | Adicionar audit logging | ~2h |
| 19 | Self-hospedar fonte Inter | ~30min |

---

## Headers de Seguranca Recomendados (Nginx)

Adicionar ao bloco `server` de cada config Nginx do webmail:

```nginx
# HSTS — forca HTTPS por 1 ano
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# Impede MIME type sniffing
add_header X-Content-Type-Options "nosniff" always;

# Protecao contra clickjacking
add_header X-Frame-Options "DENY" always;

# Referrer Policy — nao vazar URLs internas
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Permissions Policy — desabilitar APIs desnecessarias
add_header Permissions-Policy "accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()" always;

# Impede cache de conteudo sensivel
add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate" always;
add_header Pragma "no-cache" always;

# Ocultar versao do servidor
server_tokens off;

# Content Security Policy (ver secao dedicada abaixo)
# add_header Content-Security-Policy "..." always;
```

**Configs a alterar:**
- `/etc/nginx/sites-available/webmail.ista.com.br.conf`
- `/etc/nginx/sites-available/webmail.siscar.app.br.conf`
- Config de dev (se existir)

---

## CSP Recomendada

```
Content-Security-Policy:
    default-src 'self';
    script-src 'self';
    style-src 'self' 'unsafe-inline' https://fonts.bunny.net;
    font-src 'self' https://fonts.bunny.net;
    img-src 'self' data: blob:;
    connect-src 'self';
    frame-src 'self' blob:;
    frame-ancestors 'none';
    base-uri 'self';
    form-action 'self';
    object-src 'none';
    upgrade-insecure-requests;
```

**Notas:**
- `'unsafe-inline'` em `style-src` e necessario por causa do Tailwind e TipTap (injetam estilos inline)
- `frame-ancestors 'none'` substitui `X-Frame-Options` em browsers modernos
- `object-src 'none'` bloqueia Flash, Java applets e plugins
- Se self-hospedar a fonte Inter, remover `https://fonts.bunny.net` de `style-src` e `font-src`

---

## Rate Limiting Recomendado

| Endpoint | Atual | Recomendado |
|----------|-------|-------------|
| Login (por IP) | 5/min | 5/min (manter) |
| Login (por email) | Nenhum | **10/5min** |
| Envio de e-mail (por usuario) | 30/hora | 30/hora (manter) |
| Envio de e-mail (burst) | Nenhum | **5/min** |
| Upload de anexo | Nenhum | **20/10min por sessao** |
| Busca | Nenhum | **30/min por sessao** |
| Autocomplete de contatos | Nenhum | **5/min por sessao** |
| Operacoes de pasta | Nenhum | **10/min por sessao** |
| Destinatarios por mensagem | Ilimitado | **max 50 (to+cc+bcc)** |
| Envios diarios | Ilimitado | **200/dia por usuario** |

---

## Referencias

- [OWASP Top 10 (2021)](https://owasp.org/Top10/)
- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP Content Security Policy Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [OWASP HTTP Security Response Headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html)
- [HTMLPurifier](http://htmlpurifier.org/)
- [Laravel Security — Encryption](https://laravel.com/docs/12.x/encryption)
- [MDN — Subresource Integrity](https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity)
- [MDN — Content-Security-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy)
