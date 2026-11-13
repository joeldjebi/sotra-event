<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminal utilisé par le personnel (en-têtes X-Device-Serial et X-App-Version).
 *
 * Un terminal enregistré et bloqué est refusé (blocage à distance, étape 2) ; sinon sa dernière
 * synchronisation et la version de l'app sont mises à jour. Un smartphone personnel non enregistré reste accepté.
 */
class ResolveStaffDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('sanctum');
        $serial = trim((string) $request->header('X-Device-Serial'));

        if ($staff instanceof Staff && $serial !== '') {
            $device = Device::query()->withoutGlobalScopes()->where('company_id', $staff->company_id)->where('serial_number', $serial)->first();

            if ($device?->status === DeviceStatus::Blocked) {
                throw new ApiException('Ce terminal est bloqué. Contactez votre exploitation.', 'DEVICE_BLOCKED', 403);
            }

            if ($device !== null) {
                $device->forceFill([
                    'last_sync_at' => now(),
                    'app_version' => $request->header('X-App-Version') ?: $device->app_version,
                ])->saveQuietly();

                $request->attributes->set('device', $device);
            }
        }

        return $next($request);
    }
}
