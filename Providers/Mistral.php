<?php

namespace App\Extensions\NeuronAI\Providers;

use App\Extensions\NeuronAI\Agent\Messages\AssistantMessage;
use App\Extensions\NeuronAI\Agent\Messages\Message;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use League\CommonMark\Exception\LogicException;

class Mistral implements AIProviderInterface
{
    /**
     * The http client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * System instructions.
     * https://docs.mistral.ai/capabilities/completion/#chat-messages
     *
     * @var string
     */
    protected string $system;

    public function __construct(
        protected string $key,
        protected string $model,
        protected int $max_tokens
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.mistral.ai/v1',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->key}",
            ]
        ]);
    }

    public function systemPrompt(string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }

    public function chat(array|string $prompt): Message
    {
        if (\is_string($prompt)) {
            $prompt = [['role' => 'user', 'content' => $prompt]];
        }

        if (isset($this->system)) {
            \array_unshift($prompt, ['role' => 'system', 'content' => $this->system]);
        }

        $result = $this->client->post('/chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => $prompt,
            ]
        ])->getBody()->getContents();

        $result = \json_decode($result, true);

        // todo: attach usage to the response message

        // todo: Add tool call management

        return new AssistantMessage($result['choices'][0]['message']['content']);
    }

    public function contextWindow(): int
    {
        return $this->max_tokens;
    }

    public function maxCompletionTokens(): int
    {
        return $this->max_tokens;
    }

    public function setTools(array $tools): AIProviderInterface
    {
        throw new LogicException('Not implemented');
    }
}
