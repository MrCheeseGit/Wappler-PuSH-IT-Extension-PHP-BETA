<?php
/**
 * PuSH-IT — Web Push (VAPID) for Wappler Server Connect (PHP).
 * Requires: composer require minishlink/web-push
 * Env: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT
 */

namespace modules;

use lib\core\Module;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class pushit extends Module
{
    public function prepare($options, $name)
    {
        try {
            $raw = $this->resolveSubscriptionInput($options);
            $subscription = $this->parseSubscriptionJson($raw);

            if (!$subscription) {
                return [
                    'success' => false,
                    'valid' => false,
                    'error' => $this->describeMissingSubscription($options),
                ];
            }

            $userUUID = trim((string) $this->parseOptional($options->userUUID ?? null, ''));
            $entityId = trim((string) $this->parseOptional($options->entityId ?? null, ''));
            $eventTypes = trim((string) $this->parseOptional($options->eventTypes ?? null, ''));
            $userAgent = trim((string) $this->parseOptional($options->userAgent ?? null, ''));

            $subscriptionJson = is_string($raw) ? trim($raw) : json_encode($subscription);

            $insertRow = [
                'endpoint' => $subscription['endpoint'],
                'p256dh' => $subscription['keys']['p256dh'],
                'auth' => $subscription['keys']['auth'],
                'userUUID' => $userUUID,
                'entityId' => $entityId,
                'eventTypes' => $eventTypes,
                'userAgent' => $userAgent,
                'subscriptionJson' => $subscriptionJson,
            ];

            return [
                'success' => true,
                'valid' => true,
                'endpoint' => $insertRow['endpoint'],
                'p256dh' => $insertRow['p256dh'],
                'auth' => $insertRow['auth'],
                'userUUID' => $insertRow['userUUID'],
                'entityId' => $insertRow['entityId'],
                'eventTypes' => $insertRow['eventTypes'],
                'userAgent' => $insertRow['userAgent'],
                'subscriptionJson' => $insertRow['subscriptionJson'],
                'insertRow' => $insertRow,
                'rows' => [$insertRow],
            ];
        } catch (\Throwable $error) {
            return [
                'success' => false,
                'valid' => false,
                'error' => $error->getMessage(),
            ];
        }
    }

    public function send($options, $name)
    {
        try {
            $this->ensureWebPushLibrary();

            $mode = (string) $this->parseOptional($options->mode ?? null, 'single');
            $payload = $this->buildNotificationPayload($options);
            $payloadString = json_encode($payload);

            $sampleRow = null;
            if ($mode === 'fromQuery') {
                $sourceData = $this->parseOptional($options->sourceData ?? null, []);
                $rows = $this->parseGrid($sourceData);
                $sampleRow = $rows[0] ?? null;
            }

            $subscriptionField = $this->resolveColumnName($options->subscriptionColumn ?? null, $sampleRow);
            $endpointField = $this->resolveColumnName($options->endpointColumn ?? null, $sampleRow);
            $p256dhField = $this->resolveColumnName($options->p256dhColumn ?? null, $sampleRow);
            $authField = $this->resolveColumnName($options->authColumn ?? null, $sampleRow);
            $userIdField = $this->resolveColumnName($options->userIdColumn ?? null, $sampleRow);
            $entityIdField = $this->resolveColumnName($options->entityIdColumn ?? null, $sampleRow);

            $results = [];
            $sent = 0;
            $failed = 0;
            $noSubscription = 0;

            $webPush = $this->createWebPush();

            $pushOne = function ($subscription, array $context) use (
                &$results,
                &$sent,
                &$failed,
                &$noSubscription,
                $webPush,
                $payloadString
            ) {
                if (!$subscription) {
                    $noSubscription++;
                    $results[] = array_merge($context, [
                        'status' => 'no_subscription',
                        'error' => 'Missing or invalid subscription data',
                        'expired' => false,
                    ]);
                    return;
                }

                try {
                    $webPush->queueNotification(
                        Subscription::create($subscription),
                        $payloadString
                    );

                    foreach ($webPush->flush() as $report) {
                        if ($report->isSuccess()) {
                            $sent++;
                            $results[] = array_merge($context, [
                                'status' => 'sent',
                                'error' => '',
                                'expired' => false,
                            ]);
                        } else {
                            $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
                            $expired = in_array($statusCode, [404, 410], true);
                            $failed++;
                            $results[] = array_merge($context, [
                                'status' => 'failed',
                                'error' => $report->getReason() ?: 'Push send failed',
                                'expired' => $expired,
                                'statusCode' => $statusCode,
                            ]);
                        }
                    }
                } catch (\Throwable $error) {
                    $failed++;
                    $results[] = array_merge($context, [
                        'status' => 'failed',
                        'error' => $error->getMessage(),
                        'expired' => false,
                        'statusCode' => null,
                    ]);
                }
            };

            if ($mode === 'fromQuery') {
                $sourceData = $this->parseOptional($options->sourceData ?? null, []);
                $rows = $this->parseGrid($sourceData);

                if (!$rows) {
                    throw new \RuntimeException(
                        'Query results are empty. Add a database query step above, then bind its output to Query results.'
                    );
                }

                if (!$subscriptionField && !($endpointField && $p256dhField && $authField)) {
                    throw new \RuntimeException(
                        'Set Subscription column (JSON) or Endpoint + p256dh + auth columns for query mode.'
                    );
                }

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $subscription = $this->subscriptionFromRow(
                        $row,
                        $subscriptionField,
                        $endpointField,
                        $p256dhField,
                        $authField
                    );
                    $context = [
                        'userId' => $userIdField ? (string) $this->getRowFieldValue($row, $userIdField) : '',
                        'entityId' => $entityIdField ? (string) $this->getRowFieldValue($row, $entityIdField) : '',
                        'endpoint' => $subscription ? $subscription['endpoint'] : '',
                    ];
                    $pushOne($subscription, $context);
                }
            } else {
                $raw = $this->parseOptional($options->subscription ?? null, null);
                $subscription = $this->parseSubscriptionJson($raw);
                $pushOne($subscription, [
                    'userId' => '',
                    'entityId' => '',
                    'endpoint' => $subscription ? $subscription['endpoint'] : '',
                ]);
            }

            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'no_subscription' => $noSubscription,
                'total' => count($results),
                'results' => $results,
            ];
        } catch (\Throwable $error) {
            return [
                'success' => false,
                'error' => $error->getMessage(),
                'sent' => 0,
                'failed' => 0,
                'no_subscription' => 0,
                'total' => 0,
                'results' => [],
            ];
        }
    }

    private function ensureWebPushLibrary()
    {
        if (!class_exists(WebPush::class)) {
            throw new \RuntimeException(
                'minishlink/web-push is not installed. Run: composer require minishlink/web-push'
            );
        }
    }

    private function createWebPush()
    {
        $config = $this->getVapidConfig();

        return new WebPush([
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['publicKey'],
                'privateKey' => $config['privateKey'],
            ],
        ]);
    }

    private function getVapidConfig()
    {
        $publicKey = $this->env('VAPID_PUBLIC_KEY');
        $privateKey = $this->env('VAPID_PRIVATE_KEY');
        $subject = $this->env('VAPID_SUBJECT');

        if (!$publicKey || !$privateKey || !$subject) {
            throw new \RuntimeException(
                'Missing VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, or VAPID_SUBJECT. Add them in Wappler Project Settings → Environment.'
            );
        }

        return [
            'publicKey' => trim($publicKey),
            'privateKey' => trim($privateKey),
            'subject' => trim($subject),
        ];
    }

    private function env($name)
    {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        return null;
    }

    private function parseOptional($value, $default = null)
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $parsed = $this->app->parseObject($value);
        if ($parsed === null || $parsed === '') {
            return $default;
        }
        return $parsed;
    }

    private function parseGrid($value)
    {
        if (!$value) {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_array'));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $this->parseGrid($decoded) : [];
        }
        if (is_object($value)) {
            $arr = (array) $value;
            ksort($arr, SORT_NUMERIC);
            return array_values(array_filter($arr, 'is_array'));
        }
        return [];
    }

    private function resolveFieldName($input)
    {
        $s = trim((string) $input);
        if ($s === '') {
            return '';
        }

        if (preg_match('/\{\{([^}]+)\}\}/', $s, $m)) {
            $s = trim($m[1]);
        }

        if (strpos($s, '{{') !== false) {
            return '';
        }

        if (preg_match('/(?:^|[.\[])([a-zA-Z_][a-zA-Z0-9_]*)$/', $s, $m)) {
            return $m[1];
        }

        if (strpos($s, '.') !== false) {
            $parts = array_filter(explode('.', $s));
            return $parts ? end($parts) : '';
        }

        return $s;
    }

    private function resolveColumnName($input, $sampleRow)
    {
        $fromBinding = $this->resolveFieldName($input);
        $raw = trim((string) ($input ?? ''));

        if (is_array($sampleRow)) {
            if ($fromBinding && $this->rowHasField($sampleRow, $fromBinding)) {
                return $fromBinding;
            }
            if ($raw !== '' && strpos($raw, '{{') === false) {
                foreach ($sampleRow as $key => $cell) {
                    if ((string) $cell === $raw) {
                        return (string) $key;
                    }
                }
            }
        }

        return $fromBinding;
    }

    private function rowHasField(array $row, $fieldName)
    {
        if (!$fieldName) {
            return false;
        }
        if (array_key_exists($fieldName, $row)) {
            return true;
        }
        foreach (array_keys($row) as $key) {
            if (strtolower((string) $key) === strtolower($fieldName)) {
                return true;
            }
        }
        return false;
    }

    private function getRowFieldValue(array $row, $fieldName)
    {
        if (!$fieldName) {
            return '';
        }
        if (array_key_exists($fieldName, $row)) {
            return $row[$fieldName];
        }
        foreach ($row as $key => $value) {
            if (strtolower((string) $key) === strtolower($fieldName)) {
                return $value;
            }
        }
        return '';
    }

    private function parseSubscriptionJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $obj = $value;
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                return null;
            }
            $obj = $decoded;
        }

        if (!is_array($obj)) {
            return null;
        }

        $endpoint = trim((string) ($obj['endpoint'] ?? ''));
        if ($endpoint === '') {
            return null;
        }

        $keys = (isset($obj['keys']) && is_array($obj['keys'])) ? $obj['keys'] : $obj;
        $p256dh = trim((string) ($keys['p256dh'] ?? $keys['p256dh_key'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? $keys['auth_key'] ?? ''));

        if ($p256dh === '' || $auth === '') {
            return null;
        }

        return [
            'endpoint' => $endpoint,
            'expirationTime' => $obj['expirationTime'] ?? null,
            'keys' => ['p256dh' => $p256dh, 'auth' => $auth],
        ];
    }

    private function subscriptionFromRow(array $row, $subscriptionField, $endpointField, $p256dhField, $authField)
    {
        if ($subscriptionField) {
            return $this->parseSubscriptionJson($this->getRowFieldValue($row, $subscriptionField));
        }

        if ($endpointField && $p256dhField && $authField) {
            $endpoint = trim((string) $this->getRowFieldValue($row, $endpointField));
            $p256dh = trim((string) $this->getRowFieldValue($row, $p256dhField));
            $auth = trim((string) $this->getRowFieldValue($row, $authField));
            if ($endpoint === '' || $p256dh === '' || $auth === '') {
                return null;
            }
            return [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => $p256dh, 'auth' => $auth],
            ];
        }

        return $this->parseSubscriptionJson($row);
    }

    private function buildNotificationPayload($options)
    {
        $title = trim((string) $this->parseOptional($options->title ?? null, ''));
        $body = trim((string) $this->parseOptional($options->body ?? null, ''));

        if ($title === '' && $body === '') {
            throw new \RuntimeException('Notification title or body is required.');
        }

        $url = trim((string) $this->parseOptional($options->url ?? null, $this->env('PUSH_IT_DEFAULT_URL') ?: ''));
        $icon = trim((string) $this->parseOptional($options->icon ?? null, $this->env('PUSH_IT_DEFAULT_ICON') ?: ''));
        $tag = trim((string) $this->parseOptional($options->tag ?? null, ''));

        $data = $this->parseOptional($options->data ?? null, null);
        if (is_string($data) && trim($data) !== '') {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : ['value' => $data];
        }

        $payload = [
            'title' => $title !== '' ? $title : 'Notification',
            'body' => $body,
        ];
        if ($url !== '') {
            $payload['url'] = $url;
        }
        if ($icon !== '') {
            $payload['icon'] = $icon;
        }
        if ($tag !== '') {
            $payload['tag'] = $tag;
        }
        if (is_array($data)) {
            $payload['data'] = $data;
        }

        return $payload;
    }

    private function getRequestPost()
    {
        try {
            $post = $this->app->get('$_POST');
            if (is_array($post) || is_object($post)) {
                return (array) $post;
            }
        } catch (\Throwable $e) {
        }

        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }

        return null;
    }

    private function collectSubscriptionCandidates($options)
    {
        $post = $this->getRequestPost();
        $candidates = [];

        $bound = $this->parseOptional($options->subscription ?? null, null);
        if ($bound !== null && $bound !== '') {
            $candidates[] = $bound;
        }

        if (is_array($post)) {
            if (isset($post['subscription']) && $post['subscription'] !== '') {
                $candidates[] = $post['subscription'];
            }
            if (!empty($post['endpoint'])) {
                $candidates[] = $post;
            }
        }

        return ['post' => $post, 'candidates' => $candidates];
    }

    private function resolveSubscriptionInput($options)
    {
        $collected = $this->collectSubscriptionCandidates($options);

        foreach ($collected['candidates'] as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            if (is_string($candidate) && trim($candidate) === '[object Object]') {
                continue;
            }
            if ($this->parseSubscriptionJson($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function describeMissingSubscription($options)
    {
        $collected = $this->collectSubscriptionCandidates($options);
        $post = $collected['post'];
        $keys = is_array($post) ? array_keys($post) : [];

        if (!$keys) {
            return 'No POST body received. POST JSON with a subscription field (or paste subscription JSON into the Wappler API Run panel).';
        }

        return 'POST keys received: ' . implode(', ', $keys) . '. Expected subscription (object or JSON string) with endpoint and keys.p256dh / keys.auth.';
    }
}
