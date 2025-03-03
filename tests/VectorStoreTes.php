<?php

namespace NeuronAI\Tests;

use NeuronAI\RAG\VectorStore\MemoryVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use PHPUnit\Framework\TestCase;

class VectorStoreTes extends TestCase
{
    protected function setUp(): void
    {
    }

    public function testVectorStoreInstance()
    {
        $store = new MemoryVectorStore();
        $this->assertInstanceOf(VectorStoreInterface::class, $store);
    }
}
