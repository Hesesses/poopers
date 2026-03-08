<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMagicLinkRequest;
use App\Http\Requests\VerifyMagicLinkRequest;
use App\Http\Resources\UserResource;
use App\Services\MagicLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private MagicLinkService $magicLinkService,
    ) {}

    public function sendMagicLink(SendMagicLinkRequest $request): JsonResponse
    {
        $this->magicLinkService->generate(
            email: $request->validated('email'),
            firstName: $request->validated('first_name'),
            lastName: $request->validated('last_name'),
        );

        return response()->json(['message' => 'Magic link sent to your email.']);
    }

    public function verify(VerifyMagicLinkRequest $request): JsonResponse
    {
        $user = $this->magicLinkService->verify($request->validated('token'));

        if (! $user) {
            return response()->json(['message' => 'Invalid or expired magic link.'], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully.']);
    }
}
