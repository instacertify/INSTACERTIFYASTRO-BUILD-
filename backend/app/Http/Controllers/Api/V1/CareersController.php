<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Resources\VacancyResource;
use App\Models\JobApplication;
use App\Models\Vacancy;
use App\Notifications\NewJobApplication;
use App\Support\MailSettings;
use App\Support\Privacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CareersController extends Controller
{
    public function vacancies(): AnonymousResourceCollection
    {
        return VacancyResource::collection(
            Vacancy::open()->orderBy('sort')->orderByDesc('published_at')->get()
        );
    }

    public function apply(StoreJobApplicationRequest $request): JsonResponse
    {
        $file = $request->file('cv');
        $extension = $file->guessExtension() ?: strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('cvs/'.now()->format('Y/m'), Str::uuid().'.'.$extension, JobApplication::cvDisk());

        $application = JobApplication::create([
            ...Arr::except($request->validated(), ['website', 'vacancy', 'cv']),
            'vacancy_id' => $request->vacancy?->id,
            'cv_path' => $path,
            'cv_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'cv_size' => $file->getSize(),
            'consent_version' => Privacy::version(),
            'consented_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        $notification = new NewJobApplication($application);
        if ($recipients = MailSettings::careersRecipients()) {
            Notification::route('mail', $recipients)->notify($notification);
        }
        if ($request->vacancy) {
            Notification::send($request->vacancy->hiringTeam, $notification);
        }

        return response()->json([
            'data' => [
                'id' => $application->uuid,
                'message' => 'Thank you for applying! Our team reviews every application and will contact you if your profile is a match.',
            ],
        ], 201);
    }
}
