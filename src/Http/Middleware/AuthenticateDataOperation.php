<?php

namespace UsmanAhmed\ImportExport\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use UsmanAhmed\ImportExport\Auth\GuestAccess;
use UsmanAhmed\ImportExport\Auth\GuestPrincipal;
use UsmanAhmed\ImportExport\Auth\SystemPrincipal;

final class AuthenticateDataOperation
{
    public function handle(Request $request, Closure $next): mixed
    {
        $guard = config('import-export.authentication_guard');
        $actor = $guard === null ? $request->user() : auth()->guard($guard)->user();
        if ($actor === null) {
            $actor = app(GuestAccess::class)->resolve($request->bearerToken());
        }
        if ($actor === null || $actor instanceof SystemPrincipal) {
            throw new AuthenticationException;
        }
        if ($actor instanceof GuestPrincipal && is_string($request->route('resource')) && $actor->resource !== $request->route('resource')) {
            abort(403);
        }
        $request->setUserResolver(fn () => $actor);

        return $next($request);
    }
}
