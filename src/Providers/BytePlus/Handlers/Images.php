<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\BytePlus\Handlers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Prism\Prism\Images\Request;
use Prism\Prism\Images\Response;
use Prism\Prism\Images\ResponseBuilder;
use Prism\Prism\ValueObjects\GeneratedImage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;

class Images
{
    public function __construct(protected PendingRequest $client) {}

    public function handle(Request $request): Response
    {
        $response = $this->sendRequest($request);

        $data = $response->json();

        $images = $this->extractImages($data);

        $responseBuilder = new ResponseBuilder(
            usage: new Usage(
                promptTokens: 0,
                completionTokens: 0,
            ),
            meta: new Meta(
                id: data_get($data, 'id', 'img_'.bin2hex(random_bytes(8))),
                model: data_get($data, 'model', $request->model()),
                rateLimits: [],
            ),
            images: $images,
        );

        return $responseBuilder->toResponse();
    }

    protected function sendRequest(Request $request): ClientResponse
    {
        $payload = [
            'model' => $request->model(),
            'prompt' => $request->prompt(),
        ];

        $providerOptions = $request->providerOptions();

        $supportedOptions = [
            'num_images' => $providerOptions['num_images'] ?? $providerOptions['n'] ?? 1,
            'size' => $providerOptions['size'] ?? null,
            'response_format' => $providerOptions['response_format'] ?? 'url',
        ];

        $payload = array_merge($payload, array_filter($supportedOptions, fn ($v) => $v !== null));

        /** @var ClientResponse $response */
        $response = $this->client->post('images/generations', $payload);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return GeneratedImage[]
     */
    protected function extractImages(array $data): array
    {
        $images = [];

        foreach (data_get($data, 'data', []) as $imageData) {
            $images[] = new GeneratedImage(
                url: data_get($imageData, 'url'),
                base64: data_get($imageData, 'b64_json'),
                revisedPrompt: data_get($imageData, 'revised_prompt'),
            );
        }

        return $images;
    }
}
