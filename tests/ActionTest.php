<?php

declare(strict_types=1);

namespace Next\Tests;

use Next\Action\ActionDispatcher;
use Next\Http\NextRequest;
use PHPUnit\Framework\TestCase;

class ActionTest extends TestCase
{
    public function testServerActionHeaderDispatch(): void
    {
        $dispatcher = new ActionDispatcher();

        $dispatcher->register('updateProfile', function (array $data, NextRequest $req): array {
            return [
                'updated' => true,
                'name' => $data['name'] ?? 'Anonymous',
            ];
        });

        $req = new NextRequest(
            method: 'POST',
            uri: '/api/action',
            headers: [
                'Content-Type' => 'application/json',
                'Next-Action' => 'updateProfile',
            ],
            body: '{"name":"Alice"}'
        );

        $this->assertTrue($dispatcher->isActionRequest($req));
        $res = $dispatcher->dispatch($req);

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode($res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('Alice', $body['data']['name']);
    }

    public function testServerActionFormDispatch(): void
    {
        $dispatcher = new ActionDispatcher();

        $dispatcher->register('deleteItem', function (array $data): string {
            return 'deleted:' . ($data['id'] ?? 'none');
        });

        $req = new NextRequest(
            method: 'POST',
            uri: '/items',
            headers: [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            body: '__next_action=deleteItem&id=42'
        );

        $this->assertTrue($dispatcher->isActionRequest($req));
        $res = $dispatcher->dispatch($req);

        $body = json_decode($res->getBody(), true);
        $this->assertSame('deleted:42', $body['data']);
    }

    public function testServerActionNotFound(): void
    {
        $dispatcher = new ActionDispatcher();
        $req = new NextRequest(
            method: 'POST',
            uri: '/',
            headers: ['Next-Action' => 'unknownAction']
        );

        $res = $dispatcher->dispatch($req);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function testServerActionCsrfMismatchBlocked(): void
    {
        $dispatcher = new ActionDispatcher();
        $dispatcher->register('transferFunds', fn() => 'transferred');

        $req = new NextRequest(
            method: 'POST',
            uri: '/api/action',
            headers: [
                'Origin' => 'https://malicious-attacker.com',
                'Host' => 'my-nextphp-app.com',
                'Next-Action' => 'transferFunds',
            ]
        );

        $res = $dispatcher->dispatch($req);
        $this->assertSame(403, $res->getStatusCode());
        $body = json_decode($res->getBody(), true);
        $this->assertStringContainsString('CSRF verification failed', $body['error']);
    }
}
