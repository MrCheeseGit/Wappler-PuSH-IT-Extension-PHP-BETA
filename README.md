# PuSH-IT Extension — PHP BETA

Web Push notifications for **Wappler Server Connect (PHP)** using **VAPID** — **no paid push API**, no Firebase project, no per-message fees. Browsers use Google/Mozilla push services for free; you only host your own keys and subscription rows.

> ## ⚠️ BETA — read before you install
>
> **This is a BETA extension.** It has **not** been fully tested by Mr Cheese on production PHP targets.
>
> Mr Cheese is a **Node.js convert** (disciple of the Gospel of JonL) — the **Node** PuSH-IT extension is the battle-tested build. This PHP port mirrors the same design and is offered for PHP Server Connect users who want parity, but **use at your own risk** in beta and report issues on GitHub.
>
> For production Node projects, use the main extension instead (link below).

> **Separate package — not interchangeable with Node.** For **Node** projects use:  
> [Wappler-PuSH-IT-Extension](https://github.com/MrCheeseGit/Wappler-PuSH-IT-Extension)  
> Do not mix Node and PHP module files in the same project.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![Wappler](https://img.shields.io/badge/Wappler-Server%20Connect%20PHP-teal)
![Version](https://img.shields.io/badge/version-0.1.0--beta-orange)

Built by **[Mr Cheese](https://www.mrcheese.co.uk)**

---

## What it does

Same workflow as Node PuSH-IT:

- **Prepare** — parse browser `PushSubscription` JSON into flat fields for **Database Insert**
- **Send** — notify one subscription or a **query result set** (query-first)

Browser setup (service worker, subscribe page, VAPID keys) is **identical** to the Node extension.

---

## Requirements

- Wappler project with **Server Connect PHP** target
- **Composer** on the server (or local PHP dev environment)
- **`minishlink/web-push`** package
- **`dmx-browser`** not required — Web Push is browser + service worker only

### Environment variables

Set in **Wappler Project Settings → Environment** (same names as Node PuSH-IT):

| Variable | Required |
|----------|----------|
| `VAPID_PUBLIC_KEY` | Yes |
| `VAPID_PRIVATE_KEY` | Yes (server only) |
| `VAPID_SUBJECT` | Yes (e.g. `https://yoursite.com/`) |
| `PUSH_IT_DEFAULT_ICON` | No |
| `PUSH_IT_DEFAULT_URL` | No |

Generate keys: `npx web-push generate-vapid-keys` (or any VAPID tool — keys work on PHP and Node).

---

## Install

### 1. Composer dependency (PHP project root)

```bash
cd YOUR_WAPPLER_PHP_PROJECT
composer require minishlink/web-push
```

### 2. Extension files

```bash
cp pushit.php YOUR_PROJECT/extensions/server_connect/modules/pushit.php
cp pushit_prepare.hjson pushit_send.hjson YOUR_PROJECT/extensions/server_connect/modules/
cp pushit_service_worker.js YOUR_PROJECT/public/pushit_service_worker.js
```

**Quit Wappler completely and restart.**

Actions appear under **Mr Cheese → PuSH-IT Prepare Subscription** / **PuSH-IT Send Notification**.

### 3. Database

Run once: [examples/sql/push_subscriptions.mysql.sql](examples/sql/push_subscriptions.mysql.sql)

### 4. Service worker

Register from your subscribe page:

```javascript
navigator.serviceWorker.register('/pushit_service_worker.js', { scope: '/' });
```

See [pushit_service_worker.js](pushit_service_worker.js) for details.

---

## Typical API flow

1. **Subscribe API** — `PuSH-IT Prepare` (bind `{{$_POST.subscription}}`) → **Database Insert**
2. **Notify API** — **Database Query** (active subscriptions) → `PuSH-IT Send` (mode: **Send to query results**, bind query, map `endpoint` / `p256dh` / `auth` columns)

---

## vs Node PuSH-IT

| | Node PuSH-IT | PHP BETA |
|---|--------------|----------|
| Runtime | `pushit.js` + `web-push` (npm) | `pushit.php` + `minishlink/web-push` (Composer) |
| Install path | `lib/modules/` + `extensions/...` | `extensions/server_connect/modules/pushit.php` |
| Service worker | Same file | Same file |
| VAPID env vars | Same | Same |
| Status | Production | **Beta — feedback welcome** |

---

## Beta notes

- Tested against Wappler PHP module conventions (`\lib\core\Module`, `parseObject`).
- Report issues with PHP version, Wappler target, and Composer setup.
- Feature parity goal with Node v1.0; SMS fallback remains a separate **ClickSend** step (PHP or Node).

---

## License

MIT — see [LICENSE](LICENSE).
