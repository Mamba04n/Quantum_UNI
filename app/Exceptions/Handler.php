<?php

namespace App\Exceptions;

use App\Infrastructure\Audit\AuditContext;
use App\Infrastructure\Audit\BusinessAuditRecorder;
use App\Support\UserMessage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (UnauthorizedException $e, $request) {
            return response()->view('errors.403', ['exception' => $e], 403);
        });

        $this->reportable(function (Throwable $exception) {
            app(BusinessAuditRecorder::class)->record('application.exception', null, null, [
                'module' => 'security', 'exception' => $exception::class,
                'method' => request()->method(), 'path' => AuditContext::path(request()->path()),
                'ip' => request()->ip(),
            ]);
            request()->attributes->set('audit_exception_'.spl_object_id($exception), true);
            Log::error('application.exception', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'method' => request()->method(),
                'path' => request()->path(),
            ]);
        })->stop();
    }

    public function render($request, Throwable $e)
    {
        $status = $this->prepareException($e);
        $statusCode = match (true) {
            $e instanceof ValidationException => $e->status,
            $e instanceof AuthenticationException => 401,
            $e instanceof UnauthorizedException => 403,
            $status instanceof HttpExceptionInterface => $status->getStatusCode(),
            default => 500,
        };
        if (! $request->attributes->get('audit_exception_'.spl_object_id($e))
            && (in_array($statusCode, [401, 403, 405, 419, 422, 429], true) || $statusCode >= 500)) {
            app(BusinessAuditRecorder::class)->record('http.error', null, null, [
                'module' => 'security', 'status' => $statusCode,
                'method' => $request->method(), 'path' => AuditContext::path($request->path()),
                'exception' => $e::class, 'ip' => $request->ip(),
                'referer_path' => AuditContext::path(parse_url($request->headers->get('referer', ''), PHP_URL_PATH) ?: ''),
            ]);
        }
        if ($e instanceof UnauthorizedException) {
            return response()->view('errors.403', ['exception' => $e], 403);
        }

        $response = parent::render($request, $e);
        if ($request->expectsJson() && ! $request->is('api/*') && $response->getStatusCode() >= 400) {
            $body = json_decode($response->getContent(), true);
            if (is_array($body)) {
                $body['message'] = UserMessage::http($response->getStatusCode());
                $response->setContent(json_encode($body, JSON_UNESCAPED_UNICODE));
            }
        }

        return $response;
    }
}
