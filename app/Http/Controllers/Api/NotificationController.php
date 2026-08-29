<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Current user's notifications.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        $query =
            Notification::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'channel',
                    'system'
                );

        if (
            $request->boolean(
                'unread'
            )
        ) {
            $query->whereNull(
                'read_at'
            );
        }

        if (
            $request->filled(
                'type'
            )
        ) {
            $query->where(
                'type',
                $request->type
            );
        }

        $notifications =
            $query
                ->latest(
                    'created_at'
                )
                ->paginate(
                    min(
                        max(
                            (int)
                            $request->input(
                                'per_page',
                                20
                            ),
                            1
                        ),
                        100
                    )
                );

        return response()->json([
            'success' => true,

            'data' => $notifications,
        ]);
    }

    /**
     * Current user's unread notification count.
     */
    public function unreadCount(
        Request $request
    ): JsonResponse {
        $count =
            Notification::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->where(
                    'channel',
                    'system'
                )
                ->whereNull(
                    'read_at'
                )
                ->count();

        return response()->json([
            'success' => true,

            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Mark one notification as read.
     */
    public function markAsRead(
        Request $request,
        Notification $notification
    ): JsonResponse {
        $this->ensureOwnership(
            $request,
            $notification
        );

        if (
            ! $notification
                ->read_at
        ) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,

            'message' => 'Notification marked as read.',

            'data' => $notification
                ->fresh(),
        ]);
    }

    /**
     * Mark all notifications belonging to the user as read.
     */
    public function markAllAsRead(
        Request $request
    ): JsonResponse {
        Notification::query()
            ->where(
                'user_id',
                $request
                    ->user()
                    ->id
            )
            ->where(
                'channel',
                'system'
            )
            ->whereNull(
                'read_at'
            )
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,

            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Prevent reading another user's notification.
     */
    protected function ensureOwnership(
        Request $request,
        Notification $notification
    ): void {
        if (
            $notification
                ->user_id !==
            $request
                ->user()
                ->id
        ) {
            abort(
                403,
                'Unauthorized notification access.'
            );
        }
    }
}
