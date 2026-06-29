<?php

namespace App\Services;

use App\Models\Team;

class AiInsightService
{
    public function __construct(
        private readonly string $apiKey = '',
        private readonly bool   $mock   = false,
    ) {}

    /**
     * Generate cross-platform intelligence recommendations for a team.
     *
     * @return array{recommendations: array<array{title: string, rationale: string, priority: string, engine: string}>, tokens_used: int}
     */
    public function generateRecommendations(Team $team, string $context): array
    {
        if ($this->mock || empty($this->apiKey)) {
            return $this->mockRecommendations($team);
        }

        $response = $this->callClaude($team, $context);

        return $response;
    }

    /**
     * Answer a cross-platform intelligence question using the Universal Intelligence Graph.
     */
    public function answerIntelligenceQuery(Team $team, string $question): string
    {
        if ($this->mock || empty($this->apiKey)) {
            return $this->mockIntelligenceAnswer($question);
        }

        return $this->callClaudeQuery($team, $question);
    }

    private function callClaude(Team $team, string $context): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => $this->buildRecommendationPrompt($team, $context),
                    ],
                ],
            ]),
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $body) {
            return $this->mockRecommendations($team);
        }

        $data = json_decode($body, true);
        $text = $data['content'][0]['text'] ?? '';

        preg_match('/\[.*\]/s', $text, $matches);
        $items = json_decode($matches[0] ?? '[]', true) ?? [];

        return [
            'recommendations' => $items,
            'tokens_used'     => $data['usage']['input_tokens'] + $data['usage']['output_tokens'],
        ];
    }

    private function callClaudeQuery(Team $team, string $question): string
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 512,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => "You are the intelligence layer of the Dot ecosystem for team: {$team->name}.\n\n{$question}",
                    ],
                ],
            ]),
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || ! $body) {
            return $this->mockIntelligenceAnswer($question);
        }

        $data = json_decode($body, true);

        return $data['content'][0]['text'] ?? $this->mockIntelligenceAnswer($question);
    }

    private function buildRecommendationPrompt(Team $team, string $context): string
    {
        return <<<PROMPT
You are the Dot.Analytics intelligence engine for {$team->name}.

Based on the following cross-platform data context:
{$context}

Generate 3 actionable intelligence recommendations. Return JSON array only, no markdown:
[
  {
    "title": "...",
    "rationale": "...",
    "priority": "high|medium|low",
    "engine": "business|financial|people|operational|predictive|risk|decision"
  }
]
PROMPT;
    }

    private function mockRecommendations(Team $team): array
    {
        return [
            'recommendations' => [
                [
                    'title'     => 'Fleet utilisation below optimal threshold',
                    'rationale' => 'Vehicle idle time increased 23% this week. Scheduling optimisation could reduce fuel costs by an estimated 12%.',
                    'priority'  => 'high',
                    'engine'    => 'operational',
                ],
                [
                    'title'     => 'Three customer accounts show churn signals',
                    'rationale' => 'Support tickets increased, invoice payments delayed, and engagement dropped. Proactive outreach recommended within 48 hours.',
                    'priority'  => 'high',
                    'engine'    => 'risk',
                ],
                [
                    'title'     => 'Payroll cost spike predicted next month',
                    'rationale' => 'Overtime hours trending up across two departments. Hiring one additional operator would reduce overtime costs by an estimated 18%.',
                    'priority'  => 'medium',
                    'engine'    => 'financial',
                ],
            ],
            'tokens_used' => 0,
        ];
    }

    private function mockIntelligenceAnswer(string $question): string
    {
        return 'Based on cross-platform data from your connected Dot platforms, the analysis shows correlated signals across fleet operations, financial records, and workforce data. Connect more data sources to unlock deeper intelligence.';
    }
}
