<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationLeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Read-only enquiries for tools holding an API key (Admin → Settings → API keys). */
class IntegrationLeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeKey($request);
        $filters = $request->validate([
            'created_after' => ['nullable', 'date'],
            'updated_after' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys(Lead::STATUSES))],
            'type' => ['nullable', Rule::in(array_keys(Lead::TYPES))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $leads = Lead::query()->with(['service', 'assignee'])
            ->when($filters['created_after'] ?? null, fn ($query, $date) => $query->where('created_at', '>', Carbon::parse($date)))
            ->when($filters['updated_after'] ?? null, fn ($query, $date) => $query->where('updated_at', '>', Carbon::parse($date)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return IntegrationLeadResource::collection($leads)->response()->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeKey($request);
        $lead = Lead::with(['service', 'assignee'])->where('uuid', $id)->firstOrFail();

        return (new IntegrationLeadResource($lead))->response()->header('Cache-Control', 'no-store, private');
    }

    /** The key's ability, and the person who created it must still be allowed to see leads. */
    private function authorizeKey(Request $request): void
    {
        abort_unless($request->user()?->tokenCan('leads:read') && $request->user()->canManageLeads(), 403, 'This API key cannot read enquiries.');
    }
}
