<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['pt_BR', 'en'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $sessionLocale = $request->session()->get('locale');

        if (in_array($sessionLocale, self::SUPPORTED, true)) {
            return $sessionLocale;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        return $preferred ?: config('app.locale');
    }
}
