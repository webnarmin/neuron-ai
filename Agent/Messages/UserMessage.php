<?php

namespace App\Extensions\NeuronAI\Agent\Messages;

class UserMessage extends Message
{
    public function __construct(string $content)
    {
        parent::__construct(Message::ROLE_USER, $content);
    }
}
