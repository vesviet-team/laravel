<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Carbon;
use XMLWriter;

class FeedService
{
    /**
     * Generate Sitemap Index XML pointing to discrete sitemaps.
     */
    public function renderSitemapIndex(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $sitemaps = [
            url('/sitemap-products.xml'),
            url('/sitemap-categories.xml'),
            url('/sitemap-posts.xml'),
            url('/sitemap-pages.xml'),
        ];

        $now = Carbon::now()->toAtomString();

        foreach ($sitemaps as $loc) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $loc);
            $xml->writeElement('lastmod', $now);
            $xml->endElement();
        }

        $xml->endElement(); // sitemapindex
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Products Sitemap XML with Image extension.
     */
    public function renderProductsSitemap(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $products = Product::where('status', 'published')
            ->select(['id', 'name', 'slug', 'image_path', 'updated_at'])
            ->get();

        foreach ($products as $product) {
            $xml->startElement('url');
            $xml->writeElement('loc', route('products.show', $product->slug));
            $xml->writeElement('lastmod', $product->updated_at?->toAtomString() ?? now()->toAtomString());
            $xml->writeElement('changefreq', 'daily');
            $xml->writeElement('priority', '0.8');

            if ($product->image_path) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', asset('storage/'.$product->image_path));
                $xml->writeElement('image:title', $product->name);
                $xml->endElement();
            }

            $xml->endElement(); // url
        }

        $xml->endElement(); // urlset
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Categories Sitemap XML.
     */
    public function renderCategoriesSitemap(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $categories = Category::all();

        foreach ($categories as $category) {
            $xml->startElement('url');
            $xml->writeElement('loc', route('products.index', ['category' => $category->slug]));
            $xml->writeElement('lastmod', $category->updated_at?->toAtomString() ?? now()->toAtomString());
            $xml->writeElement('changefreq', 'weekly');
            $xml->writeElement('priority', '0.7');
            $xml->endElement();
        }

        $xml->endElement(); // urlset
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Blog Posts Sitemap XML.
     */
    public function renderPostsSitemap(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $posts = Post::where('status', 'published')
            ->select(['id', 'title', 'slug', 'featured_image', 'updated_at'])
            ->get();

        foreach ($posts as $post) {
            $xml->startElement('url');
            $xml->writeElement('loc', route('blog.show', $post->slug));
            $xml->writeElement('lastmod', $post->updated_at?->toAtomString() ?? now()->toAtomString());
            $xml->writeElement('changefreq', 'weekly');
            $xml->writeElement('priority', '0.6');

            if ($post->featured_image) {
                $xml->startElement('image:image');
                $xml->writeElement('image:loc', asset('storage/'.$post->featured_image));
                $xml->writeElement('image:title', $post->title);
                $xml->endElement();
            }

            $xml->endElement(); // url
        }

        $xml->endElement(); // urlset
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Static & CMS Pages Sitemap XML.
     */
    public function renderPagesSitemap(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $staticUrls = [
            ['url' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => route('products.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['url' => route('blog.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
            ['url' => route('about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['url' => route('contact'), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($staticUrls as $item) {
            $xml->startElement('url');
            $xml->writeElement('loc', $item['url']);
            $xml->writeElement('lastmod', now()->toAtomString());
            $xml->writeElement('changefreq', $item['changefreq']);
            $xml->writeElement('priority', $item['priority']);
            $xml->endElement();
        }

        $pages = Page::published()->get();

        foreach ($pages as $page) {
            $xml->startElement('url');
            $xml->writeElement('loc', route('page.show', $page->slug));
            $xml->writeElement('lastmod', $page->updated_at?->toAtomString() ?? now()->toAtomString());
            $xml->writeElement('changefreq', 'monthly');
            $xml->writeElement('priority', '0.5');
            $xml->endElement();
        }

        $xml->endElement(); // urlset
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Google Merchant Center Product Feed (XML/RSS 2.0).
     */
    public function renderGoogleMerchantFeed(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');
        $xml->writeElement('title', config('app.name', 'Sober Furniture').' - Google Shopping Catalog');
        $xml->writeElement('link', url('/'));
        $xml->writeElement('description', 'High-quality furniture, lighting and minimalist interior products');

        $products = Product::where('status', 'published')
            ->with(['category'])
            ->get();

        foreach ($products as $product) {
            $xml->startElement('item');
            $xml->writeElement('g:id', (string) $product->id);
            $xml->writeElement('g:title', $product->name);
            $xml->writeElement('g:description', $product->description ?? $product->name);
            $xml->writeElement('g:link', route('products.show', $product->slug));

            if ($product->image_path) {
                $xml->writeElement('g:image_link', asset('storage/'.$product->image_path));
            } else {
                $xml->writeElement('g:image_link', asset('images/placeholder.jpg'));
            }

            $availability = ($product->stock > 0) ? 'in_stock' : 'out_of_stock';
            $xml->writeElement('g:availability', $availability);
            $xml->writeElement('g:price', number_format($product->price, 0, '', '').' VND');
            $xml->writeElement('g:brand', config('app.name', 'Sober Furniture'));
            $xml->writeElement('g:condition', 'new');

            if ($product->category) {
                $xml->writeElement('g:product_type', $product->category->name);
            }

            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Generate Blog RSS 2.0 Feed.
     */
    public function renderBlogRssFeed(): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

        $xml->startElement('channel');
        $xml->writeElement('title', config('app.name', 'Sober Furniture').' - Blog & Design Hub');
        $xml->writeElement('link', route('blog.index'));
        $xml->writeElement('description', 'Latest interior design ideas, living space trends, and home decor guides.');
        $xml->writeElement('language', 'vi');

        $xml->startElement('atom:link');
        $xml->writeAttribute('href', url('/feed'));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        $posts = Post::where('status', 'published')
            ->with(['category'])
            ->latest('published_at')
            ->limit(30)
            ->get();

        foreach ($posts as $post) {
            $xml->startElement('item');
            $xml->writeElement('title', $post->title);
            $xml->writeElement('link', route('blog.show', $post->slug));
            $xml->writeElement('guid', route('blog.show', $post->slug));
            $xml->writeElement('pubDate', ($post->published_at ?? $post->created_at)->toRfc2822String());
            $xml->writeElement('description', $post->meta_description ?? substr(strip_tags($post->body ?? ''), 0, 300));
            if ($post->category) {
                $xml->writeElement('category', $post->category->name);
            }
            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
