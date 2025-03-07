<?php

namespace NeuronAI\Observability;

use Inspector\Inspector;
use Inspector\Models\Segment;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Events\InstructionsChanged;
use NeuronAI\Events\InstructionsChanging;
use NeuronAI\Events\MessageSaved;
use NeuronAI\Events\MessageSaving;
use NeuronAI\Events\MessageSending;
use NeuronAI\Events\MessageSent;
use NeuronAI\Events\ToolCalled;
use NeuronAI\Events\ToolCalling;
use NeuronAI\Events\VectorStoreResult;
use NeuronAI\Events\VectorStoreSearching;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolCallMessage;
use NeuronAI\Tools\ToolProperty;

class AgentMonitoring implements \SplObserver
{
    const SEGMENT_TYPE = 'neuron';
    const SEGMENT_COLOR = '#506b9b';

    /**
     * @var array<string, Segment>
     */
    protected $segments = [];

    public function __construct(protected Inspector $inspector) {}

    public function update(\SplSubject $subject, string $event = null, $data = null): void
    {
        $methods = [
            'rag-start' => 'start',
            'rag-stop' => 'stop',
            'chat-start' => "start",
            'chat-stop' => "stop",
            'message-saving' => 'messageSaving',
            'message-saved' => 'messageSaved',
            'message-sending' => "messageSending",
            'message-sent' => "messageSent",
            'tool-calling' => "toolCalling",
            'tool-called' => "toolCalled",
            'rag-vectorstore-searching' => "vectorStoreSearching",
            'rag-vectorstore-result' => "vectorStoreResult",
            'rag-instructions-changing' => "instructionsChanging",
            'rag-instructions-changed' => "instructionsChanged",
        ];

        if (!\is_null($event) && \array_key_exists($event, $methods) && $subject instanceof \NeuronAI\AgentInterface) {
            $method = $methods[$event];
            $this->$method($subject, $event, $data);
        }
    }

    public function start(\NeuronAI\AgentInterface $agent, string $event, $data = null)
    {
        if (!$this->inspector->isRecording()) {
            return;
        }

        $entity = $this->getEventEntity($event);
        $class = get_class($agent);

        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($class);
        } elseif ($this->inspector->canAddSegments() && $entity !== 'chat') {
            $this->segments[
                $entity.$class
            ] = $this->inspector->startSegment(self::SEGMENT_TYPE.'-'.$entity, $entity.':'.$class)
                ->setColor(self::SEGMENT_COLOR);
        }
    }

    public function stop(\NeuronAI\AgentInterface $agent, string $event, $data = null)
    {
        $entity = $this->getEventEntity($event);
        $class = get_class($agent);

        if (\array_key_exists($entity.$class, $this->segments)) {
            $this->segments[$entity.$class]
                ->setContext($this->getContext($agent))
                ->end();
        }
    }

    public function messageSaving(\NeuronAI\AgentInterface $agent, string $event, MessageSaving $data)
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $label = json_encode($data->message->getContent());

        $this->segments[
            $this->getMessageId($data->message).'-save'
        ] = $this->inspector
            ->startSegment(self::SEGMENT_TYPE.'-chathistory', "save( {$label} )")
            ->setColor(self::SEGMENT_COLOR);
    }

    public function messageSaved(\NeuronAI\AgentInterface $agent, string $event, MessageSaved $data)
    {
        $id = $this->getMessageId($data->message).'-save';

        if (\array_key_exists($id, $this->segments)) {
            $this->segments[$id]
                ->addContext('Message', \array_merge($data->message->jsonSerialize(), $data->message->getUsage() ? [
                    'usage' => [
                        'input_tokens' => $data->message->getUsage()->inputTokens,
                        'output_tokens' => $data->message->getUsage()->outputTokens,
                    ]
                ] : []))
                ->end();
        }
    }

    public function messageSending(\NeuronAI\AgentInterface $agent, string $event, MessageSending $data)
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }
        
        $label = json_encode($data->message->getContent());

        $this->segments[
            $this->getMessageId($data->message).'-send'
        ] = $this->inspector
            ->startSegment(self::SEGMENT_TYPE.'-chat', "chat( {$label} )")
            ->setColor(self::SEGMENT_COLOR);
    }

    public function messageSent(\NeuronAI\AgentInterface $agent, string $event, MessageSent $data)
    {
        $id = $this->getMessageId($data->message).'-send';

        if (\array_key_exists($id, $this->segments)) {
            $this->segments[$id]
                ->setContext($this->getContext($agent))
                ->end();
        }
    }

    public function toolCalling(\NeuronAI\AgentInterface $agent, string $event, ToolCalling $data)
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $this->segments[
            $data->tool->getName()
        ] = $this->inspector
            ->startSegment(self::SEGMENT_TYPE.'-tools', "tool:{$data->tool->getName()}")
            ->setColor(self::SEGMENT_COLOR);
    }

    public function toolCalled(\NeuronAI\AgentInterface $agent, string $event, ToolCalled $data)
    {
        if (\array_key_exists($data->tool->getName(), $this->segments)) {
            $this->segments[$data->tool->getName()]
                ->addContext('Tool', $data->tool->jsonSerialize())
                ->end();
        }
    }

    public function vectorStoreSearching(\NeuronAI\AgentInterface $agent, string $event, VectorStoreSearching $data)
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $id = \md5($data->question->getContent());

        $this->segments[
            $id
        ] = $this->inspector
            ->startSegment(self::SEGMENT_TYPE.'-vector-search', "vectorSearch( {$data->question->getContent()} )")
            ->setColor(self::SEGMENT_COLOR);
    }

    public function vectorStoreResult(\NeuronAI\AgentInterface $agent, string $event, VectorStoreResult $data)
    {
        $id = \md5($data->question->getContent());

        if (\array_key_exists($id, $this->segments)) {
            $this->segments[$id]
                ->addContext('Data', [
                    'question' => $data->question->getContent(),
                    'documents' => \count($data->documents)
                ])
                ->end();
        }
    }

    public function instructionsChanging(\NeuronAI\AgentInterface $agent, string $event, InstructionsChanging $data)
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $id = \md5($data->instructions);

        $this->segments[
            $id
        ] = $this->inspector
            ->startSegment(self::SEGMENT_TYPE.'-instructions', "instructions( {$data->instructions} )")
            ->setColor(self::SEGMENT_COLOR);
    }

    public function instructionsChanged(\NeuronAI\AgentInterface $agent, string $event, InstructionsChanged $data)
    {
        $id = \md5($data->previous);

        if (\array_key_exists($id, $this->segments)) {
            $this->segments[$id]
                ->addContext('Instructions', [
                    'previous' => $data->previous,
                    'current' => $data->current
                ])
                ->end();
        }
    }

    public function getEventEntity(string $event): string
    {
        return explode('-', $event)[0];
    }

    protected function getContext(\NeuronAI\AgentInterface $agent): array
    {
        return [
            'Agent' => [
                'instructions' => $agent->instructions(),
                'provider' => get_class($agent->provider()),
            ],
            'Tools' => \array_map(function (Tool $tool) {
                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'properties' => \array_map(function (ToolProperty $property) {
                        return $property->toArray();
                    }, $tool->getProperties()),
                ];
            }, $agent->tools()??[]),
            'Messages' => $agent->resolveChatHistory()->getMessages(),
        ];
    }

    public function getMessageId(Message $message): string
    {
        $content = $message->getContent();
        
        if (!is_string($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        
        return \md5($content.$message->getRole());
    }
}
