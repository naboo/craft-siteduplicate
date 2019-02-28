<?php
namespace naboo\siteduplicate\models;

use craft\base\Model;

/**
 * Craft settings model class.
 *
 * @package     siteduplicate/models
 * @author      Johan Strömqvist
 * @version     1.0
 */
class SettingsModel extends Model
{
    // Constants
    // =========================================================================

    // Protected Properties
    // =========================================================================

    // Public Properties
    // =========================================================================

    /**
     * @var Plugin Name
     */
    public $pluginName = "Site Duplicate";

    /**
     * @var array
     */
    public $enabledSections = [];

    // Inheritance
    // =========================================================================

    /**
     * Define rules
     */
    public function rules()
    {
        return [
            // Safe
            [['enabledSections'], 'safe'],
        ];

        return [];
    }

    /**
     * Init
     */
    public function init()
    {
    }

    // Public Methods
    // =========================================================================

    // Private Methods
    // =========================================================================
}
