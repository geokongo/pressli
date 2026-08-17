<?php namespace Controllers\Admin;

use Rackage\Url;
use Rackage\Csrf;
use Rackage\Path;
use Rackage\View;
use Rackage\Input;
use Rackage\Session;
use Rackage\Redirect;
use Lib\Services\Media;
use Controllers\Admin\AdminController;
use Lib\Exceptions\ServiceException;

/**
 * Media Controller - Pressli CMS
 *
 * Manages media library: upload, organize, edit metadata, and delete files.
 * Handles images, videos, documents, and audio files with automatic categorization.
 * All state-changing operations are CSRF-protected.
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class MediaController extends AdminController
{
    /**
     * Display media library
     *
     * Fetches all media with author info, optionally filtered by type via ?type= query param.
     * Calculates counts per type for filter tabs. Displays thumbnails for images,
     * file icons for documents/audio/video with metadata (size, dimensions, upload date).
     *
     * @return void
     */
    public function getIndex()
    {
        // Get type filter from query string
        $type = Input::get('type');

        // Get all media with optional type filter
        $media = Media::getAll($type);

        // Get type counts for tabs
        $typeCounts = Media::getTypeCounts();

        // Array of data to send to view
        $data = [
            'title' => 'Media Library',
            'media' => $media,
            'typeCounts' => $typeCounts,
            'settings' => $this->settings
        ];

        View::render('admin/media', $data);
    }

    /**
     * Process file upload from modal
     *
     * Handles AJAX file upload from modal form. Validates file against
     * allowed types and 10MB size limit. Uses Rachie's Upload helper to store file in
     * public/uploads/YYYY/MM/ with unique SHA1 filename. Automatically detects file
     * category (image/video/document/audio/other) from MIME type, extracts image
     * dimensions if applicable, and saves metadata to database with current user as author.
     * Returns JSON with media data for dynamic grid insertion.
     *
     * @return void Outputs JSON response with media data or error
     */
    public function postUpload()
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            View::halt(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        try {

            // Upload file via service
            $uploaded = Media::upload('file', Session::get('user_id'));

            if($uploaded) {

                View::json([
                    'success'  => true,
                    'image_url'=> Url::assets($uploaded['file_path']),
                    'filename' => $uploaded['filename']   
                ]);
            }

            else {

                // Return with false message
                View::json([
                    'success' => false,
                    'media' => 'There was an error uploading the file'
                ]);
            }
        }
        catch (ServiceException $e) {
            // Upload failed or validation error
            View::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }


    /**
     * Display metadata edit form
     *
     * Loads media item and displays form for editing title, alt text (for accessibility),
     * and description. Shows file preview and existing metadata. Redirects to media library
     * with error if media not found or already deleted.
     *
     * @param int $id Media ID from URL
     * @return void
     */
    public function getEdit($id)
    {
        // Get media by ID
        $media = Media::getById($id);

        if (!$media) {
            Session::flash('error', 'Media not found.');
            Redirect::to('admin/media');
        }

        // Array of data to send to view
        $data = [
            'title' => 'Edit Media',
            'media' => $media,
            'settings' => $this->settings
        ];

        View::render('admin/media-edit', $data);
    }

    /**
     * Process metadata update
     *
     * Updates media metadata (title, alt text, description) without modifying the physical file.
     * CSRF-protected. Redirects to media library with success message, or back with error if
     * media not found.
     *
     * @param int $id Media ID from URL
     * @return void Redirects to media library on success
     */
    public function postEdit($id)
    {
        // Verify CSRF token
        if (!Csrf::verify()) {
            Session::flash('error', 'Invalid security token. Please try again.');
            Redirect::back();
        }

        try {
            // Prepare metadata from form input
            $data = [
                'title' => Input::post('title'),
                'alt_text' => Input::post('alt_text'),
                'description' => Input::post('description')
            ];

            // Update media via service
            Media::update($id, $data);

            // Success - redirect to library
            Session::flash('success', 'Media updated successfully!');
            Redirect::to('admin/media');
        }
        catch (ServiceException $e) {
            // Media not found
            Session::flash('error', $e->getMessage());
            Redirect::to('admin/media');
        }
    }

    /**
     * Soft delete media (move to trash)
     *
     * Sets deleted_at timestamp for soft deletion. Physical file remains on disk allowing
     * for potential recovery. Media won't appear in library but can be restored or permanently
     * deleted later. Redirects to media library with success message.
     *
     * @param int $id Media ID from URL
     * @return void Redirects to media library with status message
     */
    public function getDelete($id)
    {
        try {
            // Soft delete via service
            Media::delete($id);

            // Success - redirect to library
            Session::flash('success', 'Media moved to trash.');
            Redirect::to('admin/media');
        }
        catch (ServiceException $e) {
            // Media not found
            Session::flash('error', $e->getMessage());
            Redirect::to('admin/media');
        }
    }
}
