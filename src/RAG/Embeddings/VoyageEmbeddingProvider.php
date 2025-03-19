<?php

namespace NeuronAI\RAG\Embeddings;

use NeuronAI\RAG\Document;
use GuzzleHttp\Client;

class VoyageEmbeddingProvider extends AbstractEmbeddingProvider
{
    protected Client $client;

    protected string $baseUri = 'https://api.voyageai.com/v1/embedding';

    public function __construct(
        string $key,
        protected string $model
    ) {
        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ],
        ]);
    }

    public function embedText(string $text): array
    {
        $response = $this->client->post('', [
            'json' => [
                'model' => $this->model,
                'input' => $text,
            ]
        ]);

        $response = \json_decode($response->getBody()->getContents(), true);

        return $response['data'][0]['embedding'];
    }
}
