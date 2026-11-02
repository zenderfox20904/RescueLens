<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmergencyResource;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\Request;

/** System admin panel endpoints (role:admin). */
class AdminController extends Controller
{
    public function overview()
    {
        return ['data' => [
            'incidents_by_status' => Incident::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'incidents_by_category' => Incident::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'users_by_role' => User::selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
            'last_24h' => Incident::where('created_at', '>=', now()->subDay())->count(),
            'ai_assisted' => Incident::where('ai_assisted', true)->count(),
        ]];
    }

    public function resources()
    {
        return ['data' => EmergencyResource::orderBy('country_code')->orderBy('name')->get()];
    }

    /** New entries start unverified; they appear publicly only after an admin verifies them. */
    public function storeResource(Request $r)
    {
        $d = $r->validate([
            'country_code' => ['required', 'regex:/^[A-Za-z]{2}$/'],
            'name' => 'required|string|max:160', 'name_bn' => 'nullable|string|max:160',
            'phone' => ['required', 'regex:/^[0-9+ ]{2,20}$/'], 'category' => 'required|string|max:40',
            'jurisdiction' => 'nullable|string|max:120',
            'source_name' => 'required|string|max:200', 'source_url' => 'required|url|starts_with:https://',
        ]);
        $d['country_code'] = strtoupper($d['country_code']);
        $d['jurisdiction'] ??= $d['country_code'];
        $res = EmergencyResource::create($d + ['is_active' => true, 'verified_at' => null]);
        AuditLog::record('admin.resource_create', $res);
        return response()->json(['data' => $res], 201);
    }
}
