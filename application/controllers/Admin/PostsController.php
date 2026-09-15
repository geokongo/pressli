<?php namespace Controllers\Admin;

use Rackage\Csrf;
use Rackage\View;
use Rackage\Input;
use Rackage\Session;
use Rackage\Request;
use Rackage\Redirect;
use Lib\Services\Post;
use Controllers\Admin\AdminController;
use Lib\Exceptions\ServiceException;

/**
 * Posts Controller - Pressli CMS
 *
 * Manages blog posts in the admin panel. Handles creating, editing, publishing,
 * scheduling, and deleting posts with full content management features. All
 * state-changing operations are CSRF-protected.
 *
 * Routes (automatic URL-based routing):
 * - GET  /posts           List all posts with status filtering
 * - GET  /posts/create    Display post creation form
 * - POST /posts/create    Process new post creation
 * - GET  /posts/edit/{id} Display post edit form
 * - POST /posts/update/{id} Process post update
 * - GET  /posts/delete/{id} Soft delete post (move to trash)
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class PostsController extends AdminController
{
    /**
     * Display list of all posts with status filtering
     *
     * Fetches all non-deleted posts with author names via LEFT JOIN.
     * Calculates post counts per status for filter tab badges. Orders by
     * created_at descending (newest first).
     *
     * @return void
     */
    public function getIndex()
    {
        // Get status filter from query string
        $status = Input::get('status');

        // Get all posts with optional status filter
        $posts = Post::getAll('post', $status);

        // Get status counts for tabs
        $statusCounts = Post::getStatusCounts('post');

        // Array of data to send to view
        $data = [
            'title'         => 'Posts',
            'filter'        => $status,
            'posts'         => $posts,
            'statusCounts'  => $statusCounts,
            'settings'      => $this->settings
        ];

        View::render('admin/posts', $data);
    }

    /**
     * Display form to create new post
     *
     * Loads all categories and tags for selection in the post editor.
     * Categories shown in dropdown, tags in multi-select. Editor includes
     * WYSIWYG content field, status selector, and SEO metadata inputs.
     *
     * @return void
     */
    public function getNew()
    {
        // Get categories and tags for editor
        $categories = Post::getCategories();
        $tags = Post::getTags();

        // Array of data to send to view
        $data = [
            'title' => 'New Post',
            'categories' => $categories,
            'tags' => $tags,
            'settings' => $this->settings
        ];

        View::render('admin/posts-new', $data);
    }

    /**
     * Process new post creation
     *
     * Validates input, auto-generates slug from title if not provided,
     * sets published_at based on status, and creates post with current
     * user as author.
     *
     * Validation: title required; slug must be unique; status must be valid enum.
     *
     * @return void Redirects to /posts on success, back on error
     */
    public function postNew()
    {        
        // Verify CSRF token
        if (!Csrf::verify()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Redirect::back();
        }

        try {
            // Prepare post data from form input
            $data = [
                'title' => Input::post('title'),
                'slug' => Input::post('slug'),
                'content' => Input::post('content'),
                'excerpt' => Input::post('excerpt'),
                'type' => Input::post('type'),
                'status' => Input::post('status', 'draft'),
                'visibility' => Input::post('visibility', 'public'),
                'allow_comments' => Input::post('allow-comments') ? 1 : 0,
                'meta_title' => Input::post('meta-title'),
                'meta_description' => Input::post('meta-description'),
                'featured_image_id' => Input::post('featured-image-id'),
                'published_at' => Input::post('published-at'),
                'categories' => Input::post('categories')
            ];

            // Create post via service
            $postId = Post::create($data, Session::get('user_id'));

            // Success - redirect to edit page
            Session::flash('success', 'Post created successfully!');
            Redirect::to('admin/posts/edit/' . $postId);
        }
        catch (ServiceException $e) {
            // Validation or business logic error
            Session::flash('error', $e->getMessage());
            Redirect::back();
        }
    }

    /**
     * Display form to edit post
     *
     * Loads post data, categories, and tags for editing. Returns 404 if post
     * not found or already deleted.
     *
     * @param int $id Post ID from URL
     * @return void
     */
    public function getEdit($id)
    {
        // Get post with featured image
        $post = Post::getById($id);

        if (!$post) {
            Session::flash('error', 'Post not found.');
            Redirect::to('admin/posts');
        }

        // Get categories and tags for editor
        $categories = Post::getCategories();
        $tags = Post::getTags();

        // Get post's assigned category IDs for pre-selection
        $postCategories = Post::getPostCategories($id);

        // Array of data to send to view
        $data = [
            'title' => 'Edit Post',
            'post' => $post,
            'categories' => $categories,
            'tags' => $tags,
            'postCategories' => $postCategories,
            'settings' => $this->settings
        ];

        View::render('admin/posts-edit', $data);
    }

    /**
     * Process post edit
     *
     * Updates post information including content, metadata, status, and visibility.
     * Validates slug uniqueness excluding current post. Handles status transitions
     * and sets published_at accordingly.
     *
     * @param int $id Post ID from URL
     * @return void Redirects to /posts on success, back on error
     */
    public function postEdit($id)
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            if (Request::ajax()) {
                View::json(['success' => false, 'message' => 'Invalid security token. Please try again.'], 403);
                return;
            }
            Session::flash('error', 'Invalid security token. Please try again.');
            Redirect::back();
        }

        try {
            // Prepare post data from form input
            $data = [
                'title' => Input::post('title'),
                'slug' => Input::post('slug'),
                'content' => Input::post('content'),
                'excerpt' => Input::post('excerpt'),
                'type' => Input::post('type'),
                'status' => Input::post('status', 'draft'),
                'visibility' => Input::post('visibility', 'public'),
                'allow_comments' => Input::post('allow-comments') ? 1 : 0,
                'meta_title' => Input::post('meta-title'),
                'meta_description' => Input::post('meta-description'),
                'featured_image_id' => Input::post('featured-image-id'),
                'published_at' => Input::post('published-at'),
                'categories' => Input::post('categories')
            ];

            // Update post via service
            Post::update($id, $data);

            // Return JSON for AJAX requests
            if (Request::ajax()) {
                View::json([
                    'success' => true,
                    'message' => 'Post updated successfully!',
                    'post' => [
                        'id' => $id,
                        'title' => $data['title'],
                        'slug' => $data['slug'],
                        'status' => $data['status']
                    ]
                ]);
                return;
            }

            // Traditional redirect for non-AJAX
            Session::flash('success', 'Post updated successfully!');
            Redirect::to('admin/posts/edit/' . $id);
        }
        catch (ServiceException $e) {
            // Validation or business logic error
            if (Request::ajax()) {
                View::json(['success' => false, 'message' => $e->getMessage()], 400);
                return;
            }
            Session::flash('error', $e->getMessage());
            Redirect::back();
        }
    }

    /**
     * Soft delete post (move to trash)
     *
     * Sets deleted_at timestamp instead of removing record. Preserves data for
     * potential restoration. Trashed posts can be permanently deleted or restored
     * from trash view.
     *
     * @param int $id Post ID from URL
     * @return void Redirects to /posts with status message
     */
    public function getDelete($id)
    {
        try {
            // Soft delete via service
            Post::delete($id);

            // Success - redirect to list
            Session::flash('success', 'Post moved to trash.');
            Redirect::to('admin/posts');
        }
        catch (ServiceException $e) {
            // Post not found
            Session::flash('error', $e->getMessage());
            Redirect::to('admin/posts');
        }
    }

    /**
     * Delete all posts currently in trash.
     * 
     * Loops through all posts recently deleted but still in trash 'deleted_at' and
     * permanently deletes them from the database. This makes them irreversible.
     * 
     * @param null
     * @return void
     */
    public function trash()
    {

        try {

            // Call service to permantently delete items
            $deleted    = Post::emptyTrash();

            // Success - redirect to list
            Session::flash($deleted['success'] ? 'success' : 'error', $deleted['message']);
            Redirect::to('admin/posts?status=trash');
        }
        catch (\Throwable $exception) {

            // Post not found
            Session::flash('error', $exception->getMessage());
            Redirect::to('admin/posts');
        }

    }
}
