<?php

namespace App\Support;

use App\Http\Resources\EventResource;
use App\Http\Resources\IndustryResource;
use App\Http\Resources\LandingPageResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StandardResource;
use App\Http\Resources\VacancyResource;
use App\Models\Event;
use App\Models\Industry;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Standard;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Admin drafts shown on the website's preview server: the unsaved record is turned into the same
 * JSON the public API returns and kept for a short while under an unguessable link.
 */
class Preview
{
    public const MINUTES = 120;

    /** Opens the record as it is in the editor, without saving it. */
    public static function forRecord(Model $record): string
    {
        $record = clone $record;
        $record->updated_at = now();
        if ($record instanceof Post || $record instanceof Vacancy) {
            $record->published_at ??= now();
        }

        [$kind, $resource] = match (true) {
            $record instanceof LandingPage => ['ad', new LandingPageResource($record)],
            $record instanceof Page => ['page', new PageResource($record)],
            $record instanceof Service => ['service', new ServiceResource($record)],
            $record instanceof Post => ['post', new PostResource($record->unsetRelations()->load(['category', 'industryCategory', 'author']))],
            $record instanceof Event => ['event', new EventResource($record->unsetRelations()->load('industryCategory'))],
            $record instanceof Standard => ['standard', new StandardResource($record->unsetRelations()->load('category'))],
            $record instanceof Industry => ['industry', new IndustryResource($record)],
            $record instanceof Vacancy => ['vacancy', new VacancyResource($record)],
            default => throw new InvalidArgumentException('No preview for '.$record::class),
        };

        return self::store($kind, self::resolve($resource));
    }

    /**
     * @param  array<string, mixed>  $settings  the home page form, saved or not
     */
    public static function forHome(array $settings): string
    {
        $home = HomePage::forApi(HomePage::merge(HomePage::settings(), $settings));

        return self::store('home', ['home' => $home, 'process' => HomePage::process($home)]);
    }

    /**
     * Form state without files that are still being uploaded; those keep the saved value until the record is saved.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    public static function withoutUploads(array $state, array $saved = []): array
    {
        foreach ($state as $key => $value) {
            if ($value instanceof TemporaryUploadedFile) {
                $state[$key] = $saved[$key] ?? null;
            } elseif (is_array($value)) {
                $state[$key] = self::withoutUploads($value, is_array($saved[$key] ?? null) ? $saved[$key] : []);
            }
        }

        return $state;
    }

    /** @return array{kind: string, data: array<string, mixed>}|null */
    public static function get(string $token): ?array
    {
        return Cache::get(self::key($token));
    }

    /** @param  array<string, mixed>  $data */
    private static function store(string $kind, array $data): string
    {
        $token = Str::random(40);
        Cache::put(self::key($token), ['kind' => $kind, 'data' => $data], now()->addMinutes(self::MINUTES));

        return rtrim(config('instacertify.preview_url') ?: config('instacertify.frontend_url'), '/').'/preview/'.$token;
    }

    /** The resource as the website's detail endpoints return it. */
    private static function resolve(JsonResource $resource): array
    {
        $request = Request::create('/', 'GET', ['detail' => 1, 'content' => 1, 'full' => 1]);

        return json_decode(json_encode($resource->resolve($request)), true);
    }

    private static function key(string $token): string
    {
        return 'preview:'.$token;
    }
}
