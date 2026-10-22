<?php

declare(strict_types=1);

namespace Next\Http;

class ResponseEmitter implements ResponseEmitterInterface
{
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent()) {
            echo $response->getBody();
            return;
        }

        // Set status code
        http_response_code($response->getStatusCode());

        // Emit headers
        foreach ($response->getHeaders()->toArray() as $name => $value) {
            header("{$name}: {$value}", false);
        }

        // Emit cookies
        foreach ($response->getCookies()->all() as $cookie) {
            header('Set-Cookie: ' . $cookie->toHeaderString(), false);
        }

        // Output body
        echo $response->getBody();
    }
}
