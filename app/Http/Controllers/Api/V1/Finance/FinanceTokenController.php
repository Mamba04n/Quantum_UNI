<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domain\Finanzas\Api\Services\FinanceApiAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\IssueFinanceTokenRequest;
use App\Infrastructure\Audit\AuditTrailService;
use App\Models\FinanceDeviceEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinanceTokenController extends Controller
{
    public function store(
        IssueFinanceTokenRequest $request,
        FinanceApiAccess $access,
        AuditTrailService $audit
    ): JsonResponse {
        $data = $request->validated();
        $email = Str::lower(trim($data['email']));
        $throttleKey = 'finance-token:'.Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['email' => 'No fue posible autenticar el dispositivo.']);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => 'No fue posible autenticar el dispositivo.']);
        }

        $abilities = $access->issueAbilities($user, $data['abilities']);
        $expiresAt = now()->addDays(max(1, (int) config('finance.api_token_ttl_days', 30)));

        $token = DB::transaction(function () use ($user, $data, $abilities, $expiresAt) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $enrollment = FinanceDeviceEnrollment::query()
                ->where('user_id', $lockedUser->id)
                ->where('device_id', $data['device_id'])
                ->lockForUpdate()
                ->first();

            if (! $enrollment
                || $enrollment->consumed_at !== null
                || $enrollment->expires_at->isPast()
                || ! hash_equals($enrollment->code_hash, hash('sha256', $data['enrollment_code']))
                || ! hash_equals($enrollment->device_name, $data['device_name'])) {
                throw ValidationException::withMessages([
                    'enrollment_code' => 'El código de enrolamiento no es válido o ya expiró.',
                ]);
            }

            $enrollment->forceFill(['consumed_at' => now()])->save();
            $lockedUser->tokens()->where('device_id', $data['device_id'])->delete();

            $issued = $lockedUser->createToken(
                'finance-device:'.$data['device_name'],
                $abilities,
                $expiresAt
            );
            $issued->accessToken->forceFill([
                'device_id' => $data['device_id'],
                'device_name' => $data['device_name'],
            ])->save();

            return $issued;
        });

        RateLimiter::clear($throttleKey);
        $audit->log($user->id, 'finance.api_token.issued', User::class, $user->id, [
            'device_id' => $data['device_id'],
            'device_name' => $data['device_name'],
            'abilities' => $abilities,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'device_id' => $data['device_id'],
                'device_name' => $data['device_name'],
                'abilities' => $abilities,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ], 201)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tokens = $user->tokens()->latest()->get()->map(fn ($token) => [
            'device_id' => $token->device_id,
            'device_name' => $token->device_name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $tokens]);
    }

    public function destroy(Request $request, string $deviceId, AuditTrailService $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('device_id', $deviceId)->delete();
        $audit->log($user->id, 'finance.api_token.revoked', User::class, $user->id, [
            'device_id' => $deviceId,
        ]);

        return response()->json(null, 204);
    }

    public function destroyAll(Request $request, AuditTrailService $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $revoked = $user->tokens()->delete();
        $audit->log($user->id, 'finance.api_token.revoked_all', User::class, $user->id, [
            'revoked_count' => $revoked,
        ]);

        return response()->json(null, 204);
    }
}
