<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $route = match ($request->user()->role) {
            UserRole::Pelapor => 'pelapor.dashboard',
            UserRole::Relawan => 'relawan.dashboard',
            UserRole::Koordinator => 'koordinator.dashboard',
        };

        return redirect()->route($route);
    }
}
