<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\ProfitLossPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProfitLossReportPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->role === User::ROLE_OWNER
            ? null
            : $user->roleDefinition()->with('permissions')->first();
        $canExport = $user->isActive() && (
            $user->role === User::ROLE_OWNER
            || ($role && $role->status !== Role::STATUS_DISABLED
                && $role->permissions->contains('code', 'report.export'))
        );

        return view('reports.profit-loss', [
            'periodPresets' => ProfitLossPeriod::presets(),
            'canExport' => $canExport,
        ]);
    }
}
