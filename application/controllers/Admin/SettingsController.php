<?php namespace Controllers\Admin;

use Rackage\View;
use Rackage\Input;
use Rackage\Redirect;
use Rackage\Csrf;
use Models\SettingModel;
use Models\PostModel;
use Rackage\Controller;

/**
 * Settings Controller - Pressli CMS
 *
 * Manages site-wide configuration including general settings, reading preferences,
 * discussion options, media settings, permalinks, and privacy controls.
 *
 * Routes (automatic URL-based routing):
 * - GET  /settings           Display settings page
 * - POST /settings/update    Save all settings
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class SettingsController extends Controller
{
    /**
     * Display settings page with all configuration sections
     *
     * Loads all settings from database and organizes them by category.
     * Settings are stored as key-value pairs in the settings table.
     *
     * @return void
     */
    public function getIndex()
    {
        // Load all settings
        $settingsRaw = SettingModel::all();

        // Convert to key-value array for easier access
        $settings = [];
        foreach ($settingsRaw as $setting) {
            $settings[$setting['name']] = $setting['value'];
        }

        // Load all published pages for homepage selector
        $pages = PostModel::select(['id', 'title'])
                    ->where('type', 'page')
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->order('title', 'asc')
                    ->all();

        // Array of data to send to view
        $data = [
            'title'     => 'Settings',
            'settings'  => $settings,
            'pages'     => $pages,
        ];

        View::render('admin/settings', $data);
    }

    /**
     * Save settings from form submission
     *
     * Updates or creates settings based on form input. Handles all setting
     * sections: general, reading, discussion, media, permalinks, and privacy.
     *
     * @return void Redirects back to settings page with success message
     */
    public function postUpdate()
    {
        if (!Csrf::verify()) {
            Redirect::back()->flash('error', 'Invalid security token. Please try again.');
        }

        // Define all settings fields and their defaults
        $settingsFields = [
            // General
            'site_title' => Input::post('site-title', 'Pressli CMS'),
            'site_tagline' => Input::post('site-tagline', 'Just another Pressli site'),
            'admin_email' => Input::post('admin-email', ''),
            'timezone' => Input::post('timezone', 'UTC'),
            'date_format' => Input::post('date-format', 'F j, Y'),
            'time_format' => Input::post('time-format', 'g:i a'),
            'week_starts_on' => Input::post('week-starts-on', '0'),

            // Reading
            'homepage_type' => Input::post('homepage-type', 'posts'),
            'homepage_page_id' => Input::post('homepage-page-id', ''),
            'posts_page_id' => Input::post('posts-page-id', ''),
            'posts_per_page' => Input::post('posts-per-page', '10'),
            'syndication_feeds' => Input::post('syndication-feeds', '10'),
            'search_engine_visibility' => Input::post('search-engine-visibility', '0'),

            // Discussion
            'allow_comments' => Input::post('allow-comments', '1'),
            'allow_pingbacks' => Input::post('allow-pingbacks', '0'),
            'comments_manual_approval' => Input::post('comments-manual-approval', '1'),
            'comment_author_approved' => Input::post('comment-author-approved', '0'),
            'hold_comments_links' => Input::post('hold-comments-links', '2'),
            'disallowed_keys' => Input::post('disallowed-keys', ''),
            'show_avatars' => Input::post('show-avatars', '1'),

            // Media
            'thumbnail_width' => Input::post('thumbnail-width', '150'),
            'thumbnail_height' => Input::post('thumbnail-height', '150'),
            'medium_width' => Input::post('medium-width', '300'),
            'medium_height' => Input::post('medium-height', '300'),
            'large_width' => Input::post('large-width', '1024'),
            'large_height' => Input::post('large-height', '1024'),

            // Permalinks
            'permalink_structure' => Input::post('permalink', 'postname'),
            'custom_permalink' => Input::post('custom-permalink', ''),

            // Privacy
            'privacy_page' => Input::post('privacy-page', ''),
            'user_registration' => Input::post('user-registration', '1'),
            'default_role' => Input::post('default-role', 'subscriber'),
        ];

        // Update or create each setting
        foreach ($settingsFields as $key => $value) {
            $existing = SettingModel::where('name', $key)->first();

            if ($existing) {
                // Update existing setting
                SettingModel::where('name', $key)->save(['value' => $value]);
            } else {
                // Create new setting
                SettingModel::save([
                    'name' => $key,
                    'value' => $value,
                    'autoload' => 1
                ]);
            }
        }

        Redirect::to('admin/settings')->flash('success', 'Settings saved successfully!');
    }
}
