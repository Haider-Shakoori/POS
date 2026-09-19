<?php

namespace App\Http\Middleware;

use App\Models\ShopSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('pos.locales', ['en' => []]));
        $preferred = $request->user()?->preferred_locale;
        $sessionLocale = $request->session()->get('locale');

        $shopLocale = Schema::hasTable('shop_settings')
            ? ShopSetting::query()->value('default_locale')
            : null;
        $fallback = in_array($shopLocale, $supported, true) ? $shopLocale : config('app.locale');

        $locale = in_array($preferred, $supported, true)
            ? $preferred
            : (in_array($sessionLocale, $supported, true) ? $sessionLocale : $fallback);

        App::setLocale($locale);

        return $next($request);
    }
}
