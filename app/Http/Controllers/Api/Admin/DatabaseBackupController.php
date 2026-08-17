<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateDatabaseBackupJob;
use App\Jobs\RestoreDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $perPage =
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
            );

        $backups =
            DatabaseBackup::query()
                ->with([
                    'createdBy:id,name,email',
                    'restoredBy:id,name,email',
                ])
                ->latest()
                ->paginate(
                    $perPage
                );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $backups,
        ]);
    }

    public function store(
        Request $request,
        DatabaseBackupService $service
    ): JsonResponse {
        $backup =
            $service->createRecord(
                'manual',
                $request->user()
            );

        CreateDatabaseBackupJob::dispatch(
            $backup->id,
            'manual',
            $request->user()->id
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database backup has been queued.',

            'data' =>
                $backup,
        ], 202);
    }

    public function show(
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): JsonResponse {
        return response()->json([
            'success' =>
                true,

            'data' => [
                'backup' =>
                    $databaseBackup->load([
                        'createdBy:id,name,email',
                        'restoredBy:id,name,email',
                    ]),

                'file_exists' =>
                    Storage::disk(
                        $databaseBackup->disk
                    )->exists(
                        $databaseBackup->path
                    ),

                'verified' =>
                    $service->verify(
                        $databaseBackup
                    ),
            ],
        ]);
    }

    public function download(
        DatabaseBackup $databaseBackup
    ): StreamedResponse {
        abort_unless(
            $databaseBackup
                ->isRestorable(),
            422,
            'Backup is not available for download.'
        );

        $disk =
            Storage::disk(
                $databaseBackup->disk
            );

        abort_unless(
            $disk->exists(
                $databaseBackup->path
            ),
            404,
            'Backup file not found.'
        );

        return $disk->download(
            $databaseBackup->path,
            $databaseBackup->filename,
            [
                'Content-Type' =>
                    'application/gzip',
            ]
        );
    }

    public function restore(
        Request $request,
        DatabaseBackup $databaseBackup
    ): JsonResponse {
        $validated =
            $request->validate([
                'password' => [
                    'required',
                    'string',
                ],

                'confirmation' => [
                    'required',
                    'string',
                    'in:RESTORE DATABASE',
                ],
            ]);

        if (
            !Hash::check(
                $validated[
                    'password'
                ],
                $request
                    ->user()
                    ->password
            )
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Your administrator password is incorrect.',
            ], 422);
        }

        abort_unless(
            $databaseBackup
                ->isRestorable(),
            422,
            'This backup cannot be restored.'
        );

        RestoreDatabaseBackupJob::dispatch(
            $databaseBackup->id,
            $request
                ->user()
                ->id
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database restore has been queued. A safety backup will be created before restoration.',
        ], 202);
    }

    public function destroy(
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): JsonResponse {
        $service->delete(
            $databaseBackup
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Database backup deleted successfully.',
        ]);
    }
}