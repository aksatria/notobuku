<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\AutocompleteTelemetry;

class TelemetryController extends Controller
{
    public function autocomplete(Request $request)
    {
        $payload = $request->all();
        $user = $request->user();
        $counts = is_array($payload['counts'] ?? null) ? $payload['counts'] : [];

        Log::info('Autocomplete telemetry', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'path' => $payload['path'] ?? null,
            'counts' => $counts,
            'ip' => $request->ip(),
            'ua' => substr((string) $request->userAgent(), 0, 120),
        ]);

        if (!empty($counts) && Schema::hasTable('autocomplete_telemetry')) {
            $day = now()->toDateString();
            $path = isset($payload['path']) ? trim((string) $payload['path']) : null;
            $institutionId = (int) ($user?->institution_id ?? 0);
            $institutionId = $institutionId > 0 ? $institutionId : null;

            foreach ($counts as $field => $count) {
                $field = trim((string) $field);
                $count = (int) $count;
                if ($field === '' || $count <= 0) {
                    continue;
                }

                $query = AutocompleteTelemetry::query()
                    ->where('day', $day)
                    ->where('field', $field)
                    ->where('path', $path)
                    ->where('user_id', $user?->id);

                $row = $query->first();
                if ($row) {
                    $row->increment('count', $count);
                } else {
                    AutocompleteTelemetry::query()->create([
                        'user_id' => $user?->id,
                        'institution_id' => $institutionId,
                        'field' => $field,
                        'path' => $path,
                        'count' => $count,
                        'day' => $day,
                    ]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }
}
