<?php

namespace NeuronAI\Providers\Anthropic;

use GuzzleHttp\Exception\GuzzleException;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\HandleWithTools;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Chat\Messages\Usage;
use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

class Anthropic implements AIProviderInterface
{
    use HandleWithTools;
    use HandleChat;
    use HandleStream;

    /**
     * The http client.
     *
     * @var Client
     */
    protected Client $client;

    /**
     * System instructions.
     * https://docs.anthropic.com/claude/docs/system-prompts#how-to-use-system-prompts
     *
     * @var ?string
     */
    protected ?string $system;

    /**
     * AnthropicClaude constructor.
     */
    public function __construct(
        protected string $key,
        protected string $model,
        protected string $version = '2023-06-01',
        protected int $max_tokens = 8192,
        protected ?float $temperature = null,
        protected ?array $stop_sequences = null,
    ) {
        $this->client = new Client([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->key,
                'anthropic-version' => $version,
            ]
        ]);
    }

    /**
     * @inerhitDoc
     */
    public function systemPrompt(?string $prompt): AIProviderInterface
    {
        $this->system = $prompt;
        return $this;
    }

    public function generateToolsPayload(): array
    {
        return \array_map(function (ToolInterface $tool) {
            \array_reduce($tool->getProperties(), function ($carry, ToolProperty $property) {
                $carry[$property->getName()] = [
                    'type' => $property->getType(),
                    'description' => $property->getDescription(),
                ];

                return $carry;
            }, []);

            return [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'input_schema' => [
                    'type' => 'object',
                    'properties' => !empty($properties) ? $properties : null,
                    'required' => $tool->getRequiredProperties(),
                ],
            ];
        }, $this->tools);
    }

    public function createToolMessage(array $content): Message
    {
        $tool = $this->findTool($content['name'])
            ->setInputs($content['input'])
            ->setCallId($content['id']);

        return new ToolCallMessage(
            [$content],
            [$tool] // Anthropic call one tool at a time. So we pass an array with one element.
        );
    }
}
