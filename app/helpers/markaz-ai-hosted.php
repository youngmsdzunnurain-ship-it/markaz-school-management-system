<?php
declare(strict_types=1);

/**
 * Build a bounded, role-scoped chat transcript. Only user/assistant turns
 * from the authenticated conversation are accepted; callers cannot inject
 * additional system instructions through stored history.
 */
function markazAiBuildHostedMessages(string $message, array $scope, array $workspaceContext, array $history): array
{
    $messages = [[
        'role' => 'system',
        'content' => markazAiBuildInstructions($scope, '', $workspaceContext)
            . ' You are a conversational assistant: resolve short follow-ups using the recent conversation. '
            . 'When asked about activity, distinguish recorded events from unrecorded work. '
            . 'If the available context does not contain the requested facts, say so clearly. '
            . 'Never fabricate names, dates, actions, counts or records.'
    ]];

    $recent = array_slice($history, -10);
    foreach ($recent as $turn) {
        $role = (string) ($turn['role'] ?? '');
        $content = trim((string) ($turn['content'] ?? ''));
        if (!in_array($role, ['user', 'assistant'], true) || $content === '' || mb_strlen($content) > 4000) {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $content];
    }

    $messages[] = ['role' => 'user', 'content' => $message];
    return $messages;
}

/**
 * Call Cloudflare Workers AI only when explicit server-side credentials exist.
 * Returns null on configuration/provider failure so the caller can use the
 * deterministic, verified-data fallback without exposing provider details.
 *
 * The optional transport is a test seam; production uses cURL over HTTPS.
 */
function markazAiGenerateHostedAnswer(array $messages, ?callable $transport = null): ?string
{
    $accountId = trim((string) (getenv('MARKAZ_AI_CLOUDFLARE_ACCOUNT_ID') ?: ''));
    $apiToken = trim((string) (getenv('MARKAZ_AI_CLOUDFLARE_API_TOKEN') ?: ''));
    if ($accountId === '' || $apiToken === '') {
        return null;
    }

    $url = 'https://api.cloudflare.com/client/v4/accounts/'
        . rawurlencode($accountId) . '/ai/run/@cf/google/gemma-4-26b-a4b-it';
    $headers = [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json',
    ];
    $payload = ['messages' => $messages, 'max_tokens' => 900, 'temperature' => 0.2];
    $timeout = 20;

    try {
        if ($transport !== null) {
            $response = $transport($url, $headers, $payload, $timeout);
        } else {
            if (!function_exists('curl_init')) {
                return null;
            }
            $ch = curl_init($url);
            if ($ch === false) return null;
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($body) || $status < 200 || $status >= 300) return null;
            $response = json_decode($body, true);
        }
    } catch (Throwable $error) {
        error_log('MARKAZ AI hosted provider request failed.');
        return null;
    }

    if (!is_array($response) || ($response['success'] ?? false) !== true) return null;
    $answer = trim((string) ($response['result']['response'] ?? ''));
    if ($answer === '' || mb_strlen($answer) > 12000) return null;
    return $answer;
}
