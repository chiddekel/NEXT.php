<?php

declare(strict_types=1);

namespace Next\Exception;

class HttpException extends NextException
{
    public function __construct(
        int $statusCode = 500,
        string $message = 'Internal Server Error',
        private readonly array $headers = []
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
