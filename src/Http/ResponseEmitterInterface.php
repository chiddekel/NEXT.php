<?php

declare(strict_types=1);

namespace Next\Http;

interface ResponseEmitterInterface
{
    public function emit(ResponseInterface $response): void;
}
