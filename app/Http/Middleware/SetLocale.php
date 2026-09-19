<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('pos.locales', ['en' => []]));
        $preferred = $request->user()?->preferred_locale;
        $sessionLocale = $request->session()->get('locale');

        $locale = in_array($preferred, $supported, true)
            ? $preferred
            : (in_array($sessionLocale, $supported, true) ? $sessionLocale : config('app.locale'));

        App::setLocale($locale);

        return $next($request);
    }
}
