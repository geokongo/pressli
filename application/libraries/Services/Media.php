<?php namespace Lib\Services;

/**
 * Media Service - Pressli CMS
 *
 * Business logic layer for managing media library (upload, organize, edit, delete).
 * Provides reusable functions for file upload handling, metadata management, and
 * media queries with automatic file type categorization.
 *
 * USAGE CONTEXTS:
 * - Admin controllers (web UI for media library)
 * - API controllers (REST endpoints for headless CMS)
 * - CLI commands (bulk media operations, cleanup)
 * - Plugins (programmatic media uploads)
 * - Cron jobs (automated media processing)
 *
 * FILE TYPE CATEGORIZATION:
 * - image: image/* MIME types (jpg, png, gif, webp, etc.)
 * - video: video/* MIME types (mp4, webm, etc.)
 * - audio: audio/* MIME types (mp3, wav, etc.)
 * - document: PDF, Word, Excel formats
 * - other: Everything else (zip, txt, etc.)
 *
 * UPLOAD WORKFLOW:
 * - Files stored in public/uploads/YYYY/MM/ with SHA1 unique filenames
 * - MIME type detected, file type category assigned
 * - Image dimensions extracted for images
 * - Metadata saved to database (filename, path, size, dimensions, author)
 *
 * VALIDATION RULES:
 * - File type must be in allowed list
 * - File size must be under limit (default 10MB)
 * - Media must exist and not be deleted for updates
 * - Physical file remains on disk after soft delete (recovery possible)
 *
 * ERROR HANDLING:
 * All methods throw ServiceException on validation or logic errors.
 * Controllers catch exceptions and format response appropriately (flash messages or JSON).
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */

use Rackage\Date;
use Rackage\Upload;
use Models\MediaModel;
use Lib\Exceptions\ServiceException;

class Media
{
    /**
     * Upload and process media file
     *
     * Handles file upload via Rachie's Upload helper, validates file type and size,
     * stores in public/uploads/YYYY/MM/ with unique SHA1 filename, determines file
     * type category from MIME type, extracts image dimensions if applicable, and
     * saves metadata to database with author reference.
     *
     * @param string $fieldName Form field name containing uploaded file
     * @param int $authorId User ID of uploader
     * @param array $allowedTypes Array of allowed extensions (default: common types)
     * @param int $maxSize Max file size in bytes (default: 10MB)
     * @return array Media record data (id, filename, file_path, file_type, etc.)
     * @throws ServiceException If upload fails or validation error
     */
    public static function upload($fieldName, $authorId, $allowedTypes = null, $maxSize = null)
    {
        // Default allowed file types if not specified
        if ($allowedTypes === null) {
            $allowedTypes = [
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'pdf', 'doc', 'docx',
                'mp4', 'mp3', 'zip'
            ];
        }

        // Default max size 10MB if not specified
        if ($maxSize === null) {
            $maxSize = 10 * 1024 * 1024;
        }

        // Upload file with validation
        $result = Upload::file($fieldName)
            ->path('public/uploads/' . date('Y/m'))
            ->allowedTypes($allowedTypes)
            ->maxSize($maxSize)
            ->save();

        // Check upload success
        if (!$result->success) {
            throw new ServiceException($result->errorMessage);
        }

        // Determine file type category from MIME type
        $fileType = self::determineFileType($result->mimeType);

        // Strip 'public/' from path (document root already points there)
        $filePath = str_replace('\\', '/', $result->relativePath);
        $filePath = preg_replace('#^public/#', '', $filePath);

        // Save media metadata to database
        $mediaId = MediaModel::save([
            'filename' => $result->fileName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'mime_type' => $result->mimeType,
            'file_size' => $result->fileSize,
            'width' => $result->width ?? null,
            'height' => $result->height ?? null,
            'title' => pathinfo($result->fileName, PATHINFO_FILENAME),
            'author_id' => $authorId
        ]);

        // Return media data for response
        return [
            'id' => $mediaId,
            'filename' => $result->fileName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'mime_type' => $result->mimeType,
            'file_size' => $result->fileSize,
            'width' => $result->width ?? null,
            'height' => $result->height ?? null,
            'title' => pathinfo($result->fileName, PATHINFO_FILENAME)
        ];
    }

    /**
     * Update media metadata
     *
     * Updates media title, alt text (for accessibility), and description without
     * modifying the physical file. Validates media exists and is not deleted.
     *
     * @param int $id Media ID to update
     * @param array $data Updated metadata (title, alt_text, description)
     * @return bool True on successful update
     * @throws ServiceException If media not found or already deleted
     */
    public static function update($id, $data)
    {
        // Fetch media to update (must exist and not be deleted)
        $media = MediaModel::where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$media) {
            throw new ServiceException('Media not found.');
        }

        // Update metadata fields
        MediaModel::where('id', $id)->save([
            'title' => $data['title'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'description' => $data['description'] ?? null
        ]);

        return true;
    }

    /**
     * Soft delete media (move to trash)
     *
     * Sets deleted_at timestamp on media record. Physical file remains on disk
     * allowing for potential recovery. Media won't appear in library queries but
     * can be restored or permanently deleted later.
     *
     * @param int $id Media ID to delete
     * @return bool True on successful deletion
     * @throws ServiceException If media not found or already deleted
     */
    public static function delete($id)
    {
        // Fetch media to delete (must exist and not be deleted)
        $media = MediaModel::where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$media) {
            throw new ServiceException('Media not found.');
        }

        // Soft delete by setting timestamp
        MediaModel::where('id', $id)->save([
            'deleted_at' => Date::now()
        ]);

        return true;
    }

    /**
     * Get all non-deleted media with optional type filter
     *
     * Fetches media with author names via LEFT JOIN. Optionally filters by file
     * type category (image/video/document/audio/other). Excludes soft-deleted
     * records, ordered by newest first.
     *
     * @param string|null $type Optional type filter (image, video, document, audio, other)
     * @return array Array of media records with author info
     */
    public static function getAll($type = null)
    {
        // Build query with author JOIN
        $query = MediaModel::select([
                'id', 'filename', 'file_path', 'file_type', 'mime_type',
                'file_size', 'width', 'height', 'alt_text', 'title',
                'author_id', 'created_at'
            ])
            ->leftJoin('users', 'author_id = id', ['first_name', 'last_name']);

        // Apply type filter if provided
        if ($type && in_array($type, ['image', 'video', 'document', 'audio', 'other'])) {
            $query->where('file_type', $type);
        }

        // Exclude deleted media and order by newest first
        $query->whereNull('deleted_at');
        return $query->order('created_at', 'desc')->all();
    }

    /**
     * Get single media by ID
     *
     * Fetches media matching ID, excluding soft-deleted records. Used for
     * loading media details in edit forms and API responses.
     *
     * @param int $id Media ID
     * @return array|null Media record or null if not found
     */
    public static function getById($id)
    {
        return MediaModel::where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Get type counts for all media categories
     *
     * Returns count of media items in each file type category (all, image, video,
     * document, audio). Used for displaying type filter tabs with counts in admin UI.
     *
     * @return array Associative array with type counts
     */
    public static function getTypeCounts()
    {
        return [
            'all' => MediaModel::whereNull('deleted_at')->count(),
            'image' => MediaModel::where('file_type', 'image')->whereNull('deleted_at')->count(),
            'video' => MediaModel::where('file_type', 'video')->whereNull('deleted_at')->count(),
            'document' => MediaModel::where('file_type', 'document')->whereNull('deleted_at')->count(),
            'audio' => MediaModel::where('file_type', 'audio')->whereNull('deleted_at')->count(),
        ];
    }

    /**
     * Determine file type category from MIME type
     *
     * Maps MIME types to broad categories for filtering and icon display in media
     * library. Checks MIME type prefix for image/video/audio, specific MIME types
     * for common document formats (PDF, Word, Excel), and defaults to 'other' for
     * everything else.
     *
     * @param string $mimeType Full MIME type (e.g., "image/jpeg", "application/pdf")
     * @return string Category: image, video, document, audio, or other
     */
    private static function determineFileType($mimeType)
    {
        // Check image/* MIME types
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }

        // Check video/* MIME types
        if (strpos($mimeType, 'video/') === 0) {
            return 'video';
        }

        // Check audio/* MIME types
        if (strpos($mimeType, 'audio/') === 0) {
            return 'audio';
        }

        // Check common document MIME types
        if (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ])) {
            return 'document';
        }

        // Default to other for everything else
        return 'other';
    }
}
