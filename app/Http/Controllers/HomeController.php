<?php

namespace App\Http\Controllers;

use App\Support\FirstRunSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request, FirstRunSetup $setup): RedirectResponse
    {
        if ($setup->required()) {
            return redirect()->route('setup');
        }

        return $request->user()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}