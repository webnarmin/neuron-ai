<?php

namespace NeuronAI\Tests;


use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\RAG\RAG;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolCallMessage;
use NeuronAI\Tools\ToolInterface;
use PHPUnit\Framework\TestCase;

class NeuronAITest extends TestCase
{
    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @throws \Exception
     */
    public function setUp(): void
    {
    }

    public function testAgentInstance()
    {
        $neuron = new Agent();
        $this->assertInstanceOf(AgentInterface::class, $neuron);

        $neuron = new RAG();
        $this->assertInstanceOf(Agent::class, $neuron);
    }

    public function testMessageInstance()
    {
        $tools = [
            new Tool('example', 'example')
        ];

        $this->assertInstanceOf(Message::class, new UserMessage(''));
        $this->assertInstanceOf(Message::class, new AssistantMessage(''));
        $this->assertInstanceOf(Message::class, new ToolCallMessage('', $tools));
    }

    public function testToolInstance()
    {
        $tool = new Tool('example', 'example');
        $this->assertInstanceOf(ToolInterface::class, $tool);
    }
}
