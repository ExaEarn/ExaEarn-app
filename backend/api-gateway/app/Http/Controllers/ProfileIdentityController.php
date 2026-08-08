<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileAppearanceRequest;
use App\Http\Requests\Profile\UploadProfileImageRequest;
use App\Models\User;
use App\Services\ProfileIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProfileIdentityController extends Controller
{
    public function __construct(private readonly ProfileIdentityService $profiles)
    {
    }

    public function identity(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
                'identity' => $this->profiles->identityFor($user, 'self'),
            ],
        ]);
    }

    public function avatars(): JsonResponse
    {
        return response()->json(['data' => $this->profiles->avatarCatalog()]);
    }

    public function selectAvatar(UpdateProfileAppearanceRequest $request): JsonResponse
    {
        try {
            $user = $this->profiles->selectAvatar(
                $request->user(),
                (string) $request->validated('avatar_id'),
                (string) ($request->validated('visibility') ?? $request->user()->profile_visibility ?? 'self'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Avatar saved.', 'data' => $this->userPayload($user)]);
    }

    public function useInitials(UpdateProfileAppearanceRequest $request): JsonResponse
    {
        try {
            $user = $this->profiles->useInitials(
                $request->user(),
                (string) ($request->validated('visibility') ?? $request->user()->profile_visibility ?? 'self'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Initials selected.', 'data' => $this->userPayload($user)]);
    }

    public function upload(UploadProfileImageRequest $request): JsonResponse
    {
        try {
            $user = $this->profiles->uploadImage(
                $request->user(),
                $request->file('image'),
                (array) ($request->validated('crop') ?? []),
                (string) ($request->validated('visibility') ?? 'self'),
            );
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'Image processing is unavailable') ? 503 : 422;
            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json(['message' => 'Profile image saved successfully.', 'data' => $this->userPayload($user)], 201);
    }

    public function removeImage(Request $request): JsonResponse
    {
        $user = $this->profiles->removeCustomImage($request->user());

        return response()->json(['message' => 'Profile image removed.', 'data' => $this->userPayload($user)]);
    }

    public function updateVisibility(UpdateProfileAppearanceRequest $request): JsonResponse
    {
        try {
            $user = $this->profiles->updateVisibility(
                $request->user(),
                (string) ($request->validated('visibility') ?? 'self'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Profile visibility updated.', 'data' => $this->userPayload($user)]);
    }

    public function image(Request $request, User $user, string $variant)
    {
        if (!in_array($variant, ['standard', 'thumbnail'], true)) {
            abort(404);
        }

        return $this->profiles->imageResponse($request->user(), $user, $variant);
    }

    private function userPayload(User $user): array
    {
        return array_merge($user->toArray(), [
            'profile_identity' => $this->profiles->identityFor($user, 'self'),
            'verification' => [
                'kyc_verified' => (bool) $user->kyc_verified_at,
                'kyc_level' => (int) ($user->kyc_level ?? 0),
                'verified_at' => optional($user->kyc_verified_at)->toIso8601String(),
            ],
        ]);
    }
}