<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\LandingPageResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\StandardResource;
use App\Models\Author;
use App\Models\Event;
use App\Models\Faq;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Support\Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EditorialController extends Controller
{
    public function posts(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Post::TYPES))],
            'category' => ['nullable', 'string', 'max:100'],
            'author' => ['nullable', 'string', 'max:100'],
            'scheme' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return PostResource::collection(
            Post::published()
                ->with(['category', 'industryCategory', 'author', 'schemes'])
                ->when($validated['type'] ?? null, fn ($q, $type) => $q->ofType($type))
                ->when($validated['category'] ?? null, fn ($q, $slug) => $q->whereRelation('category', 'slug', $slug))
                ->when($validated['author'] ?? null, fn ($q, $slug) => $q->whereRelation('author', 'slug', $slug))
                ->when($validated['scheme'] ?? null, fn ($q, $slug) => $q->whereRelation('schemes', 'slug', $slug))
                ->orderByDesc('published_at')
                ->paginate($validated['per_page'] ?? 24)
                ->withQueryString()
        );
    }

    public function post(string $type, string $slug): PostResource
    {
        abort_unless(array_key_exists($type, Post::TYPES), 404);

        return new PostResource(
            Post::published()->ofType($type)->with(['category', 'author', 'schemes'])->where('slug', $slug)->firstOrFail()
        );
    }

    public function events(Request $request): AnonymousResourceCollection
    {
        $when = $request->validate(['when' => ['nullable', Rule::in(['upcoming', 'past', 'all'])]])['when'] ?? 'all';

        return EventResource::collection(
            Event::published()
                ->with(['industryCategory', 'schemes'])
                ->when($when === 'upcoming', fn ($q) => $q->upcoming()->orderBy('starts_at'))
                ->when($when !== 'upcoming', fn ($q) => $q->orderByDesc('starts_at'))
                ->get()
        );
    }

    public function event(string $slug): EventResource
    {
        return new EventResource(Event::published()->with('schemes')->where('slug', $slug)->firstOrFail());
    }

    public function standards(): JsonResponse
    {
        $categories = StandardCategory::published()
            ->orderBy('sort')
            ->with(['standards' => fn ($q) => $q->published()])
            ->get();

        return response()->json(['data' => $categories->map(fn (StandardCategory $c) => [
            'title' => $c->title,
            'slug' => $c->slug,
            'group' => $c->group,
            'groupLabel' => StandardCategory::GROUPS[$c->group] ?? null,
            'url' => $c->url(),
            'description' => $c->description,
            'image' => Images::url($c->image),
            'seo' => $c->seoMeta($c->title, $c->description),
            'standards' => StandardResource::collection($c->standards),
        ])]);
    }

    public function standard(string $path): StandardResource
    {
        return new StandardResource(
            Standard::published()->with('category')->where('path', trim($path, '/'))->firstOrFail()
        );
    }

    public function pages(): AnonymousResourceCollection
    {
        return PageResource::collection(Page::published()->orderBy('path')->get());
    }

    public function page(string $path): PageResource
    {
        return new PageResource(Page::published()->where('path', trim($path, '/'))->firstOrFail());
    }

    public function landingPages(): AnonymousResourceCollection
    {
        return LandingPageResource::collection(LandingPage::published()->orderBy('path')->get());
    }

    public function landingPage(string $path): LandingPageResource
    {
        return new LandingPageResource(LandingPage::published()->where('path', trim($path, '/'))->firstOrFail());
    }

    public function author(string $slug): JsonResponse
    {
        $author = Author::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            ...(new AuthorResource($author))->resolve(),
            'seo' => $author->seoMeta($author->name, $author->short_bio),
            'posts' => PostResource::collection($author->posts()->published()->with('category')->latest('published_at')->limit(50)->get()),
        ]]);
    }

    public function faqs(): JsonResponse
    {
        return response()->json([
            'data' => Faq::published()->orderBy('sort')->get(['question', 'answer', 'category']),
        ]);
    }

    /** Chains (A → B → C) are flattened so every old URL lands in one hop; loops are left out. */
    public function redirects(): JsonResponse
    {
        $redirects = Redirect::where('is_active', true)->get(['from_path', 'to_path', 'status_code']);
        $targets = $redirects->pluck('to_path', 'from_path')->all();

        return response()->json([
            'data' => $redirects
                ->map(fn (Redirect $r) => ($to = Redirect::resolve($r->from_path, $r->to_path, $targets))
                    ? ['from' => $r->from_path, 'to' => $to, 'status' => $r->status_code]
                    : null)
                ->filter()
                ->values(),
        ]);
    }
}
