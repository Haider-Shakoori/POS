<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateShopSettingsRequest;
use App\Models\ShopSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShopSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.shop', [
            'settings' => ShopSetting::query()->firstOrCreate(['id' => 1]),
        ]);
    }

    public function update(
        UpdateShopSettingsRequest $request,
        AuditLogger $audit,
    ): RedirectResponse {
        $settings = ShopSetting::query()->firstOrCreate(['id' => 1]);
        $old = $settings->only([
            'shop_name',
            'address',
            'phone',
            'default_locale',
            'receipt_locale',
            'receipt_size',
            'cash_variance_tolerance',
            'negative_stock_enabled',
            'discount_approval_threshold',
        ]);

        $settings->fill($request->validated());
        $settings->save();

        $audit->record(
            'settings.shop.updated',
            model: $settings,
            oldValues: $old,
            newValues: $settings->only(array_keys($old)),
            actor: $request->user(),
        );

        return back()->with('status', __('ui.settings_saved'));
    }
}
