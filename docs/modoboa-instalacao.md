# Instalacao Modoboa Mail Server - VPS1

**Data:** 2 de Fevereiro de 2026
**Servidor:** vps1.scriptorium.net.br (76.13.171.208)
**Sistema:** Ubuntu 24.04 LTS
**Dominio principal:** scriptorium.net.br

---

## Indice

1. [Visao Geral](#visao-geral)
2. [Versoes Instaladas](#versoes-instaladas)
3. [Arquitetura](#arquitetura)
4. [Processo de Instalacao](#processo-de-instalacao)
5. [Problemas Encontrados e Solucoes](#problemas-encontrados-e-solucoes)
6. [Certificado SSL (Let's Encrypt)](#certificado-ssl-lets-encrypt)
7. [Configuracao DKIM](#configuracao-dkim)
8. [Arquivos Importantes](#arquivos-importantes)
9. [Credenciais](#credenciais)
10. [DNS Atual (Cloudflare)](#dns-atual-cloudflare)
11. [Servicos e Portas](#servicos-e-portas)
12. [Proximos Passos](#proximos-passos)

---

## Visao Geral

Servidor de e-mail completo instalado com Modoboa 2.6.5 utilizando o modoboa-installer oficial. O Modoboa inclui webmail integrado (o antigo plugin modoboa-webmail foi descontinuado e incorporado ao core do Modoboa 2.x).

O servidor foi configurado com:
- **Postfix** como MTA (SMTP)
- **Dovecot** como MDA (IMAP/POP3)
- **rspamd** como anti-spam + DKIM signing
- **Nginx** como reverse proxy / frontend web
- **uWSGI** para servir a aplicacao Django
- **PostgreSQL** como banco de dados
- **Redis** para cache, filas e sessoes
- **Radicale** para CalDAV/CardDAV
- **Fail2ban** para protecao contra brute-force

### Contexto de Migracao

Existe um servidor de e-mail antigo em `mail.scriptorium.net.br` (IP 69.62.92.54). O plano e migrar dominios e caixas de e-mail gradualmente para este novo servidor. O DNS no Cloudflare ainda aponta MX para o servidor antigo.

Dominios planejados para caixas de e-mail:
- scriptorium.net.br (principal)
- siscar.app.br
- bingoplay.app.br
- apismater.com.br

---

## Versoes Instaladas

| Componente   | Versao                    |
|-------------|---------------------------|
| Modoboa     | 2.6.5                     |
| Postfix     | 3.8.6                     |
| Dovecot     | 2.3.21                    |
| rspamd      | 3.14.3                    |
| Nginx       | 1.29.4                    |
| PostgreSQL  | 18.1                      |
| Python      | 3.12 (virtualenv)         |
| Ubuntu      | 24.04 LTS                 |

---

## Arquitetura

```
Internet
   |
   v
[Nginx :80/:443] --> redirect HTTP->HTTPS
   |
   |-- /                    --> Frontend SPA (Vue.js em /srv/modoboa/instance/frontend/)
   |-- /api, /accounts      --> uWSGI (Django/Modoboa backend)
   |-- /rspamd/             --> proxy para localhost:11334
   |-- /radicale/           --> proxy para localhost:5232
   |
[Postfix :25/:587/:465] <--> [Dovecot :143/:993/:110/:995]
   |                              |
   v                              v
[rspamd :11334]            [Mailboxes em /srv/modoboa/]
(anti-spam + DKIM)
   |
[Redis :6379] -- cache, filas, sessoes
   |
[PostgreSQL :5432] -- banco de dados 'modoboa'
```

---

## Processo de Instalacao

### 1. Script de instalacao

O script `/root/scripts/install_modoboa.sh` foi criado e iterado diversas vezes. Ele:

1. Instala dependencias do sistema
2. Configura PostgreSQL (cria banco `modoboa` e usuario `modoboa` com senha gerada automaticamente)
3. Clona o repositorio `modoboa-installer` do GitHub
4. Gera o `installer.cfg` padrao com `--stop-after-configfile-check`
5. Ajusta o config via `sed` (hostname, engine, rspamd, timezone, etc.)
6. Adiciona o hostname do mail ao `/etc/hosts`
7. Pre-cria o diretorio `/srv/modoboa` com usuario `modoboa` (workaround Ubuntu 24.04)
8. Executa o installer com `yes | python3 run.py --debug --configfile installer.cfg`
9. Ajusta permissoes e salva credenciais

### 2. Execucao do modoboa-installer

O installer foi executado com dominio `scriptorium.net.br` e hostname `vps1.scriptorium.net.br`.

Parametros chave no `installer.cfg`:
- `engine = postgres` (nao `postgresql` - o installer espera `postgres`)
- `hostname = vps1.scriptorium.net.br`
- `timezone = America/Sao_Paulo`
- `[antispam] type = rspamd`
- `[opendkim] enabled = false` (rspamd cuida do DKIM)
- `[postwhite] enabled = false` (incompativel com rspamd)

### 3. Pos-instalacao manual

Apos o installer terminar, foram necessarios varios ajustes manuais:

#### a) Frontend (404 Not Found)

O diretorio `/srv/modoboa/instance/frontend/` nao existia apos a instalacao. O comando `load_initial_data` do Django resolve isso:

```bash
sudo -u modoboa /srv/modoboa/env/bin/python /srv/modoboa/instance/manage.py load_initial_data
```

Este comando:
- Cria a aplicacao OAuth necessaria para o frontend v2
- Gera o arquivo `config.json` com OAUTH_CLIENT_ID
- Configura o diretorio frontend com os arquivos estaticos

#### b) Redis com senha (login travado em "Attempting to log you in")

O Redis foi instalado com autenticacao (senha), mas o `settings.py` do Modoboa nao estava configurado para usar a senha. Isso causava erro de autenticacao no Redis, impedindo o login.

Correcao em `/srv/modoboa/instance/instance/settings.py`:

```python
REDIS_HOST = 'localhost'
REDIS_PORT = 6379
REDIS_QUOTA_DB = 0
REDIS_PASSWORD = 'I9lYZMR0Mi8zTUuva75f9gDMNpsvh5fE4+gwOUg3/xI='
from urllib.parse import quote as _url_quote
REDIS_URL = 'redis://:{}@{}:{}/{}'.format(
    _url_quote(REDIS_PASSWORD, safe=''),
    REDIS_HOST, REDIS_PORT, REDIS_QUOTA_DB
)
```

**Nota:** A senha do Redis contem caracteres especiais (`/`, `+`, `=`) que quebram o parsing de URL. O `urllib.parse.quote()` com `safe=''` e necessario para escapar esses caracteres.

A senha do Redis esta em: `/etc/redis/redis.conf` (linha `requirepass`)

#### c) DKIM Storage Directory

O Modoboa exibia alerta "Diretorio de armazenamento de chaves DKIM nao configurado". Corrigido adicionando ao `settings.py`:

```python
DKIM_KEYS_STORAGE_DIR = "/var/lib/dkim"
```

Permissoes do diretorio ajustadas para que tanto Modoboa quanto rspamd possam acessar:

```bash
chown modoboa:_rspamd /var/lib/dkim
chmod 775 /var/lib/dkim
```

---

## Problemas Encontrados e Solucoes

### 1. Flag `--config` vs `--configfile`
- **Problema:** O modoboa-installer usa `--configfile`, nao `--config`
- **Solucao:** Corrigido o comando para `python3 run.py --configfile installer.cfg`

### 2. Engine `postgresql` vs `postgres`
- **Problema:** O `database.py` do installer verifica `engine == "postgres"`, nao `postgresql`
- **Solucao:** Usar `engine = postgres` no installer.cfg

### 3. Formato do installer.cfg desatualizado
- **Problema:** Gerar o config manualmente resultava em formato incompativel
- **Solucao:** Usar `--stop-after-configfile-check` para gerar o formato correto, depois ajustar com `sed`

### 4. postwhite e opendkim incompativeis com rspamd
- **Problema:** Esses componentes conflitam com rspamd
- **Solucao:** Desabilitar ambos no installer.cfg (`enabled = false`)

### 5. Prompts interativos do installer
- **Problema:** O installer faz varias perguntas Y/n durante execucao
- **Solucao:** Usar `yes |` antes do comando

### 6. Ubuntu 24.04 - diretorio /srv/modoboa
- **Problema:** Issue #573 do modoboa-installer - falha de permissao ao criar o diretorio
- **Solucao:** Pre-criar o usuario `modoboa` e o diretorio `/srv/modoboa` antes de rodar o installer

### 7. Frontend 404 apos instalacao
- **Problema:** O diretorio frontend nao foi populado pelo installer
- **Solucao:** Executar `manage.py load_initial_data` que cria a app OAuth e popula o frontend

### 8. Login travado ("Attempting to log you in")
- **Problema:** Redis com autenticacao + Modoboa sem senha no REDIS_URL
- **Solucao:** Adicionar REDIS_PASSWORD ao settings.py com URL encoding

### 9. Certificado SSL "Nao seguro"
- **Problema:** Nginx usando certificado auto-assinado em vez do Let's Encrypt
- **Solucao:** Atualizar ssl_certificate/ssl_certificate_key no nginx conf para apontar para `/etc/letsencrypt/live/scriptorium.net.br/`

---

## Certificado SSL (Let's Encrypt)

### Certificado Wildcard

Um certificado wildcard foi obtido via DNS challenge com Cloudflare:

- **Dominio:** `*.scriptorium.net.br` + `scriptorium.net.br`
- **Issuer:** Let's Encrypt (E8 - ECDSA)
- **Validade:** 28/Jan/2026 a 28/Abr/2026
- **Localizacao:** `/etc/letsencrypt/live/scriptorium.net.br/`

### Credenciais Cloudflare para renovacao

Arquivo: `/root/.cloudflare/credentials.ini` (chmod 600)

Contém o API Token do Cloudflare com permissao Zone > DNS > Edit.

### Comando para renovar manualmente

```bash
certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.cloudflare/credentials.ini \
  -d "*.scriptorium.net.br" -d "scriptorium.net.br" \
  --force-renewal \
  --dns-cloudflare-propagation-seconds 30
```

### Renovacao automatica

O certbot ja configura um timer systemd para renovacao automatica. Verificar com:

```bash
systemctl list-timers | grep certbot
```

### Configuracao Nginx

O Nginx foi atualizado para usar o certificado Let's Encrypt:

```nginx
ssl_certificate /etc/letsencrypt/live/scriptorium.net.br/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/scriptorium.net.br/privkey.pem;
```

---

## Configuracao DKIM

### Diretorio de chaves
- **Path:** `/var/lib/dkim/`
- **Owner:** `modoboa:_rspamd`
- **Permissao:** `775`

### Configuracao rspamd

Arquivo `/etc/rspamd/local.d/dkim_signing.conf`:
```
try_fallback = false;
selector_map = "/var/lib/dkim/selectors.path.map";
path_map = "/var/lib/dkim/keys.path.map";
```

### Configuracao Modoboa

Em `/srv/modoboa/instance/instance/settings.py`:
```python
DKIM_KEYS_STORAGE_DIR = "/var/lib/dkim"
```

### Gerar chaves DKIM

As chaves DKIM sao geradas pelo Modoboa ao habilitar DKIM para um dominio no painel administrativo (Dominios > scriptorium.net.br > DKIM).

Apos gerar, o registro TXT deve ser adicionado no Cloudflare.

---

## Arquivos Importantes

### Configuracao

| Arquivo | Descricao |
|---------|-----------|
| `/srv/modoboa/instance/instance/settings.py` | Config principal do Django/Modoboa |
| `/srv/modoboa/instance/frontend/config.json` | Config do frontend (OAuth) |
| `/etc/nginx/sites-enabled/vps1.scriptorium.net.br.conf` | Nginx vhost |
| `/etc/postfix/main.cf` | Configuracao do Postfix |
| `/etc/dovecot/dovecot.conf` | Configuracao do Dovecot |
| `/etc/rspamd/local.d/` | Configs locais do rspamd |
| `/etc/redis/redis.conf` | Config do Redis (contem senha) |
| `/opt/modoboa-installer/installer.cfg` | Config usada na instalacao |
| `/root/.cloudflare/credentials.ini` | API Token Cloudflare (certbot) |
| `/root/.modoboa_credentials` | Credenciais salvas na instalacao |

### Scripts

| Arquivo | Descricao |
|---------|-----------|
| `/root/scripts/install_modoboa.sh` | Script de instalacao (referencia) |

### Logs

| Arquivo | Descricao |
|---------|-----------|
| `/var/log/mail.log` | Log geral de e-mail (Postfix/Dovecot) |
| `/var/log/modoboa/` | Logs do Modoboa |
| `/var/log/nginx/vps1.scriptorium.net.br-access.log` | Access log Nginx |
| `/var/log/nginx/vps1.scriptorium.net.br-error.log` | Error log Nginx |
| `/var/log/rspamd/` | Logs do rspamd |

### Certificados

| Arquivo | Descricao |
|---------|-----------|
| `/etc/letsencrypt/live/scriptorium.net.br/fullchain.pem` | Certificado + chain |
| `/etc/letsencrypt/live/scriptorium.net.br/privkey.pem` | Chave privada |
| `/etc/ssl/certs/vps1.scriptorium.net.br.cert` | Cert auto-assinado (nao mais usado) |

---

## Credenciais

### Modoboa Admin
- **URL:** https://vps1.scriptorium.net.br/
- **Usuario:** admin
- **Senha:** alterada pelo usuario (senha padrao era `password`)

### rspamd Web UI
- **URL:** https://vps1.scriptorium.net.br/rspamd/
- **Senha:** ver `/opt/modoboa-installer/installer.cfg` secao `[rspamd]` campo `password`

### Banco de Dados PostgreSQL
- **Database:** modoboa
- **Usuario:** modoboa
- **Senha:** ver `/root/.modoboa_credentials`
- **Host:** localhost
- **Porta:** 5432

### Redis
- **Senha:** `I9lYZMR0Mi8zTUuva75f9gDMNpsvh5fE4+gwOUg3/xI=`
- **Arquivo:** `/etc/redis/redis.conf` (requirepass)

---

## DNS Atual (Cloudflare)

### scriptorium.net.br - Registros relevantes para e-mail

```
; A Records
mail.scriptorium.net.br     A    69.62.92.54    (servidor ANTIGO)
scriptorium.net.br          A    69.62.92.54

; AAAA Records
mail.scriptorium.net.br     AAAA 2a02:4780:14:71da::1

; MX Records
scriptorium.net.br          MX   10 scriptorium.net.br

; TXT Records (SPF)
scriptorium.net.br          TXT  "v=spf1 ip4:69.62.92.54 ~all"

; TXT Records (DKIM)
default._domainkey           TXT  "v=DKIM1; h=sha256; k=rsa; p=MIIBIjAN..."

; TXT Records (DMARC)
_dmarc.scriptorium.net.br   TXT  "v=DMARC1; p=quarantine;"
```

**IMPORTANTE:** O DNS ainda aponta para o servidor antigo (69.62.92.54). Para ativar o novo servidor, sera necessario atualizar:

1. `mail.scriptorium.net.br` A record -> `76.13.171.208`
2. MX record apontar para `vps1.scriptorium.net.br` (ou `mail.scriptorium.net.br` apos atualizar o A)
3. SPF incluir o novo IP: `v=spf1 ip4:76.13.171.208 ip4:69.62.92.54 ~all`
4. Gerar nova chave DKIM no Modoboa e atualizar o registro TXT
5. Eventualmente remover o IP antigo do SPF apos migracao completa

---

## Servicos e Portas

| Porta | Protocolo | Servico    | Descricao                |
|-------|-----------|-----------|--------------------------|
| 25    | TCP       | Postfix   | SMTP                     |
| 465   | TCP       | Postfix   | SMTPS (implicit TLS)     |
| 587   | TCP       | Postfix   | Submission (STARTTLS)    |
| 143   | TCP       | Dovecot   | IMAP                     |
| 993   | TCP       | Dovecot   | IMAPS (implicit TLS)     |
| 110   | TCP       | Dovecot   | POP3                     |
| 995   | TCP       | Dovecot   | POP3S (implicit TLS)     |
| 80    | TCP       | Nginx     | HTTP (redirect to HTTPS) |
| 443   | TCP       | Nginx     | HTTPS                    |
| 11334 | TCP       | rspamd    | Web UI (via Nginx proxy) |
| 5432  | TCP       | PostgreSQL| Database (localhost only) |
| 6379  | TCP       | Redis     | Cache/Queue (localhost)  |
| 5232  | TCP       | Radicale  | CalDAV/CardDAV (via Nginx)|

### Verificar status dos servicos

```bash
systemctl status postfix
systemctl status dovecot
systemctl status nginx
systemctl status rspamd
systemctl status uwsgi
systemctl status redis-server
systemctl status postgresql
systemctl status fail2ban
systemctl status radicale
```

---

## Proximos Passos

### Imediatos

1. **Configurar DKIM no Modoboa**
   - Painel > Dominios > scriptorium.net.br > habilitar DKIM
   - Copiar chave publica gerada e adicionar como registro TXT no Cloudflare

2. **Configurar dominio no Modoboa**
   - Verificar se scriptorium.net.br esta criado corretamente
   - Criar caixas de e-mail de teste

3. **Testar envio/recebimento interno**
   - Criar uma conta de e-mail no Modoboa
   - Testar via webmail integrado

### Migracao DNS (quando pronto)

4. **Atualizar DNS no Cloudflare gradualmente:**
   - Adicionar novo IP ao SPF: `v=spf1 ip4:76.13.171.208 ip4:69.62.92.54 ~all`
   - Atualizar MX para apontar para o novo servidor
   - Atualizar A record de `mail.scriptorium.net.br` para `76.13.171.208`
   - Gerar e publicar nova chave DKIM

### Dominios adicionais

5. **Adicionar dominios no Modoboa:**
   - siscar.app.br
   - bingoplay.app.br
   - apismater.com.br

   Para cada dominio:
   - Adicionar no painel do Modoboa
   - Configurar DNS (MX, SPF, DKIM, DMARC)
   - Obter certificado SSL (ou usar o wildcard de scriptorium.net.br + certs separados)
   - Criar caixas de e-mail

### Opcional

6. **Configurar hostnames por dominio** (para profissionalismo)
   - mail.siscar.app.br, mail.bingoplay.app.br, etc.
   - Cada um com A record apontando para 76.13.171.208
   - Util para configuracao IMAP/SMTP em clientes como Outlook

7. **Configurar autoconfig/autodiscover**
   - Facilita configuracao automatica de clientes de e-mail
   - Registro DNS: `autoconfig.dominio.com` CNAME para o servidor

---

## Comandos Uteis

### Reiniciar servicos

```bash
systemctl restart postfix dovecot nginx uwsgi rspamd
```

### Verificar filas de e-mail

```bash
postqueue -p       # ver fila
postqueue -f       # forcar processamento da fila
```

### Django manage.py

```bash
sudo -u modoboa /srv/modoboa/env/bin/python /srv/modoboa/instance/manage.py <comando>
```

Comandos uteis:
- `load_initial_data` - recarregar dados iniciais (OAuth, frontend)
- `modo manage_dkim_keys` - gerenciar chaves DKIM
- `shell` - shell interativo Django
- `createsuperuser` - criar novo superusuario

### Verificar certificado SSL

```bash
echo | openssl s_client -connect vps1.scriptorium.net.br:443 -servername vps1.scriptorium.net.br 2>/dev/null | openssl x509 -noout -issuer -subject -dates
```

### Testar SMTP

```bash
openssl s_client -connect vps1.scriptorium.net.br:587 -starttls smtp
```

### Testar IMAP

```bash
openssl s_client -connect vps1.scriptorium.net.br:993
```

### Logs em tempo real

```bash
tail -f /var/log/mail.log              # e-mail geral
tail -f /var/log/nginx/vps1.scriptorium.net.br-error.log  # erros nginx
journalctl -u uwsgi -f                 # logs do Modoboa/Django
```

---

## Configuracao Padrao do Webmail

### Problema: Links nao clicaveis

Por padrao, o Modoboa webmail vem com links HTML desabilitados por seguranca (protecao contra phishing). Os valores padrao sao:
- `displaymode`: `plain` (texto puro)
- `enable_links`: `false` (links desabilitados)

### Solucao Implementada

Foi criado um app Django customizado (`modoboa_custom_defaults`) que:
1. Atualiza todos os usuarios existentes para usar HTML com links
2. Configura automaticamente novos usuarios com essas preferencias

### Arquivos criados

| Arquivo | Descricao |
|---------|-----------|
| `/root/scripts/install-modoboa-defaults.sh` | Script de instalacao automatica |
| `/root/scripts/modoboa_custom_defaults/` | App Django para novos usuarios |
| `/root/scripts/modoboa-default-prefs.py` | Script Python para atualizacao manual |
| `/root/scripts/modoboa-webmail-defaults.sql` | Script SQL alternativo |

### Instalacao

```bash
sudo bash /root/scripts/install-modoboa-defaults.sh
```

O script:
1. Copia o app para `/srv/modoboa/instance/modoboa_custom_defaults/`
2. Atualiza todos os usuarios existentes
3. Reinicia o uWSGI

### Configuracao manual (se necessario)

Se o script nao conseguir adicionar automaticamente, edite `/srv/modoboa/instance/instance/settings.py` e adicione ao `INSTALLED_APPS`:

```python
INSTALLED_APPS = [
    # ... outros apps ...
    'modoboa_custom_defaults',
]
```

### Preferencias configuradas

| Parametro | Valor | Descricao |
|-----------|-------|-----------|
| `webmail.displaymode` | `html` | Exibe e-mails em HTML |
| `webmail.enable_links` | `true` | Links clicaveis habilitados |
| `webmail.editor` | `html` | Editor HTML para compor e-mails |
| `webmail.signature` | `&nbsp;` | Assinatura vazia (espaco invisivel) |

### Patches no codigo do Modoboa (reaplicar apos atualizacao)

**IMPORTANTE:** Os patches abaixo sao modificacoes diretas nos arquivos do pacote Modoboa.
Se o Modoboa for atualizado (`pip upgrade`), essas modificacoes serao perdidas e precisarao ser reaplicadas.

#### 1. viewsets.py - Usar preferencia do usuario para links

Arquivo: `/srv/modoboa/env/lib/python3.12/site-packages/modoboa/webmail/viewsets.py`

No metodo `content()`, substituir:
```python
links=request.GET.get("links", "0") == "1",
```

Por:
```python
links = request.user.parameters.get_value("enable_links")
```

Isso faz o backend usar a preferencia do usuario em vez do parametro da URL (frontend sempre passa links=0).

#### 2. email_utils.py - Nao remover links do HTML

Arquivo: `/srv/modoboa/env/lib/python3.12/site-packages/modoboa/lib/email_utils.py`

No metodo `_post_process_html()`, no Cleaner, substituir:
```python
links=True,
```

Por:
```python
links=not self.links,  # Nao remover links se usuario quer ve-los
```

Isso evita que o lxml Cleaner remova os links do HTML quando o usuario quer ve-los.
