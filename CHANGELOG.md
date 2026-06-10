# Changelog

## 0.1.1-beta — 2026-06-04

### Added

- **Prepare** — `sanitizeContextField()` drops App Connect-style binding paths accidentally POSTed as `userUUID` / `entityId` literals.
- **`examples/pushit-subscribe-vanilla.js`** — subscribe without App Connect; reads UUIDs from hidden inputs.

### Changed

- README / examples — document PHP vs Node: no `dmx-pushit-subscribe`; pass real UUIDs in POST.

## 0.1.0-beta — 2026-06-04

- **BETA** — PuSH-IT for **Wappler Server Connect (PHP)** (separate from the Node extension)
- **PuSH-IT Prepare Subscription** — parse browser subscription JSON for Database Insert
- **PuSH-IT Send Notification** — single subscription or query-first batch via `minishlink/web-push`
- Shared **service worker** and MySQL schema example (same browser setup as Node PuSH-IT)
