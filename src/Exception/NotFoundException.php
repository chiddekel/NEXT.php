<?php

declare(strict_types=1);

namespace Next\Exception;

class NotFoundException extends NextException
{
    public function __construct(string $message = 'Page or Resource Not Found', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
