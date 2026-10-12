<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationCvController extends Controller
{
    public function __invoke(JobApplication $application): StreamedResponse
    {
        Gate::authorize('view', $application);

        $disk = Storage::disk(JobApplication::cvDisk());
        abort_unless($application->cv_path && $disk->exists($application->cv_path), 404, 'This CV is no longer stored.');

        activity()->performedOn($application)->event('cv_viewed')->log('CV opened');

        $extension = pathinfo($application->cv_path, PATHINFO_EXTENSION);
        $filename = 'CV - '.Str::of($application->name)->ascii()->replaceMatches('/[^A-Za-z0-9 .-]/', '')->trim()->limit(60, '').".{$extension}";
        $pdf = $extension === 'pdf';

        return $disk->response($application->cv_path, $filename, [
            'Content-Type' => $pdf ? 'application/pdf' : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ], $pdf ? 'inline' : 'attachment');
    }
}
