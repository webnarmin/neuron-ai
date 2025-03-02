<?php

namespace App\Extensions\NeuronAI\Agent;

use App\Extensions\NeuronAI\Agent\Messages\Message;
use App\Extensions\NeuronAI\Agent\Messages\UserMessage;
use App\Extensions\NeuronAI\Agent\Tools\Tool;
use App\Extensions\NeuronAI\Agent\Tools\ToolCallMessage;
use App\Extensions\NeuronAI\Agent\Tools\ToolInterface;
use App\Extensions\NeuronAI\Providers\AIProviderInterface;
use App\Extensions\NeuronAI\Providers\Embeddings\EmbeddingsProviderInterface;

class NeuronAgent implements \SplSubject
{
    /**
     * The AI provider instance.
     *
     * @var AIProviderInterface
     */
    protected AIProviderInterface $provider;

    /**
     * The embeddings provider.
     *
     * @var EmbeddingsProviderInterface
     */
    protected EmbeddingsProviderInterface $embeddingsProvider;

    /**
     * @var AbstractChatHistory
     */
    protected AbstractChatHistory $chatHistory;

    /**
     * The system message.
     *
     * @var ?string
     */
    protected ?string $instructions = null;

    /**
     * The prompt of the agent.
     *
     * @var Message
     */
    protected Message $prompt;

    /**
     * Registered tools.
     *
     * @var array<Tool>
     */
    protected array $tools = [];

    /**
     * @var array<\SplObserver>
     */
    private array $observers = [];

    public function __construct()
    {
        // A special event group for observers that want to listen to all events.
        $this->observers["*"] = [];

        $this->notify('agent:start');
    }

    public static function make(...$args): static
    {
        return new static(...$args);
    }

    public function setProvider(AIProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function provider(): AIProviderInterface
    {
        return $this->provider;
    }

    public function setEmbeddingsProvider(EmbeddingsProviderInterface $provider): self
    {
        $this->embeddingsProvider = $provider;
        return $this;
    }

    public function embeddings(): EmbeddingsProviderInterface
    {
        return $this->embeddingsProvider;
    }

    /**
     * Execute the chat.
     *
     * @param Message|null $message
     * @return Message
     */
    public function run(?Message $message = null): Message
    {
        if (!is_null($message)) {
            $this->resolveChatHistory()->addMessage($message);
        }

        $this->notify('message:sending', $message);

        $response = $this->provider()
            ->systemPrompt($this->instructions())
            ->setTools($this->tools())
            ->chat(
                $this->resolveChatHistory()->toArray()
            );

        $this->notify('message:sent', $response);

        if ($response instanceof ToolCallMessage) {
            $this->notify('tool:calling', $response);
            $toolResult = $response->getTool()->execute($response->getInputs());
            $this->notify('tool:called', $toolResult);

            // Execute the tool and submit the response to the LLM again.
            $this->run(new UserMessage($toolResult));
        }

        $this->notify('agent:stop');
        return $response;
    }

    public function calculateTotalUsage(): int
    {
        return $this->resolveChatHistory()->calculateTotalUsage();
    }

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function setInstructions(string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }

    public function addMessage(string|Message $message): self
    {
        $this->resolveChatHistory()->addMessage(
            is_string($message) ? new UserMessage($message) : $message
        );

        return $this;
    }

    public function withMessages(array $messages): self
    {
        $this->resolveChatHistory()->clear();

        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                throw new \Exception('Messages must be instance of '.Message::class.' class');
            }
            $this->resolveChatHistory()->addMessage($message);
        }

        return $this;
    }

    /**
     * Get the list of tools.
     *
     * @return array<Tool>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * Add a tool.
     *
     * @param ToolInterface $tool
     * @return $this
     */
    public function addTool(ToolInterface $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    public function withChatHistory(AbstractChatHistory $chatHistory): self
    {
        $this->chatHistory = $chatHistory;
        return $this;
    }

    public function resolveChatHistory(): AbstractChatHistory
    {
        if (!isset($this->chatHistory)) {
            $this->chatHistory = new InMemoryChatHistory();
        }

        return $this->chatHistory;
    }

    private function initEventGroup(string $event = "*"): void
    {
        if (!isset($this->observers[$event])) {
            $this->observers[$event] = [];
        }
    }

    private function getEventObservers(string $event = "*"): array
    {
        $this->initEventGroup($event);
        $group = $this->observers[$event];
        $all = $this->observers["*"];

        return array_merge($group, $all);
    }

    public function attach(\SplObserver $observer, string $event = "*"): self
    {
        $this->initEventGroup($event);

        $this->observers[$event][] = $observer;
        return $this;
    }

    public function detach(\SplObserver $observer, string $event = "*"): void
    {
        foreach ($this->getEventObservers($event) as $key => $s) {
            if ($s === $observer) {
                unset($this->observers[$event][$key]);
            }
        }
    }

    public function notify(string $event = "*", $data = null): void
    {
        // Broadcasting the '$event' event";
        foreach ($this->getEventObservers($event) as $observer) {
            $observer->update($this, $event, $data);
        }
    }
}
