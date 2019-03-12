<?php
namespace naboo\siteduplicate\services;

use Craft;

use craft\helpers\ArrayHelper;
use yii\base\Component;

/**
 * Site duplicate service class.
 *
 * @package     siteduplicate/services
 * @author      Johan Strömqvist
 * @version     1.0
 */
class SiteDuplicateService extends Component
{
    // Public Properties
    // =========================================================================

    // Public static Methods
    // =========================================================================

    /**
     * Get available sites for Entry
     *
     * @param EntryModel
     * @return array
     */
    public static function getAvailableSitesForEntry($entry)
    {
        /*
            {% set section = entry.getSection() %}
            {% set siteIds = section.getSiteIds() %}
            {% set sites = craft.app.sites.allSites() %}
        */

        $sites = [""];

        $section = $entry->getSection();
        $siteIds = $section->getSiteIds();
        $allSites = Craft::$app->sites->allSites;
        $currentSite = "";

        foreach($allSites as $site)
        {
            if(in_array($site->id, $siteIds) && $entry->siteId != $site->id)
            {
                $sites[$site->id] = $site->name;
            }

            if($entry->siteId == $site->id)
            {
                $currentSite = $site->name;
            }
        }

        // Sort array
        ksort($sites, SORT_NUMERIC);

        // Add current site
        $sites["-"] = "-";
        $sites[$entry->siteId] = $currentSite." (".Craft::t('siteduplicate', 'current').")";

        return $sites;
    }
}