<?php

namespace App\Services;

use App\Models\NotifyTemplate;

class AiNotifyService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', '');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    /**
     * @return array{subject: string, body: string, variables: array<string>}
     */
    public function generateTemplate(string $purpose, string $channelType): array
    {
        if (empty($this->apiKey)) {
            return [
                'subject'   => "Your {$purpose} notification",
                'body'      => "Hi {{ name }},\n\nThis is a notification about {$purpose}.\n\nRegards,\nThe InfoDot Team",
                'variables' => ['name'],
            ];
        }

        $prompt = "You are a notification copywriter for InfoDot, a B2B SaaS ecosystem.\n\n" .
            "Write a {$channelType} notification template for: {$purpose}\n\n" .
            "Return JSON: {\"subject\": \"...\", \"body\": \"...\", \"variables\": [\"var1\", \"var2\"]}\n" .
            "Use {{ variable_name }} syntax for dynamic content. Keep it professional and concise.";

        $response = $this->callClaude($prompt);
        $decoded  = json_decode($response, true);

        return is_array($decoded)
            ? $decoded
            : ['subject' => '', 'body' => '', 'variables' => []];
    }

    private function callClaude(string $prompt): string
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => $this->model,
                'max_tokens' => 512,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $body, true);
        return $data['content'][0]['text'] ?? '{}';
    }
}
