<?php

declare(strict_types=1);

namespace Lits;

use Lits\Exception\FailedResponseException;
use Lits\Exception\InvalidTemplateException;
use Lits\Service\ActionService;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface as Session;
use Psr\Log\LoggerInterface as Logger;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Interfaces\RouteCollectorInterface as RouteCollector;

abstract class Action
{
    protected ServerRequest $request;
    protected Response $response;
    protected RouteCollector $routeCollector;
    protected Settings $settings;
    protected Logger $logger;
    protected Session $session;
    protected Template $template;

    /** @var array<string, string> */
    protected array $data = [];

    /** @var list<array{level: string, message: string}> */
    protected array $messages = [];

    /** @return list<string> */
    final protected function hierarchy(): array
    {
        $reflection = new \ReflectionClass($this);
        $hierarchy = \explode('\\', $reflection->getNamespaceName());

        \array_shift($hierarchy);

        $hierarchy[] = \str_replace(
            \implode('', \array_reverse($hierarchy)),
            '',
            $reflection->getShortName(),
        );

        return $hierarchy;
    }

    final protected function message(string $level, string $message): void
    {
        $messages = $this->session->get('messages');

        if (!\is_array($messages)) {
            $messages = [];
        }

        $messages[] = [
            'level' => $level,
            'message' => $message,
        ];

        $this->session->set('messages', $messages);
    }

    final protected function redirect(
        ?string $url = null,
        ?int $status = null,
    ): void {
        if (\is_null($url)) {
            $url = \rtrim($this->routeCollector->getBasePath(), '/') . '/';
        }

        $this->response = $this->response->withRedirect($url, $status);
    }

    /**
     * @param array<string, mixed> $context
     * @throws FailedResponseException
     * @throws InvalidTemplateException
     */
    final protected function render(string $name, array $context = []): void
    {
        $context['messages'] = $this->messages;

        try {
            $this->response->getBody()->write(
                $this->template->render($name, $context),
            );
        } catch (\RuntimeException $exception) {
            throw new FailedResponseException(
                'Could not write to the response body',
                0,
                $exception,
            );
        }
    }

    final protected function template(): string
    {
        return \strtolower(
            \implode(\DIRECTORY_SEPARATOR, $this->hierarchy()) .
            '.html.twig',
        );
    }

    abstract protected function action(): void;

    public function __construct(ActionService $service)
    {
        $this->request = $service->request;
        $this->response = $service->response;
        $this->routeCollector = $service->routeCollector;
        $this->settings = $service->settings;
        $this->logger = $service->logger;
        $this->session = $service->session;
        $this->template = $service->template;
    }

    /** @throws FailedResponseException */
    protected function cors(string $origin = '*'): void
    {
        try {
            $this->response = $this->response->withHeader(
                'Access-Control-Allow-Origin',
                $origin,
            );
        } catch (\Throwable $exception) {
            throw new FailedResponseException(
                'Could not add header to response',
                0,
                $exception,
            );
        }
    }

    /** @throws FailedResponseException */
    protected function json(): void
    {
        try {
            $this->response = $this->response->withHeader(
                'Content-Type',
                'application/json',
            );
        } catch (\Throwable $exception) {
            throw new FailedResponseException(
                'Could not add header to response',
                0,
                $exception,
            );
        }
    }

    /** @param array<string, string> $data */
    protected function setup(
        ServerRequest $request,
        Response $response,
        array $data,
    ): void {
        $this->request = $request;
        $this->response = $response;
        $this->data = $data;

        $session = $request->getAttribute(
            SessionMiddleware::SESSION_ATTRIBUTE,
        );

        if (\is_null($session)) {
            return;
        }

        \assert($session instanceof Session);
        $this->session = $session;

        $messages = $this->session->get('messages');

        if (\is_array($messages)) {
            /** @var array{level: string, message: string} $message */
            foreach ($messages as $message) {
                $this->messages[] = $message;
            }
        }

        $this->session->remove('messages');
    }

    protected function value(string $key, bool $post = false): ?string
    {
        $values = $post
            ? $this->request->getParsedBody()
            : $this->request->getQueryParams();

        if (
            \is_array($values) &&
            isset($values[$key]) &&
            \is_string($values[$key]) &&
            $values[$key] !== ''
        ) {
            return $values[$key];
        }

        return null;
    }

    /** @param array<string, string> $data */
    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $data,
    ): Response {
        $this->setup($request, $response, $data);
        $this->action();

        return $this->response;
    }
}
