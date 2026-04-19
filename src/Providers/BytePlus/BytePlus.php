<?php

declare(strict_types=1);

namespace Prism\Prism\Providers\BytePlus;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Prism\Prism\Concerns\InitializesClient;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Exceptions\PrismRequestTooLargeException;
use Prism\Prism\Images\Request as ImagesRequest;
use Prism\Prism\Images\Response as ImagesResponse;
use Prism\Prism\Providers\BytePlus\Handlers\Images;
use Prism\Prism\Providers\Provider;

class BytePlus extends Provider
{
    use InitializesClient;

    public function __construct(
        #[\SensitiveParameter] public readonly string $apiKey,
        public readonly string $url,
    ) {}

    #[\Override]
    public function images(ImagesRequest $request): ImagesResponse
    {
        $handler = new Images($this->client(
            $request->clientOptions(),
            $request->clientRetry()
        ));

        return $handler->handle($request);
    }

    public function handleRequestException(string $model, RequestException $e): never
    {
        match ($e->response->getStatusCode()) {
            429 => throw PrismRateLimitedException::make(rateLimits: []),
            529 => throw PrismProviderOverloadedException::make('BytePlus'),
            413 => throw PrismRequestTooLargeException::make('BytePlus'),
            default => throw PrismException::providerRequestError($model, $e),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<mixed>  $retry
     */
    protected function client(array $options = [], array $retry = [], ?string $baseUrl = null): PendingRequest
    {
        return $this->baseClient()
            ->when($this->apiKey, fn ($client) => $client->withToken($this->apiKey))
            ->withOptions($options)
            ->when($retry !== [], fn ($client) => $client->retry(...$retry))
            ->baseUrl($baseUrl ?? $this->url);
    }
}
