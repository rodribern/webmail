# Branding por Dominio - Modoboa Webmail

**Data:** 2 de Fevereiro de 2026
**Servidor:** vps1.scriptorium.net.br (76.13.171.208)
**Modoboa:** 2.6.5 (frontend Vue 3 + Vuetify 3)

---

## Indice

1. [Objetivo](#objetivo)
2. [Abordagem](#abordagem)
3. [Arquivos Criados](#arquivos-criados)
4. [Alteracoes no Nginx](#alteracoes-no-nginx)
5. [Como Funciona](#como-funciona)
6. [Configuracao por Dominio](#configuracao-por-dominio)
7. [Como Alterar Cores](#como-alterar-cores)
8. [Como Alterar Logos](#como-alterar-logos)
9. [Sobrevivencia a Updates](#sobrevivencia-a-updates)
10. [Troubleshooting](#troubleshooting)

---

## Objetivo

Aplicar logo e cores diferentes no webmail Modoboa dependendo do dominio acessado (mail.siscar.app.br, mail.bingoplay.app.br, vps1.scriptorium.net.br) sem modificar nenhum arquivo original do Modoboa.

---

## Abordagem

Usa o modulo `ngx_http_sub_module` do Nginx para injetar um `<script>` no `index.html` do frontend. O script detecta `window.location.hostname` e aplica:

- **Cores:** Sobrescreve CSS custom properties do Vuetify 3 (`--v-theme-primary`, etc.)
- **Logo:** Usa `MutationObserver` para trocar as imagens `<img>` do logo Modoboa quando o Vue as renderiza no DOM
- **Titulo:** Altera `document.title` para o nome do dominio

---

## Arquivos Criados

Todos em `/srv/modoboa/instance/frontend/` (fora do diretorio `assets/` do build):

| Arquivo | Descricao |
|---------|-----------|
| `branding.js` | Script principal de branding (detecta dominio, aplica cores e logos) |
| `logo-siscar.svg` | Logo SISCAR branco (ovo Tabler egg-filled + texto "SISCAR") para sidebar |
| `logo-siscar-dark.svg` | Logo SISCAR verde #2fb344 (ovo Tabler egg-filled) para tela de login |

### Origem do icone

O ovo usado nos logos e o mesmo icone `egg-filled` da biblioteca Tabler Icons (codigo Unicode `\f678`), exportado como SVG. E o mesmo icone usado no SISCAR web app.

---

## Alteracoes no Nginx

Adicionadas 2 linhas no bloco `location /` de cada vhost:

```nginx
# Domain branding injection
sub_filter '</head>' '<script src="/branding.js"></script></head>';
sub_filter_once on;
```

### Arquivos alterados

- `/etc/nginx/sites-available/mail.siscar.app.br.conf`
- `/etc/nginx/sites-available/mail.bingoplay.app.br.conf`
- `/etc/nginx/sites-available/vps1.scriptorium.net.br.conf`

### Modulo necessario

O `ngx_http_sub_module` ja vem compilado no Nginx do Ubuntu 24.04. Para verificar:

```bash
nginx -V 2>&1 | grep http_sub_module
```

---

## Como Funciona

1. Usuario acessa `https://mail.siscar.app.br`
2. Nginx serve o `index.html` do Modoboa e injeta `<script src="/branding.js">` antes do `</head>`
3. O `branding.js` executa antes do Vue montar a aplicacao:
   - Detecta o hostname
   - Injeta um `<style>` com overrides das variaveis CSS do Vuetify 3
   - Define `document.title`
   - Adiciona `<meta name="theme-color">`
4. Quando o Vue renderiza o DOM (SPA), o `MutationObserver` detecta as imagens do logo Modoboa e troca pelo logo do dominio

### Logos do Modoboa (originais, nao modificados)

O Modoboa usa dois logos definidos em `assets/logos-CMmd0hOG.js`:

| Logo | Arquivo | Uso |
|------|---------|-----|
| Menu (branco) | `Modoboa_RVB-BLANC-SANS-BBGoASES.png` | Sidebar (fundo escuro) |
| Login (azul) | `Modoboa_RVB-BLEU-SANS-pKrnjsR_.png` | Tela de login (fundo claro) |

O branding.js detecta qual PNG esta no `src` do `<img>` e substitui:
- `BLANC` → `menuLogo` (logo branco do dominio)
- `BLEU` → `loginLogo` (logo colorido do dominio)

---

## Configuracao por Dominio

Configuracao atual no `branding.js`:

| Dominio | Primary | Secondary | Menu Logo | Login Logo | Status |
|---------|---------|-----------|-----------|------------|--------|
| mail.siscar.app.br | #2fb344 (verde) | #48A9A6 | logo-siscar.svg | logo-siscar-dark.svg | Ativo |
| webmail.siscar.app.br | #2fb344 (verde) | #48A9A6 | logo-siscar.svg | logo-siscar-dark.svg | Ativo |
| mail.bingoplay.app.br | #10B981 (teal) | #14B8A6 | — | — | Cores OK, logos pendentes |
| webmail.bingoplay.app.br | #10B981 (teal) | #14B8A6 | — | — | Cores OK, logos pendentes |
| vps1.scriptorium.net.br | #DC2626 (vermelho) | #EF4444 | — | — | Cores OK, logos pendentes |

---

## Como Alterar Cores

Editar `/srv/modoboa/instance/frontend/branding.js`, no objeto `brands`:

```javascript
'mail.exemplo.com.br': {
  primary: '47,179,68',        // RGB sem #, para variaveis Vuetify
  primaryHex: '#2fb344',       // Hex, para background-color direto
  secondary: '72,169,166',
  secondaryHex: '#48A9A6',
  title: 'Exemplo Mail',
  menuLogo: '/logo-exemplo.svg',
  loginLogo: '/logo-exemplo-dark.svg'
}
```

**Importante:** O campo `primary` usa formato RGB sem `#` (ex: `47,179,68`) porque as variaveis `--v-theme-*` do Vuetify 3 esperam valores RGB separados por virgula.

Apos editar, nao precisa recarregar o Nginx — o JS e servido direto como arquivo estatico.

---

## Como Alterar Logos

1. Colocar o novo logo em `/srv/modoboa/instance/frontend/` (SVG ou PNG)
2. Editar o `branding.js` e atualizar `menuLogo` e/ou `loginLogo` com o path
3. Formatos recomendados:
   - **menuLogo:** Versao branca/clara (vai sobre fundo escuro da sidebar)
   - **loginLogo:** Versao colorida (vai sobre fundo claro da tela de login)

Para adicionar um novo dominio, basta adicionar uma entrada no objeto `brands`.

---

## Sobrevivencia a Updates

Este branding **sobrevive** a updates do Modoboa porque:

- Nenhum arquivo em `assets/` foi modificado (sao do build e serao substituidos em updates)
- O `branding.js` e os logos SVG ficam na raiz de `/srv/modoboa/instance/frontend/`, fora do build
- A injecao e feita pelo Nginx, que nao e gerenciado pelo Modoboa
- Se o Modoboa mudar os nomes dos PNGs do logo apos update, basta ajustar os patterns no `branding.js` (procurar por `Modoboa_RVB-BLANC` e `Modoboa_RVB-BLEU`)

### O que verificar apos um update do Modoboa

1. Confirmar que o `index.html` ainda tem `</head>` (para o sub_filter funcionar)
2. Verificar se os nomes dos PNGs do logo mudaram em `assets/`
3. Testar acessando o webmail e verificando cores/logo

---

## Troubleshooting

### Script nao esta sendo injetado

```bash
# Verificar se o sub_filter esta funcionando
curl -sk https://mail.siscar.app.br/ | grep branding.js
```

Se nao aparecer, verificar:
- Nginx foi recarregado (`systemctl reload nginx`)
- O bloco `location /` tem as linhas `sub_filter`
- O modulo `ngx_http_sub_module` esta disponivel

### Cores nao mudam

- Abrir DevTools (F12) → Console, verificar se ha erros no `branding.js`
- Verificar se o hostname esta listado no objeto `brands`
- Testar com hard refresh (Ctrl+Shift+R) para limpar cache

### Logo nao troca

- O `MutationObserver` desconecta apos 30 segundos. Se o logo aparece depois disso (improvavel), aumentar o timeout no `branding.js`
- Verificar no DevTools → Network se o SVG do logo esta sendo carregado (status 200)
- Confirmar que os nomes dos PNGs originais ainda contem `Modoboa_RVB-BLANC` e `Modoboa_RVB-BLEU`

### Adicionar novo dominio

1. Adicionar entrada no objeto `brands` em `branding.js`
2. Criar o vhost Nginx com as linhas `sub_filter`
3. Colocar os logos em `/srv/modoboa/instance/frontend/`
4. `nginx -t && systemctl reload nginx`
