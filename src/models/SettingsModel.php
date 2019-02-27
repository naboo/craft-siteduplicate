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

    public $pluginName = "Site Duplicate";

    // Inheritance
    // =========================================================================

    /**
     * Define rules
     */
    public function rules()
    {
        /*return [
            [
                [
                    'var'
                ], 
                'required'
            ],
        ];*/

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
