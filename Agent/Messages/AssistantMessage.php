<?php

namespace App\Extensions\NeuronAI\Agent\Messages;

class AssistantMessage extends Message
{
    public function __construct(string $content)
    {
        parent::__construct(Message::ROLE_ASSISTANT, $content);
    }
}
