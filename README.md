# PuSH-IT Extension — PHP BETA

> ## ⚠️ NPM users — please read first (important)
>
> Mr Cheese extensions were built for **Git copy install** first. Wappler's **npm** lane (Project Settings → Extensions) puts the package in `node_modules` but **does not automatically copy** Server Connect modules, App Connect JS/CSS, or other files into your project folders.
>
> **This PHP BETA is not published on npm.** For Node projects use **[Wappler-PuSH-IT-Extension](https://github.com/MrCheeseGit/Wappler-PuSH-IT-Extension)** (`wappler-push-it` on npm) and follow its full npm install section. For this PHP build, use the [Git Extension Installer](https://www.mrcheese.co.uk/extensions/install) only.
>
> Mr Cheese is working on a combined solution and has proposed **[`wappler-install.json`](https://github.com/MrCheeseGit/Wappler-Git-Extension-Manifest-Standard)** so install tools (and hopefully Wappler itself) can deploy extensions the same way from Git or npm. Until then, sorry for the extra steps — this is one reason these extensions were never intended to rely on npm alone.

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

[![License: Mr Cheese Extension v1.0](https://img.shields.io/badge/License-Mr%20Cheese%20Extension%20v1.0-blue.svg)](https://www.mrcheese.co.uk/extension-license)
![Wappler](https://img.shields.io/badge/Wappler-Server%20Connect%20PHP-teal)
![Version](https://img.shields.io/badge/version-0.1.3--beta-orange)

Built by **[Mr Cheese](https://www.mrcheese.co.uk)**

---

## What it does

Same workflow as Node PuSH-IT:

- **Prepare** — parse browser `PushSubscription` JSON into flat fields for **Database Insert**
- **Send** — notify one subscription or a **query result set** (query-first)

Browser setup (service worker, subscribe page, VAPID keys) is **identical** to the Node extension.

**No App Connect component** ships with PHP PuSH-IT. Use [examples/pushit-subscribe-vanilla.js](examples/pushit-subscribe-vanilla.js) or your own JS. POST **resolved** `userUUID` / `entityId` values (from PHP session, hidden inputs, or a profile API) — not Wappler binding path strings like `adminProfile.data.getProfile.subUsrUUID`.

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

### 1. Composer dependency

From your **Wappler PHP project root** (the folder that contains `composer.json`):

```bash
composer require minishlink/web-push
```

### 2. Extension files

Run from your **Wappler project root** (the folder that contains `package.json`). Skip `git clone` if you already have this repo cloned alongside your project.

```bash
git clone https://github.com/MrCheeseGit/Wappler-PuSH-IT-Extension-PHP-BETA.git ../Wappler-PuSH-IT-Extension-PHP-BETA

cp ../Wappler-PuSH-IT-Extension-PHP-BETA/pushit.php extensions/server_connect/modules/pushit.php
cp ../Wappler-PuSH-IT-Extension-PHP-BETA/pushit_prepare.hjson extensions/server_connect/modules/
cp ../Wappler-PuSH-IT-Extension-PHP-BETA/pushit_send.hjson extensions/server_connect/modules/
cp ../Wappler-PuSH-IT-Extension-PHP-BETA/pushit_service_worker.js public/pushit_service_worker.js
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

[Mr Cheese Extension License v1.0](https://www.mrcheese.co.uk/extension-license) — see [LICENSE](LICENSE). © [Mr Cheese](https://www.mrcheese.co.uk)
