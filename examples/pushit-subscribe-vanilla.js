/**
 * PuSH-IT — vanilla subscribe helper for PHP (no App Connect).
 *
 * Set real values on the page (PHP session, hidden inputs), not binding path strings:
 *
 *   <input type="hidden" id="pushitUserUuid" value="<?= htmlspecialchars($subUsrUUID) ?>">
 *   <input type="hidden" id="pushitEntityId" value="<?= htmlspecialchars($subscriptionUUID) ?>">
 *   <button type="button" id="pushitEnable">Enable notifications</button>
 *
 *   <script src="/js/pushit-subscribe-vanilla.js"></script>
 *   <script>
 *     PushItSubscribe.init({
 *       userUuidInput: 'pushitUserUuid',
 *       entityIdInput: 'pushitEntityId',
 *       eventTypes: 'admin',
 *     });
 *   </script>
 */
(function (global) {
    'use strict';

    var DEFAULTS = {
        vapidUrl: '/api/pushNotifications/vapid_public',
        subscribeUrl: '/api/pushNotifications/subscribe',
        serviceWorkerUrl: '/pushit_service_worker.js',
        serviceWorkerScope: '/',
        userUuidInput: '',
        entityIdInput: '',
        eventTypes: '',
        button: '',
    };

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        return Uint8Array.from(raw, function (c) {
            return c.charCodeAt(0);
        });
    }

    function readInputValue(id) {
        if (!id) return '';
        var el = document.getElementById(id);
        if (!el) return '';
        return String(el.value || '').trim();
    }

    function extractVapidKey(payload) {
        if (!payload || typeof payload !== 'object') return '';
        return (
            (payload.vapidPublic && payload.vapidPublic.publicKey) ||
            payload.vapidPublic ||
            payload.publicKey ||
            ''
        );
    }

    function init(options) {
        var cfg = Object.assign({}, DEFAULTS, options || {});
        var button = cfg.button ? document.getElementById(cfg.button) : null;

        async function subscribe() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                throw new Error('Web Push is not supported in this browser');
            }

            var permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Notification permission denied');
            }

            var registration = await navigator.serviceWorker.register(cfg.serviceWorkerUrl, {
                scope: cfg.serviceWorkerScope,
            });
            await navigator.serviceWorker.ready;

            var vapidRes = await fetch(cfg.vapidUrl);
            if (!vapidRes.ok) {
                throw new Error('Could not load VAPID public key');
            }
            var vapidPayload = await vapidRes.json();
            var vapidKey = extractVapidKey(vapidPayload);
            if (!vapidKey) {
                throw new Error('VAPID public key missing');
            }

            var subscription =
                (await registration.pushManager.getSubscription()) ||
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                }));

            var body = {
                subscription: subscription.toJSON(),
            };

            var userUuid = readInputValue(cfg.userUuidInput);
            var entityId = readInputValue(cfg.entityIdInput);
            if (userUuid) body.userUUID = userUuid;
            if (entityId) body.entityId = entityId;
            if (cfg.eventTypes) body.eventTypes = cfg.eventTypes;

            var subRes = await fetch(cfg.subscribeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            if (!subRes.ok) {
                throw new Error('Subscribe API failed (' + subRes.status + ')');
            }

            return subscription;
        }

        if (button) {
            button.addEventListener('click', function () {
                button.disabled = true;
                subscribe()
                    .then(function () {
                        button.textContent = 'Notifications enabled';
                    })
                    .catch(function (err) {
                        button.disabled = false;
                        alert(err.message || String(err));
                    });
            });
        }

        return { subscribe: subscribe };
    }

    global.PushItSubscribe = { init: init };
})(typeof window !== 'undefined' ? window : this);
