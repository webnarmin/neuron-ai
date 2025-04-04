<?php

namespace NeuronAI\RAG\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use NeuronAI\RAG\Document;

class PineconeVectorStore implements VectorStoreInterface
{
    protected Client $client;

    public function __construct(
        string $key,
        protected string $indexUrl,
        string $version = '2025-01'
    ) {
        $this->client = new Client([
            'base_uri' => $this->indexUrl,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => $key,
                'X-Pinecone-API-Version' => $version,
            ]
        ]);
    }

    public function addDocument(Document $document): void
    {
        $this->addDocuments([$document]);
    }

    public function addDocuments(array $documents): void
    {
        $this->client->post("vectors/upsert", [
            RequestOptions::JSON => [
                'vectors' => \array_map(function (Document $document) {
                    return [
                        'id' => $document->id??\uniqid(),
                        'values' => $document->embedding,
                    ];
                }, $documents)
            ]
        ]);
    }

    public function similaritySearch(array $embedding, int $k = 4): iterable
    {
        $result = $this->client->post("query", [
            RequestOptions::JSON => [
                'namespace' => '',
                'vector' => $embedding,
                'topK' => $k,
            ]
        ])->getBody()->getContents();

        $result = \json_decode($result, true);

        return \array_map(function (array $item) {
            $document = new Document();
            $document->id = $item['id'];
            $document->embedding = $item['values'];
            return $document;
        }, $result['matches']);
    }
}
