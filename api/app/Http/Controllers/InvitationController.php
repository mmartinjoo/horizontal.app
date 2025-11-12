<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Mail\TenantInvitation;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    /**
     * Create and send a new invitation.
     */
    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $plainToken = Invitation::generateToken();
        $hashedToken = Invitation::hashToken($plainToken);

        $invitation = Invitation::create([
            'email' => $request->input('email'),
            'token' => $hashedToken,
            'invited_by_user_id' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new TenantInvitation($invitation, $plainToken));

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    /**
     * Get invitation details by token.
     */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::findByToken($token);

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found.',
            ], 404);
        }

        if (! $invitation->isValid()) {
            $message = $invitation->isExpired()
                ? 'This invitation has expired.'
                : 'This invitation has already been accepted.';

            return response()->json([
                'message' => $message,
            ], 410);
        }

        return response()->json([
            'invitation' => [
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }
}
