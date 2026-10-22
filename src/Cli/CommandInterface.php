<?php

declare(strict_types=1);

namespace Next\Cli;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int;
}
