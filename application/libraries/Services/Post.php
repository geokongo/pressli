<?php namespace Lib\Services;

/**
 * Post Service - Pressli CMS
 *
 * Business logic layer for managing blog posts with full content management
 * features. Provides reusable functions for creating, updating, deleting posts
 * with validation, slug generation, category relationships, status transitions,
 * and publishing workflow.
 *
 * USAGE CONTEXTS:
 * - Admin controllers (web UI for managing posts)
 * - API controllers (REST endpoints for headless CMS)
 * - CLI commands (bulk post operations, imports)
 * - Plugins (programmatic post creation)
 * - Cron jobs (scheduled post publishing)
 *
 * VALIDATION RULES:
 * - Title is required (cannot be empty)
 * - Slug must be unique among non-deleted posts
 * - Slug auto-generated from title if not provided
 * - Status must be: draft, published, scheduled
 * - Scheduled posts require published_at date
 * - Slug contains only lowercase letters, numbers, hyphens
 *
 * STATUS WORKFLOW:
 * - draft → published (sets published_at to now)
 * - draft → scheduled (requires published_at in future)
 * - published → draft (clears published_at)
 * - any → any (maintains existing published_at if transitioning back to published)
 *
 * CATEGORY RELATIONSHIPS:
 * - Categories stored in post_taxonomies junction table
 * - Update operation syncs categories (deletes old, inserts new)
 * - List operations include categories via JOIN
 *
 * ERROR HANDLING:
 * All methods throw ServiceException on validation or business logic errors.
 * Controllers catch exceptions and format response appropriately (flash messages or JSON).
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */

use Rackage\Date;
use Models\PostModel;
use Models\TaxonomyModel;
use Models\PostTaxonomyModel;
use Lib\Exceptions\ServiceException;

class Post
{
    /**
     * Create new post
     *
     * Validates input, generates slug if not provided, checks uniqueness,
     * handles published_at based on status, creates post record, and syncs
     * category relationships.
     *
     * @param array $data Input data (title, slug, content, excerpt, status, categories, etc.)
     * @param int $authorId User ID of post author
     * @return int Created post ID
     * @throws ServiceException If title empty, slug exists, or scheduled without date
     */
    public static function create($data, $authorId)
    {
        // Validate title is provided
        if (empty($data['title'])) {
            throw new ServiceException('Post title is required.');
        }

        // Generate slug from title if not provided, otherwise sanitize user input
        $slug = empty($data['slug'])
            ? self::generateSlug($data['title'])
            : self::sanitizeSlug($data['slug']);

        // Check slug uniqueness (excluding deleted posts)
        if (PostModel::where('slug', $slug)->whereNull('deleted_at')->exists()) {
            throw new ServiceException('Slug already exists. Please choose a different one.');
        }

        // Determine published_at based on status
        $status = $data['status'] ?? 'draft';
        $publishedAt = self::calculatePublishedAt($status, null, $data['published_at'] ?? null);

        // Validate featured image ID if provided
        $featuredImageId = !empty($data['featured_image_id']) ? $data['featured_image_id'] : null;

        // Create post with all fields
        $postId = PostModel::save([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'author_id' => $authorId,
            'type' => $data['type'] ?? 'post',
            'status' => $status,
            'visibility' => $data['visibility'] ?? 'public',
            'published_at' => $publishedAt,
            'allow_comments' => !empty($data['allow_comments']) ? 1 : 0,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'featured_image_id' => $featuredImageId
        ]);

        // Sync category relationships
        if (!empty($data['categories']) && is_array($data['categories'])) {
            self::syncCategories($postId, $data['categories']);
        }

        return $postId;
    }

    /**
     * Update existing post
     *
     * Validates input, fetches existing post, sanitizes slug, checks uniqueness
     * excluding current record, handles status transitions and published_at,
     * updates post record, and syncs category relationships.
     *
     * @param int $id Post ID to update
     * @param array $data Updated data (title, slug, content, status, categories, etc.)
     * @return bool True on successful update
     * @throws ServiceException If post not found, title empty, or slug exists
     */
    public static function update($id, $data)
    {
        // Fetch post to update (must exist and not be deleted)
        $post = PostModel::where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$post) {
            throw new ServiceException('Post not found.');
        }

        // Validate title is provided
        if (empty($data['title'])) {
            throw new ServiceException('Post title is required.');
        }

        // Generate slug from title if not provided, otherwise sanitize user input
        $slug = empty($data['slug'])
            ? self::generateSlug($data['title'])
            : self::sanitizeSlug($data['slug']);

        // Check slug uniqueness excluding current post
        $existingSlug = PostModel::where('slug', $slug)
            ->where('id != ?', $id)
            ->whereNull('deleted_at')
            ->first();

        if ($existingSlug) {
            throw new ServiceException('Slug already exists. Please choose a different one.');
        }

        // Handle published_at based on status transition
        $status = $data['status'] ?? 'draft';
        $publishedAt = self::calculatePublishedAt(
            $status,
            $post['published_at'],
            $data['published_at'] ?? null,
            $post['status']
        );

        // Validate featured image ID if provided
        $featuredImageId = !empty($data['featured_image_id']) ? $data['featured_image_id'] : null;

        // Update post fields
        PostModel::where('id', $id)->save([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'type' => $data['type'] ?? 'post',
            'status' => $status,
            'visibility' => $data['visibility'] ?? 'public',
            'published_at' => $publishedAt,
            'allow_comments' => !empty($data['allow_comments']) ? 1 : 0,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'featured_image_id' => $featuredImageId
        ]);

        // Sync category relationships (delete old, insert new)
        PostTaxonomyModel::where('post_id', $id)->delete();
        if (!empty($data['categories']) && is_array($data['categories'])) {
            self::syncCategories($id, $data['categories']);
        }

        return true;
    }

    /**
     * Soft delete post (move to trash)
     *
     * Sets deleted_at timestamp on post record. Post won't appear in queries
     * that filter whereNull('deleted_at'). Can be restored later.
     *
     * @param int $id Post ID to delete
     * @return bool True on successful deletion
     * @throws ServiceException If post not found or already deleted
     */
    public static function delete($id)
    {
        // Fetch post to delete (must exist and not be deleted)
        $post = PostModel::where('id', $id)
                    ->whereNull('deleted_at')
                    ->first();

        if (!$post) {
            throw new ServiceException('Post not found.');
        }

        // Soft delete by setting timestamp
        PostModel::where('id', $id)->save([
            'deleted_at' => Date::now()
        ]);

        return true;
    }

    /**
     * Permanently deletes posts - empty trash
     *
     * Delete all posts in the database where 'deleted_at' is not null. Meaning they
     * had been soft deleted - aka moved to trash.
     *
     * @param null
     * @return bool True on successful deletion
     */
    public static function emptyTrash()
    {
        // Fetch post to delete (must exist and not be deleted)
        $posts = PostModel::select(['id'])->whereNotNull('deleted_at')->all();

        if (count($posts) < 1) {
            return [
                'success'   => false,
                'message'   => 'No posts found in trash.'
            ];
        }
        else {

            $posts  = array_column($posts, 'id');
            PostModel::whereIn('id', $posts)->delete();

            return [
                'success'   => true,
                'message'   => count($posts) . " permanently deleted from trash."
            ];
        }
    }

    /**
     * Get all non-deleted posts with optional status filter
     *
     * Fetches posts with author names via LEFT JOIN. Loads categories for each
     * post via separate query and groups by post_id. Excludes or includes
     * soft-deleted based on status filter. Ordered by newest first.
     *
     * @param string $type The type of post to load, can be 'post' or custom posts
     * @param string|null $status Optional status filter (published, draft, scheduled, trash)
     * @return array Array of post records with author and categories
     */
    public static function getAll($type, $status = null)
    {
        // Build query with author JOIN
        $query = PostModel::select([
                'id', 'title', 'slug', 'status',
                'published_at', 'created_at', 'updated_at'
            ])
            ->where('type', 'post')
            ->leftJoin('users', 'author_id = id', ['username', 'first_name', 'last_name', 'avatar']);

        // Apply status filter (trash vs active statuses)
        if ($status === 'trash') {
            $query->whereNotNull('deleted_at');
        }
        else {
            $query->whereNull('deleted_at');
            if ($status && in_array($status, ['published', 'draft', 'scheduled'])) {
                $query->where('status', $status);
            }
        }

        // Order by newest first
        $posts = $query->order('created_at', 'desc')->all();

        // Load categories for all posts in single query
        if (!empty($posts)) {
            $postIds = array_column($posts, 'id');

            // Get all post-category relationships
            $postCategories = PostTaxonomyModel::select(['post_id', 'taxonomy_id'])
                ->leftJoin('taxonomies', 'taxonomy_id = id', ['name'])
                ->whereIn('post_id', $postIds)
                ->where('taxonomies.type', 'category')
                ->all();

            // Group categories by post_id
            $categoriesByPost = [];
            foreach ($postCategories as $pc) {
                $categoriesByPost[$pc['post_id']][] = $pc['name'];
            }

            // Attach categories to each post
            foreach ($posts as &$post) {
                $post['categories'] = $categoriesByPost[$post['id']] ?? [];
            }
        }

        return $posts;
    }

    /**
     * Get single post by ID with featured image
     *
     * Fetches post matching ID with featured image details via LEFT JOIN,
     * excluding soft-deleted records. Used for loading post in edit forms.
     *
     * @param int $id Post ID
     * @return array|null Post record with featured_image fields or null if not found
     */
    public static function getById($id)
    {
        return PostModel::where('id', $id)
            ->whereNull('deleted_at')
            ->leftJoin('media', 'featured_image_id = id', [
                'file_path as featured_image',
                'alt_text as featured_image_alt',
                'title as featured_image_title'
            ])
            ->first();
    }

    /**
     * Get post's assigned category IDs
     *
     * Returns array of taxonomy IDs assigned to post. Used for pre-selecting
     * categories in edit forms.
     *
     * @param int $postId Post ID
     * @return array Array of taxonomy IDs
     */
    public static function getPostCategories($postId)
    {
        return PostTaxonomyModel::where('post_id', $postId)->pluck('taxonomy_id');
    }

    /**
     * Get status counts for all post states
     *
     * Returns count of 'posts' in each status (all, published, draft, scheduled, trash).
     * Used for displaying status tabs with counts in admin UI.
     *
     * @param string $type The type of post to fetch, default is 'post'
     * @return array Associative array with status counts
     */
    public static function getStatusCounts($type = 'post')
    {
        return [
            'all' => PostModel::where('type', $type)->whereNull('deleted_at')->count(),
            'published' => PostModel::where('type', $type)->where('status', 'published')->whereNull('deleted_at')->count(),
            'draft' => PostModel::where('type', $type)->where('status', 'draft')->whereNull('deleted_at')->count(),
            'scheduled' => PostModel::where('type', $type)->where('status', 'scheduled')->whereNull('deleted_at')->count(),
            'trash' => PostModel::where('type', $type)->whereNotNull('deleted_at')->count()
        ];
    }

    /**
     * Get all categories for post editor
     *
     * Returns all non-deleted categories ordered by name for use in post
     * creation/edit form dropdowns.
     *
     * @return array Array of category records
     */
    public static function getCategories()
    {
        return TaxonomyModel::where('type', 'category')
            ->whereNull('deleted_at')
            ->order('name', 'asc')
            ->all();
    }

    /**
     * Get all tags for post editor
     *
     * Returns all non-deleted tags ordered by name for use in post
     * creation/edit form multi-selects.
     *
     * @return array Array of tag records
     */
    public static function getTags()
    {
        return TaxonomyModel::where('type', 'tag')
            ->whereNull('deleted_at')
            ->order('name', 'asc')
            ->all();
    }

    /**
     * Generate URL-friendly slug from title
     *
     * Converts title to lowercase, replaces spaces with hyphens, removes special
     * characters (keeps only a-z, 0-9, hyphens), removes consecutive hyphens,
     * trims hyphens from edges, and ensures uniqueness by appending counter
     * (-1, -2, -3, etc.) if slug already exists.
     *
     * @param string $title Post title to convert to slug
     * @return string URL-friendly unique slug
     */
    private static function generateSlug($title)
    {
        // Convert to lowercase
        $slug = strtolower($title);

        // Replace spaces with hyphens
        $slug = str_replace(' ', '-', $slug);

        // Remove special characters (keep alphanumeric and hyphens)
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start and end
        $slug = trim($slug, '-');

        // Ensure uniqueness by appending counter if needed
        $originalSlug = $slug;
        $counter = 1;

        while (PostModel::where('slug', $slug)->whereNull('deleted_at')->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Sanitize user-provided slug
     *
     * Ensures slug contains only valid URL-friendly characters by converting to
     * lowercase, removing invalid characters (keeps only a-z, 0-9, hyphens),
     * collapsing consecutive hyphens, and trimming hyphens from start and end.
     *
     * @param string $slug User-provided slug to sanitize
     * @return string Sanitized slug containing only valid characters
     */
    private static function sanitizeSlug($slug)
    {
        // Convert to lowercase
        $slug = strtolower($slug);

        // Remove invalid characters (keep alphanumeric and hyphens)
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start and end
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Calculate published_at based on status transition
     *
     * Handles complex status workflow:
     * - draft → published: Set to now (first publish)
     * - draft → scheduled: Use provided date (must be set)
     * - published → draft: Clear published_at
     * - published → published: Keep existing (already published)
     * - scheduled → published: Keep scheduled date or set now
     *
     * @param string $newStatus Target status (draft, published, scheduled)
     * @param string|null $currentPublishedAt Existing published_at from database
     * @param string|null $providedPublishedAt User-provided published_at from form
     * @param string|null $currentStatus Current status from database (for transition detection)
     * @return string|null Calculated published_at timestamp or null
     * @throws ServiceException If scheduled without providing published_at date
     */
    private static function calculatePublishedAt($newStatus, $currentPublishedAt, $providedPublishedAt = null, $currentStatus = null)
    {
        // Publishing for the first time (draft/scheduled → published, no existing date)
        if ($newStatus === 'published' && $currentStatus !== 'published' && empty($currentPublishedAt)) {
            return Date::now();
        }

        // Scheduled post requires a future date
        if ($newStatus === 'scheduled') {
            if (empty($providedPublishedAt)) {
                throw new ServiceException('Scheduled posts require a publication date.');
            }
            return $providedPublishedAt;
        }

        // Draft posts don't have publication date
        if ($newStatus === 'draft') {
            return null;
        }

        // Keep existing published_at for already published posts
        return $currentPublishedAt;
    }

    /**
     * Sync post-category relationships
     *
     * Bulk inserts post-taxonomy records for all selected categories.
     * Called after deleting old relationships to maintain clean junction table.
     *
     * @param int $postId Post ID
     * @param array $categoryIds Array of taxonomy IDs to assign
     * @return void
     */
    private static function syncCategories($postId, $categoryIds)
    {
        // Build bulk insert data
        $categoryData = [];
        foreach ($categoryIds as $taxonomyId) {
            $categoryData[] = [
                'post_id' => $postId,
                'taxonomy_id' => (int)$taxonomyId
            ];
        }

        // Bulk insert category relationships
        if (!empty($categoryData)) {
            PostTaxonomyModel::save($categoryData);
        }
    }
}
