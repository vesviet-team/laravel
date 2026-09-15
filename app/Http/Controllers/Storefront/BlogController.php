<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\ShortcodeService;
use App\Services\TocService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Display the blog catalog with search, category filtering, and featured post.
     */
    public function index(Request $request)
    {
        $query = Post::published()
            ->with(['category', 'author', 'products'])
            ->latest('published_at');

        if ($request->filled('category')) {
            $query->byCategory($request->query('category'));
        }

        if ($request->filled('search')) {
            $query->search($request->query('search'));
        }

        $categories = PostCategory::active()
            ->ordered()
            ->withPublishedPostsCount()
            ->get();

        $featuredPost = Post::published()
            ->featured()
            ->with(['category', 'author'])
            ->latest('published_at')
            ->first();

        $posts = $query->paginate(9)->withQueryString();

        return view('storefront.blog.index', compact('posts', 'categories', 'featuredPost'));
    }

    /**
     * Display a single blog article with Table of Contents, contextual commerce, and related posts.
     */
    public function show(string $slug, TocService $tocService, ShortcodeService $shortcodeService)
    {
        $post = Post::where('slug', $slug)
            ->when(! auth()->check(), fn ($q) => $q->published())
            ->with([
                'category',
                'author',
                'products' => fn ($q) => $q->active()->with('category'),
            ])
            ->firstOrFail();

        $tocResult = $tocService->generate($post->body);
        $toc = $tocResult['toc'];
        $anchoredBody = $shortcodeService->parse($tocResult['html']);

        $relatedPosts = $post->getRelatedPosts(3);

        return view('storefront.blog.show', compact('post', 'toc', 'anchoredBody', 'relatedPosts'));
    }
}
