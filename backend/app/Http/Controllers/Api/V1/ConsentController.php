<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConsentLog;
use App\Support\Privacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consent_id' => ['required', 'uuid'],
            'action' => ['required', Rule::in(array_keys(ConsentLog::ACTIONS))],
            'choices' => ['required', 'array'],
            ...collect(ConsentLog::CATEGORIES)->mapWithKeys(fn ($c) => ["choices.{$c}" => ['required', 'boolean']])->all(),
            'version' => ['required', 'string', 'max:20'],
            'page' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', Rule::in(['en', 'hi'])],
        ]);

        $log = ConsentLog::create([
            'consent_id' => $data['consent_id'],
            'action' => $data['action'],
            'choices' => ['necessary' => true, ...collect($data['choices'])->only(ConsentLog::CATEGORIES)->map(fn ($v) => (bool) $v)->all()],
            'policy_version' => $data['version'],
            'page' => $data['page'] ?? null,
            'language' => $data['language'] ?? 'en',
            'ip' => $request->ip(),
            'ip_hash' => Privacy::hashIp($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return response()->json(['data' => ['id' => $log->id, 'currentVersion' => Privacy::version()]], 201);
    }
}
