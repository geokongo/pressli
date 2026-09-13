<?php namespace Models;

use Rackage\Model;

/**
 * Setting Model - Pressli CMS
 *
 * Manages global site configuration and options for Pressli.
 * Stores key-value pairs for settings like site_title, timezone, theme, etc.
 * Supports autoload flag to optimize frequently-accessed settings.
 *
 * Autoload Behavior:
 * - autoload = 1: Loads on every request (site_title, timezone, active_theme)
 * - autoload = 0: Loads on-demand only (smtp_password, backup_schedule)
 *
 * Common Settings:
 * - site_title: Site name
 * - site_description: Site tagline
 * - timezone: Server timezone
 * - active_theme: Current theme name
 * - posts_per_page: Number of posts per page
 * - comment_moderation: Enable/disable comment approval
 * - registration_enabled: Allow user registration
 *
 * Table: settings
 * Primary Key: id (auto-increment)
 * Indexes: key (unique), autoload
 *
 * @composite (key, autoload)
 *
 * @author Geoffrey Okongo <code@rachie.dev>
 * @copyright Copyright (c) 2015 - 2030 Geoffrey Okongo
 * @license http://opensource.org/licenses/MIT MIT License
 * @version 0.0.1
 */
class SettingModel extends Model
{
    protected static $table      = 'settings';
    protected static $timestamps = false;

    /**
     * Unique setting identifier
     * @column
     * @autonumber
     */
    protected $id;

    /**
     * Setting name (site_title, timezone, etc)
     * @column
     * @varchar 100
     * @unique
     */
    protected $name;

    /**
     * Setting value (stored as text for flexibility)
     * @column
     * @text
     * @nullable
     */
    protected $value;

    /**
     * Whether to load this setting on every request
     * @column
     * @boolean
     * @default 1
     * @index
     */
    protected $autoload;

    // ==================== HELPER METHODS ====================

    /**
     * Get all autoload settings or all settings as associative array
     *
     * Retrieves settings marked with autoload = 1 for fast access, or all settings.
     * Returns key-value pairs for frequently-accessed settings.
     * Called once per request and cached to avoid repeated queries.
     *
     * Performance: Single query fetches all settings at once.
     * Use for settings needed on every page (site_title, active_theme, timezone).
     *
     * @param bool $autoloadOnly If true, fetch only autoload=1 settings. If false, fetch all settings.
     * @return array Associative array ['key' => 'value']
     */
    public static function getAutoload($autoloadOnly = true)
    {
        if ($autoloadOnly) $settings = self::where('autoload', 1)->all();
        else $settings = self::all();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['name']] = $setting['value'];
        }

        return $result;
    }

    /**
     * Get single setting value by key
     *
     * Retrieves specific setting value from database.
     * Returns default value if setting doesn't exist.
     * Use when you need one setting that isn't autoloaded.
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value or default
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('name', $key)->first();

        return $setting ? $setting['value'] : $default;
    }

    /**
     * Set setting value (create or update)
     *
     * Creates new setting if key doesn't exist, updates if exists.
     * Handles both autoload and non-autoload settings.
     * Used by admin panel to update site configuration.
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @param bool $autoload Whether to autoload (default: true)
     * @return bool Success
     */
    public static function set($key, $value, $autoload = true)
    {
        $existing = self::where('name', $key)->first();

        if ($existing) {
            self::where('id', $existing['id'])->save([
                'value'     => $value,
                'autoload'  => $autoload ? 1 : 0
            ]);
        } 
        else {
            self::save([
                'name'      => $key,
                'value'     => $value,
                'autoload'  => $autoload ? 1 : 0
            ]);
        }

        return true;
    }

    /**
     * Delete setting by key
     *
     * Removes setting from database completely.
     * Use carefully - deleted settings can't be recovered.
     * Admin panel should confirm before deleting.
     *
     * @param string $key Setting key
     * @return bool Success (true even if key didn't exist)
     */
    public static function remove($key)
    {
        return self::where('name', $key)->delete() >= 0;
    }

    /**
     * Check if setting exists
     *
     * Verifies whether setting key exists in database.
     * Useful for conditional logic based on setting presence.
     * Doesn't load the value, just checks existence.
     *
     * @param string $key Setting key
     * @return bool True if exists, false otherwise
     */
    public static function has($key)
    {
        return self::where('name', $key)->count() > 0;
    }
}
