<?php

declare(strict_types=1);

namespace Lits;

use Lits\Service\ActionService;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface as Session;
use Psr\Log\LoggerInterface as Logger;
use ReflectionClass;
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

    protected function cors(string $origin = '*'): void
    {
        $this->response = $this->response->withHeader(
            'Access-Control-Allow-Origin',
            $origin
        );
    }

    protected function json(): void
    {
        $this->response = $this->response->withHeader(
            'Content-Type',
            'application/json'
        );
    }

    /** @param array<string, string> $data */
    protected function setup(
        ServerRequest $request,
        Response $response,
        array $data
    ): void {
        $this->request = $request;
        $this->response = $response;
        $this->data = $data;

        /** @var Session|null */
        $session = $request->getAttribute(
            SessionMiddleware::SESSION_ATTRIBUTE
        );

        if (\is_null($session)) {
            return;
        }

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

        $this->session->set('messages', $message);
    }

    final protected function redirect(
        ?string $url = null,
        ?int $status = null
    ): void {
        if (\is_null($url)) {
            $url = $this->routeCollector->getBasePath();
        }

        $this->response = $this->response->withRedirect($url, $status);
    }

    /** @param array<string, mixed> $context */
    final protected function render(string $name, array $context = []): void
    {
        $context['messages'] = $this->messages;

        $this->response->getBody()->write(
            $this->template->render($name, $context)
        );
    }

    final protected function template(): string
    {
        $reflection = new ReflectionClass($this);
        $namespace = \explode('\\', $reflection->getNamespaceName());

        \array_shift($namespace);

        $filename = \strtolower(\str_replace(
            \implode('', \array_reverse($namespace)),
            '',
            $reflection->getShortName()
        ));

        return \strtolower(\implode(\DIRECTORY_SEPARATOR, $namespace)) .
            \DIRECTORY_SEPARATOR . $filename . '.html.twig';
    }

    /** @param array<string, string> $data */
    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $data
    ): Response {
        $this->setup($request, $response, $data);
        $this->action();

        return $this->response;
    }
}
