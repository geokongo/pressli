<?php namespace Controllers;

/**
 * Page Controller - Pressli CMS
 *
 * Default and catch-all controller for all public-facing content routing.
 * Acts as the front-end entry point for posts, pages, and dynamic content.
 *
 * RESPONSIBILITIES:
 *   - Serves as homepage controller (index method)
 *   - Resolves dynamic URL slugs to posts or pages (show method)
 *   - Implements Pressli Theme Data Contract (see contract.md)
 *   - Provides standardized data structure to all theme templates
 *   - Handles 404 errors for missing content
 *
 * ROUTING:
 *   /                    → index()  (homepage - shows latest post)
 *   /about-us            → show('about-us')
 *   /my-first-post       → show('my-first-post')
 *   /{any-slug}          → show($slug) (catch-all)
 *
 * DATA CONTRACT:
 *   Every template receives standardized data structure containing:
 *   - post/page: Main content with author, categories, comments
 *   - site: Site metadata (name, logo, tagline, url)
 *   - menu: Navigation items
 *   - recent_posts: 5 latest posts for sidebar
 *   - popular_posts: 5 most viewed posts
 *   - categories: All active categories
 *   - pagination: Pagination metadata (null on single views)
 *   - is_* flags: Template type detection (is_home, is_single, is_page, etc.)
 *
 * PERFORMANCE:
 *   - Optimized with JOINs to minimize database round trips
 *   - Posts: 6 queries total (1 JOIN for post+author, 5 supporting queries)
 *   - Pages: 3 queries total (simpler data requirements)
 *   - All queries use indexed columns for fast lookups
 *
 * CONTENT RESOLUTION ORDER:
 *   1. Try to match slug as published post
 *   2. If not found, try to match slug as published page
 *   3. If neither found, return 404
 *
 * RELATIONSHIPS:
 *   - Posts have many-to-many relationship with categories via post_taxonomies
 *   - Posts belong to one author (users table)
 *   - Posts have many comments (filtered to approved only)
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */

use Rackage\View;
use Rackage\Input;
use Rackage\Path;
use Rackage\Registry;
use Rackage\Redirect;
use Lib\ThemeConfig;
use Models\PostModel;
use Lib\CoreProviders;
use Lib\PluginManager;
use Rackage\Controller;
use Models\SettingModel;
use Models\CommentModel;
use Lib\ProviderRegistry;
use Lib\ContentRegistry;
use Models\TaxonomyModel;
use Models\PostMetaModel;

class PageController extends Controller
{
    /**
     * Theme configuration instance
     *
     * Loaded from active theme's theme.json file.
     * Provides template paths and data provider requirements.
     * Used throughout controller to determine which providers to fetch.
     *
     * @var ThemeConfig
     */
    protected $themeConfig;

    /**
     * Site settings from database
     *
     * Cached autoload settings from settings table.
     * Loaded once per request to avoid repeated queries.
     * Contains site_title, active_theme, timezone, etc.
     *
     * @var array
     */
    protected $siteSettings;

    /**
     * Custom CSS generated from theme customizer settings
     *
     * CSS custom properties wrapped in <style> tags.
     * Loaded once per request from vault/tmp/theme-custom.css file.
     * NULL if user hasn't customized theme yet.
     * Injected into all theme templates via $customCSS variable.
     *
     * @var string|null
     */
    protected $customCSS;

    /**
     * Constructor - load settings, register providers, load plugins, load theme config
     *
     * Execution order:
     * 0. Check if CMS is installed (redirect to /install if not)
     * 1. Load autoload settings from database (single query)
     * 2. Get active theme name from settings
     * 3. Load theme configuration from theme.json
     * 4. Load custom CSS from theme customizer
     * 5. Register core data providers
     * 6. Load all active plugins (registers content types and routes)
     *
     * Called once per request before any controller methods execute.
     * Providers, plugins, and theme config become available to all methods.
     *
     * @return void
     */
    public function __construct()
    {
        // STEP 0: Check if CMS is installed (zero overhead - just array access)
        $settings = Registry::settings();

        if (!$settings['installed']) {
            Redirect::to('install');
        }

        // STEP 1: Load settings from database (cached for this request)
        $this->siteSettings = SettingModel::getAutoload();

        // STEP 2: Get active theme from settings (fallback to 'aurora')
        $activeTheme = $this->siteSettings['active_theme'];

        // STEP 3: Load theme configuration
        $this->themeConfig = ThemeConfig::load($activeTheme);

        // STEP 3.5: Prepend active theme to view paths (case-sensitive, works on Linux)
        // This allows theme templates to use relative paths like @extends('templates/layout')
        // instead of @extends('ThemeName/templates/layout'), making themes portable.
        // Prepends (not replaces) to preserve plugin/module view paths from config.
        $existingViewPaths = $settings['view_paths'] ?? [];
        $settings['view_paths'] = array_merge(['themes/' . $activeTheme], $existingViewPaths);
        Registry::settings($settings);

        // STEP 4: Load custom CSS from file (NULL if user hasn't customized yet)
        $cssPath = Path::vault() . 'tmp' . DIRECTORY_SEPARATOR . 'theme-custom.css';
        $this->customCSS = file_exists($cssPath) ? file_get_contents($cssPath) : null;

        // STEP 5: Register all core providers (recent_posts, popular_posts, categories, etc.)
        CoreProviders::register();

        // STEP 6: Load all active plugins (registers content types and routes)
        PluginManager::load();
    }
    /**
     * Display homepage
     *
     * Renders the most recent published post as the homepage. This is the default
     * landing page for the site when users visit the root URL (/).
     *
     * BEHAVIOR:
     *   - Fetches the latest published post (ordered by published_at DESC)
     *   - Retrieves all associated data (author, categories, comments)
     *   - Builds sidebar widgets (recent posts, popular posts, categories)
     *   - Returns 404 if no published posts exist
     *   - Sets is_home and is_single flags to true
     *
     * PERFORMANCE: 6 database queries (optimized)
     *   STEP 1: Post + author (1 query via JOIN)
     *   STEP 2: Post categories (1 query via junction table)
     *   STEP 3: Comment count (1 query)
     *   STEP 4: Recent posts for sidebar (1 query)
     *   STEP 5: Popular posts by views (1 query)
     *   STEP 6: All categories (1 query)
     *   STEP 7: Data assembly (in-memory, 0 queries)
     *
     * DATA PROVIDED TO TEMPLATE:
     *   - post: Full post object with author and categories array
     *   - site: Site metadata
     *   - menu: Navigation items
     *   - recent_posts: 5 latest posts
     *   - popular_posts: 5 most viewed posts
     *   - categories: All active categories
     *   - pagination: null (single post view)
     *   - is_home: true
     *   - is_single: true
     *   - is_archive: false
     *   - is_page: false
     *   - is_404: false
     *
     * TEMPLATE: themes/{active_theme}/views/post.php
     *
     * @return void Renders view or 404 error
     */
    public function index()
    {
        $homepageType = $this->siteSettings['homepage_type'] ?? 'posts';

        if ($homepageType === 'page') {
            $pageId = $this->siteSettings['homepage_page_id'] ?? null;

            if ($pageId) {
                $this->renderPageId($pageId, true);
                return;
            }
        }

        // Default: show post archive
        $this->renderArchive();
    }

    /**
     * Catch-all method for dynamic content routing
     *
     * Resolves URL slug to content in database. This method handles all dynamic
     * URLs that don't match explicit routes. Acts as the fallback/catch-all handler.
     *
     * UNIFIED CONTENT MODEL:
     *   Single query retrieves content regardless of type (post/page/custom).
     *   Type field determines rendering behavior and data requirements.
     *
     * CONTENT TYPE RENDERING:
     *   - type='post': Full post data (author, categories, comments, sidebar)
     *   - type='page': Minimal page data (no author/categories/comments)
     *   - custom types: Extensible for plugins (job, product, portfolio)
     *
     * PERFORMANCE:
     *   Posts: 6 queries (content+author, categories, comments, recent, popular, all categories)
     *   Pages: 3 queries (content, recent posts, all categories)
     *   ONE initial query to resolve content (not two separate table lookups)
     *
     * TEMPLATE SELECTION:
     *   - Posts render: themes/{active_theme}/views/post.php
     *   - Pages render: themes/{active_theme}/views/page.php
     *   - Custom types: themes/{active_theme}/views/{type}.php or fallback
     *   - Not found: 404 error page
     *
     * URL EXAMPLES:
     *   /about-us           → Resolves to content with slug "about-us", type "page"
     *   /my-first-post      → Resolves to content with slug "my-first-post", type "post"
     *   /contact            → Resolves to content with slug "contact" (any type)
     *   /nonexistent        → Returns 404
     *
     * @param string $slug URL slug from routing (e.g., "about-us", "my-first-post")
     * @return void Renders appropriate view or 404 error
     */
    public function show($slug)
    {
        // STEP 0: Parse top-level route segment
        $parts = explode('/', $slug);
        $topLevel = $parts[0];

        // STEP 1: Check core patterns first (category/tag archives)
        if ($topLevel === 'category') {
            $categorySlug = $parts[1] ?? null;
            if ($categorySlug) {
                $this->renderArchive($categorySlug, null);
                return;
            }
        }

        if ($topLevel === 'tag') {
            $tagSlug = $parts[1] ?? null;
            if ($tagSlug) {
                $this->renderArchive(null, $tagSlug);
                return;
            }
        }

        // STEP 2: Check plugin routes
        $routes = ContentRegistry::getRoutes();

        // 2a: Prefix routes — plugin owns a URL namespace, e.g. /jobs/...
        //     If the top-level segment matches, the plugin must handle it.
        //     A null result from run() means not found — show 404, do not fall through.
        if (isset($routes['prefix'][$topLevel])) {
            $pluginController = new $routes['prefix'][$topLevel]();
            $result = $pluginController->run($slug);

            if (!$result) {
                $this->show404();
                return;
            }

            $this->renderResult($result);
            return;
        }

        // 2b: Root handlers — plugins that inspect any slug and handle or pass.
        //     Executed in registration order; first non-null result wins.
        //     All null means no plugin claimed the URL — fall through to DB lookup.
        foreach ($routes['root'] as $controllerClass) {
            $pluginController = new $controllerClass();
            $result = $pluginController->run($slug);

            if ($result !== null) {
                $this->renderResult($result);
                return;
            }
        }

        // STEP 3: Fetch content (unified query for all types)
        $content = PostModel::select([
                'id', 'title', 'slug', 'content', 'excerpt', 'featured_image_id',
                'published_at', 'updated_at', 'view_count', 'type', 'template'
            ])
            ->leftJoin('users', 'author_id = id', ['first_name', 'last_name', 'email', 'avatar', 'bio', 'tagline'])
            ->leftJoin('media', 'featured_image_id = id', ['file_path as featured_image', 'alt_text as featured_image_alt', 'title as featured_image_title'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->first();

        // Content not found - show 404
        if (!$content) {
            $this->show404();
            return;
        }

        // Check if this page is designated as the posts archive page
        $postsPageId = $this->siteSettings['posts_page_id'] ?? null;
        if ($postsPageId && $content['id'] == $postsPageId) {
            $this->renderArchive();
            return;
        }

        // Route to appropriate renderer based on type
        if ($content['type'] === 'post') {
            $this->renderPost($content);
        }
        else if ($content['type'] === 'page') {
            $this->renderPage($content);
        }
        else {
            // Custom post type - attempt to render custom template or fallback to page
            $this->renderCustomType($content);
        }
    }

    /**
     * Render post with full blog data
     *
     * Fetches and displays post with author, categories, comments, and sidebar widgets.
     * Uses provider system to fetch only the data declared by the theme.
     *
     * @param array $content Post data from initial query
     * @return void
     */
    private function renderPost($content)
    {
        // STEP 2-6: Fetch data via providers (only what theme declares it needs)
        $context = ['post' => $content];

        // Get provider requirements from theme.json
        $providers = $this->themeConfig->getProviders('post');

        $providerData = ProviderRegistry::getBatch($providers, $context);

        // Build data contract for post
        $data = [
            'post' => [
                'id' => $content['id'],
                'title' => $content['title'],
                'slug' => $content['slug'],
                'content' => $content['content'],
                'excerpt' => $content['excerpt'],
                'featured_image' => $content['featured_image'] ?? null,
                'featured_image_alt' => $content['featured_image_alt'] ?? null,
                'featured_image_title' => $content['featured_image_title'] ?? null,
                'published_at' => $content['published_at'],
                'updated_at' => $content['updated_at'],
                'view_count' => $content['view_count'],
                'type' => $content['type'],
                'author_name' => trim(($content['first_name'] ?? '') . ' ' . ($content['last_name'] ?? '')),
                'author_email' => $content['email'] ?? null,
                'author_avatar' => $content['avatar'] ?? null,
                'author_bio' => $content['bio'] ?? null,
                'author_tagline' => $content['tagline'] ?? null,
                'author_title' => $content['title'] ?? null,
            ],
            'site' => [
                'name' => $this->siteSettings['site_title'] ?? 'Pressli',
                'tagline' => $this->siteSettings['site_tagline'] ?? 'A Modern CMS',
                'logo' => $this->siteSettings['site_logo'] ?? null,
                'favicon' => $this->siteSettings['site_favicon'] ?? null,
                'url' => $this->siteSettings['site_url'] ?? 'http://localhost',
            ],
            'pagination' => null,
            'is_home' => false,
            'is_single' => true,
            'is_archive' => false,
            'is_page' => false,
            'is_404' => false,
        ]; 

        // Merge all provider data - templates get exact provider names
        // (primary_menu, post_categories, post_comment_count, recent_posts, etc.)
        $data = array_merge($data, $providerData);

        // Get theme name and template from config
        $themeName = $this->themeConfig->getName();
        $template = $this->themeConfig->getTemplate('post');
        $template = str_replace('.php', '', $template);

        // Add custom CSS to data
        $data['customCSS'] = $this->customCSS;

        View::render($themeName . '/' . $template, $data);
    }

    /**
     * Render page with minimal data
     *
     * Displays static page content without author, categories, or comments.
     * Uses provider system to fetch sidebar widgets.
     *
     * @param array $content Page data from initial query
     * @return void
     */
    private function renderPage($content)
    {
        // STEP 2-3: Fetch data via providers (only what theme declares it needs)
        $context = ['page' => $content];

        // Get provider requirements from theme.json
        $providers = $this->themeConfig->getProviders('page', $content['template']);

        $providerData = ProviderRegistry::getBatch($providers, $context);

        // Build data contract for page
        $data = [
            'post' => [
                'id' => $content['id'],
                'title' => $content['title'],
                'slug' => $content['slug'],
                'content' => $content['content'],
                'excerpt' => $content['excerpt'] ?? null,
                'featured_image' => $content['featured_image'] ?? null,
                'featured_image_alt' => $content['featured_image_alt'] ?? null,
                'featured_image_title' => $content['featured_image_title'] ?? null,
                'published_at' => $content['published_at'],
                'updated_at' => $content['updated_at'],
                'type' => $content['type'],
            ],
            'site' => [
                'name' => $this->siteSettings['site_title'] ?? 'Pressli',
                'tagline' => $this->siteSettings['site_tagline'] ?? 'A Modern CMS',
                'logo' => $this->siteSettings['site_logo'] ?? null,
                'favicon' => $this->siteSettings['site_favicon'] ?? null,
                'url' => $this->siteSettings['site_url'] ?? 'http://localhost',
            ],
            'pagination' => null,
            'is_home' => false,
            'is_single' => false,
            'is_archive' => false,
            'is_page' => true,
            'is_404' => false,
        ];

        // Merge all provider data - templates get exact provider names
        $data = array_merge($data, $providerData);

        // Get theme name and template from config
        $themeName = $this->themeConfig->getName();
        $template = $this->themeConfig->getTemplate('page', $content['template']);
        $template = str_replace('.php', '', $template);

        // Add custom CSS to data
        $data['customCSS'] = $this->customCSS;

        View::render($themeName . '/' . $template, $data);
    }

    /**
     * Render custom post type
     *
     * Attempts to load custom template for plugin-registered post types.
     * Falls back to page rendering if custom template doesn't exist.
     *
     * @param array $content Custom content data from initial query
     * @return void
     */
    private function renderCustomType($content)
    {
        // Attempt to render custom template (e.g., job.php, product.php)
        // For now, fallback to page rendering
        // TODO: Check if custom template exists, load meta fields
        $this->renderPage($content);
    }

    /**
     * Render page by ID
     *
     * Fetches and displays specific page by ID. Used when homepage is set to
     * static page mode. Marks page as homepage if isHome flag is true.
     *
     * @param int $pageId Page ID to render
     * @param bool $isHome Whether this is homepage (default: false)
     * @return void
     */
    private function renderPageId($pageId, $isHome = false)
    {
        $content = PostModel::select([
                'id', 'title', 'slug', 'content', 'excerpt', 'featured_image_id',
                'published_at', 'updated_at', 'view_count', 'type', 'template'
            ])
            ->leftJoin('users', 'author_id = id', ['first_name', 'last_name', 'email', 'avatar'])
            ->leftJoin('media', 'featured_image_id = media.id', ['file_path as featured_image', 'alt_text as featured_image_alt', 'title as featured_image_title'])
            ->where('posts.id', $pageId)
            ->where('posts.type', 'page')
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->first();

        if (!$content) {
            $this->show404();
            return;
        }

        // Fetch data via providers
        $context = ['page' => $content];
        $providers = $this->themeConfig->getProviders('page', $content['template']);
        $providerData = ProviderRegistry::getBatch($providers, $context);

        $data = [
            'post' => [
                'id' => $content['id'],
                'title' => $content['title'],
                'slug' => $content['slug'],
                'content' => $content['content'],
                'excerpt' => $content['excerpt'] ?? null,
                'featured_image' => $content['featured_image'] ?? null,
                'featured_image_alt' => $content['featured_image_alt'] ?? null,
                'featured_image_title' => $content['featured_image_title'] ?? null,
                'published_at' => $content['published_at'],
                'updated_at' => $content['updated_at'],
                'type' => $content['type'],
            ],
            'site' => [
                'name' => $this->siteSettings['site_title'] ?? 'Pressli',
                'tagline' => $this->siteSettings['site_tagline'] ?? 'A Modern CMS',
                'logo' => $this->siteSettings['site_logo'] ?? null,
                'favicon' => $this->siteSettings['site_favicon'] ?? null,
                'url' => $this->siteSettings['site_url'] ?? 'http://localhost',
            ],
            'pagination' => null,
            'is_home' => $isHome,
            'is_single' => !$isHome,
            'is_archive' => false,
            'is_page' => true,
            'is_404' => false,
        ];

        // Merge all provider data - templates get exact provider names
        $data = array_merge($data, $providerData);

        $themeName = $this->themeConfig->getName();
        $template = $this->themeConfig->getTemplate('page', $content['template']);
        $template = str_replace('.php', '', $template);

        // Add custom CSS to data
        $data['customCSS'] = $this->customCSS;

        View::render($themeName . '/' . $template, $data);
    }

    /**
     * Render post archive with pagination
     *
     * Displays paginated list of latest posts. Used for homepage when set to
     * 'posts' mode, and for archive pages (categories, tags).
     *
     * @param string|null $categorySlug Category slug for filtering (optional)
     * @param string|null $tagSlug Tag slug for filtering (optional)
     * @return void
     */
    private function renderArchive($categorySlug = null, $tagSlug = null)
    {
        $page = (int) Input::get('page', 1);
        $perPage = (int) ($this->siteSettings['posts_per_page'] ?? 10);

        // Build query
        $query = PostModel::select([
                'id', 'title', 'slug', 'excerpt', 'featured_image_id', 'published_at', 'content'
            ])
            ->leftJoin('users', 'author_id = id', ['first_name', 'last_name'])
            ->leftJoin('media', 'featured_image_id = id', ['file_path as featured_image', 'alt_text as featured_image_alt', 'title as featured_image_title'])
            ->where('posts.type', 'post')
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->order('posts.published_at', 'desc');

        // Filter by category if provided
        if ($categorySlug) {
            $query->innerJoin('post_taxonomies', 'posts.id = post_taxonomies.post_id', [])
                ->innerJoin('taxonomies', 'post_taxonomies.taxonomy_id = taxonomies.id', ['name as taxonomy_name'])
                ->where('taxonomies.slug', $categorySlug)
                ->where('taxonomies.type', 'category');
        }

        // Filter by tag if provided
        if ($tagSlug) {
            $query->innerJoin('post_taxonomies', 'posts.id = post_taxonomies.post_id', [])
                ->innerJoin('taxonomies', 'post_taxonomies.taxonomy_id = taxonomies.id', ['name as taxonomy_name'])
                ->where('taxonomies.slug', $tagSlug)
                ->where('taxonomies.type', 'tag');
        }

        // Paginate
        $result = $query->paginate($perPage, $page);

        // Find taxonomy name it's an individual category/tags page
        $taxonomy = null;

        if($categorySlug !== null || $tagSlug !== null){
            if(count($result['data']) > 0){
               $taxonomy['name'] = $result['data'][0]['taxonomy_name'];
               $taxonomy['slug'] = $categorySlug || $tagSlug;
            }
        }

        // Fetch data via providers
        $context = [];
        $providers = $this->themeConfig->getProviders('archive');
        $providerData = ProviderRegistry::getBatch($providers, $context);

        $data = [
            'taxonomy' => $taxonomy,
            'posts' => $result['data'],
            'site' => [
                'name' => $this->siteSettings['site_title'] ?? 'Pressli',
                'tagline' => $this->siteSettings['site_tagline'] ?? 'A Modern CMS',
                'logo' => $this->siteSettings['site_logo'] ?? null,
                'favicon' => $this->siteSettings['site_favicon'] ?? null,
                'url' => $this->siteSettings['site_url'] ?? 'http://localhost',
            ],
            'pagination' => [
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'total' => $result['total'],
                'from' => $result['from'],
                'to' => $result['to'],
            ],
            'is_home' => !$categorySlug && !$tagSlug,
            'is_single' => false,
            'is_archive' => true,
            'is_page' => false,
            'is_404' => false,
        ];

        // Merge all provider data - templates get exact provider names
        $data = array_merge($data, $providerData);

        $themeName = $this->themeConfig->getName();
        $template = $this->themeConfig->getTemplate('archive');
        $template = str_replace('.php', '', $template);

        // Add custom CSS to data
        $data['customCSS'] = $this->customCSS;

        View::render($themeName . '/' . $template, $data);
    }

    /**
     * Render a result returned by a plugin controller's run() method
     *
     * Plugin controllers return an array from run() with three keys:
     *   - type:     content type string used to select a theme template (e.g. 'page', 'vendor')
     *   - data:     associative array of template variables
     *   - template: (optional) explicit template path; overrides theme template selection
     *
     * If the plugin provides a template path it is used directly. Otherwise the active
     * theme is asked for a template matching the content type, following the same
     * convention used by renderPost() and renderPage().
     *
     * @param array $result Array returned by a plugin controller's run() method
     * @return void
     */
    private function renderResult(array $result)
    {
        $type = $result['type'] ?? 'page';
        $data = $result['data'] ?? [];

        // Inject site metadata — plugins don't have access to siteSettings
        $data['site'] = [
            'name'    => $this->siteSettings['site_title']   ?? 'Pressli',
            'tagline' => $this->siteSettings['site_tagline'] ?? '',
            'logo'    => $this->siteSettings['site_logo']    ?? null,
            'favicon' => $this->siteSettings['site_favicon'] ?? null,
            'url'     => $this->siteSettings['site_url']     ?? 'http://localhost',
        ];

        $data['customCSS'] = $this->customCSS;

        // Ask the theme for a template matching the content type
        $themeName = $this->themeConfig->getName();
        $template  = $this->themeConfig->getTemplate($type);
        $template  = $themeName . '/' . str_replace('.php', '', $template);

        View::render($template, $data);
    }

    /**
     * Render 404 error page with theme layout
     *
     * Builds minimal data contract for 404 pages including site metadata,
     * navigation menu, recent posts, and categories for sidebar widgets.
     * Sets is_404 flag to true for template conditional logic.
     * Uses provider system to fetch sidebar widgets.
     *
     * TEMPLATE: themes/{active_theme}/views/errors/404.php
     * HTTP STATUS: 404 Not Found
     *
     * @return void
     */
    private function show404()
    {
        // Fetch data via providers
        $context = [];

        // Get provider requirements from theme.json
        $providers = $this->themeConfig->getProviders('404');

        $providerData = ProviderRegistry::getBatch($providers, $context);

        $data = [
            'site' => [
                'name' => $this->siteSettings['site_title'] ?? 'Pressli',
                'tagline' => $this->siteSettings['site_tagline'] ?? 'A Modern CMS',
                'logo' => $this->siteSettings['site_logo'] ?? null,
                'favicon' => $this->siteSettings['site_favicon'] ?? null,
                'url' => $this->siteSettings['site_url'] ?? 'http://localhost',
            ],
            'pagination' => null,
            'is_home' => false,
            'is_single' => false,
            'is_archive' => false,
            'is_page' => false,
            'is_404' => true,
        ];

        // Merge all provider data - templates get exact provider names
        $data = array_merge($data, $providerData);

        // Add custom CSS from theme customizer
        $data['customCSS'] = $this->customCSS;

        // Get theme name and template from config
        $themeName = $this->themeConfig->getName();
        $template = $this->themeConfig->getTemplate('404');
        $template = str_replace('.php', '', $template);

        View::render($themeName . '/' . $template, $data, 404);
    }
}