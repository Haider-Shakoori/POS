<?php

namespace App\Support;

use App\Models\ShopSetting;
use Illuminate\Support\Facades\Schema;

class ShopSettingsStore
{
    private bool $loaded = false;

    private ?ShopSetting $settings = null;

    public function get(): ?ShopSetting
    {
        if ($this->loaded) {
            return $this->settings;
        }

        $this->loaded = true;

        if (! Schema::hasTable('shop_settings')) {
            return null;
        }

        return $this->settings = ShopSetting::query()->first();
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->get()?->getAttribute($key) ?? $default;
    }
}
