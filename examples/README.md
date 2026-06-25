# PuSH-IT PHP beta — examples

- **`sql/push_subscriptions.mysql.sql`** — suggested subscription table (same as Node PuSH-IT)
- **`pushit-subscribe-vanilla.js`** — browser subscribe helper **without App Connect**

Wire your own subscribe API: **Prepare** → **Database Insert** → query active rows → **Send** (from query).

## PHP / vanilla JS — user UUID and entity ID

The **Node App Connect** component (`dmx-pushit-subscribe`) is **not** part of this PHP package.

On PHP projects, pass **real values** in the subscribe POST body — never literal binding paths such as `adminProfile.data.getProfile.subUsrUUID`.

| Do | Don't |
|----|--------|
| Hidden input `value="<?= $subUsrUUID ?>"` read by JS | `userUUID: 'adminProfile.data.getProfile.subUsrUUID'` in JS |
| `{{$_POST.userUUID}}` in Prepare (Wappler resolves POST) | Typing a binding path into a plain text field without `dmx-bind` |

**Prepare** (0.1.1-beta+) strips values that look like unresolved binding paths so bad rows are not stored silently.

For the production **Node** extension (App Connect + Server Connect), see [Wappler-PuSH-IT-Extension](https://github.com/MrCheeseGit/Wappler-PuSH-IT-Extension).
