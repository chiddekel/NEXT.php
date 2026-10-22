<?php

declare(strict_types=1);

namespace Next\Action;

use Next\Http\NextRequest;
use Next\Http\NextResponse;
use RuntimeException;
use Throwable;

/**
 * Dispatches Next.js Server Actions triggered via form POST or header.
 */
class ActionDispatcher
{
    /** @var array<string, callable|ActionInterface> */
    private array $actions = [];

    public function register(string $id, callable|ActionInterface $action): self
    {
        $this->actions[$id] = $action;
        return $this;
    }

    public function isActionRequest(NextRequest $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return false;
        }

        if ($request->getHeader('Next-Action') !== null) {
            return true;
        }

        $formData = $request->formData();
        return isset($formData['__next_action']);
    }

    public function dispatch(NextRequest $request): NextResponse
    {
        // CSRF verification: check Origin if present
        $origin = $request->getHeader('Origin');
        $host = $request->getHeader('Host');
        if ($origin !== null && $host !== null) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $targetHost = parse_url('http://' . $host, PHP_URL_HOST);
            if ($originHost !== null && $targetHost !== null && strcasecmp($originHost, $targetHost) !== 0) {
                return NextResponse::json([
                    'error' => 'CSRF verification failed: Origin does not match Host.',
                ], 403);
            }
        }

        $actionId = $request->getHeader('Next-Action');
        $data = [];

        if ($actionId === null) {
            $formData = $request->formData();
            $actionId = (string) ($formData['__next_action'] ?? '');
            $data = $formData;
            unset($data['__next_action']);
        } else {
            // Can be JSON or form
            $json = $request->json();
            $data = is_array($json) ? $json : $request->formData();
        }

        if ($actionId === '' || !isset($this->actions[$actionId])) {
            return NextResponse::json([
                'error' => "Action '{$actionId}' not found.",
            ], 404);
        }

        $action = $this->actions[$actionId];

        try {
            if ($action instanceof ActionInterface) {
                $result = $action->execute($data, $request);
            } else {
                $result = $action($data, $request);
            }

            if ($result instanceof NextResponse) {
                return $result;
            }

            return NextResponse::json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            return NextResponse::json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
