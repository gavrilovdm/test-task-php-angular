<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ValidationException;
use App\Http\JsonResponder;
use App\Middleware\JwtAuthMiddleware;
use App\Service\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $email = \is_array($body) && \is_string($body['email'] ?? null) ? $body['email'] : '';
        $password = \is_array($body) && \is_string($body['password'] ?? null) ? $body['password'] : '';

        $errors = [];
        if ('' === trim($email)) {
            $errors['email'] = 'Email обязателен';
        }
        if ('' === $password) {
            $errors['password'] = 'Пароль обязателен';
        }
        if ([] !== $errors) {
            throw new ValidationException('Некорректные данные', $errors);
        }

        $token = $this->auth->login($email, $password);

        return JsonResponder::json($response, [
            'token' => $token->token,
            'expiresAt' => $token->expiresAt->format(\DATE_ATOM),
            'user' => ['email' => $token->email],
        ]);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::json($response, ['email' => $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE)]);
    }
}
