<?php
namespace naboo\siteduplicate;

use Craft;
use craft\base\Plugin;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\services\Plugins;

use yii\base\Event;

use naboo\siteduplicate\models\SettingsModel;
use naboo\siteduplicate\helpers\TemplateHelper;
use naboo\siteduplicate\services\SiteDuplicateService;

/**
 * Site duplicate plugin class.
 *
 * @package     craft-siteduplicate
 * @author      Johan Strömqvist
 * @version     1.0.0
 */
class SiteDuplicatePlugin extends Plugin
{
    // Constants
    // =========================================================================

    // Public Properties
    // =========================================================================

    /**
     * @var Ecommerce
     */
    public static $plugin;

    /**
     * @var View
     */
    public static $view;

    /**
     * @var Settings
     */
    public static $settings;

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Inheritance
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        self::$plugin = $this;
        self::$settings = $this->getSettings();
        self::$view = Craft::$app->getView();

        /*
         * Register events
         *
         */

        // Register after plugins have loaded
        Event::on(Plugins::class, Plugins::EVENT_AFTER_LOAD_PLUGINS, function () {
                // Install these only after all other plugins have loaded
                $request = Craft::$app->getRequest();
                // Respond to Control Panel requests
                if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
                    $this->handleAdminCpRequest();
                }
            }
        );

        // Register Site URL rules
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getSiteRoutes());
            }
        );

        // Register CP URL rules
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getCpRoutes());
            }
        );

        Craft::info(Craft::t('siteduplicate', '{name} plugin loaded', ['name' => $this->name]), __METHOD__);
    }

    // Public Methods
    // =========================================================================

    // Private Methods
    // =========================================================================

    // Protected Methods
    // =========================================================================

    /**
     * Create settings model
     *
     * @return SettingsModel
     */
    protected function createSettingsModel()
    {
        return new SettingsModel();
    }

    /**
     * Handle Control Panel requests. We do it only after we receive the event
     * EVENT_AFTER_LOAD_PLUGINS so that any pending db migrations can be run
     * before our event listeners kick in
     */

    /**
     * Define CP routes
     *
     * @return array
     */
    protected function getCpRoutes()
    {
        return [
            // Default
            'siteduplicate/duplicate' => 'siteduplicate/duplicate/index',
        ];
    }

    /**
     * Define Site routes
     *
     * @return array
     */
    protected function getSiteRoutes()
    {
        return [
        ];
    }

    /**
     * Handle site request
     *
     */
    protected function handleSiteRequest()
    {
    }

    /**
     * Handle CP requests
     *
     */
    protected function handleAdminCpRequest()
    {
        // HOOK: Entries sidebar
        self::$view->hook('cp.entries.edit.details', function (&$context) {

            $html = '';

            /** @var  $entry Entry */
            $entry = $context['entry'];

            if($entry !== null && $entry->uri !== null) 
            {
                $sites = SiteDuplicateService::getAvailableSitesForEntry($entry);

                $html .= TemplateHelper::getCpTemplate('siteduplicate/_entrySidebar.twig', ['entry' => $entry, 'options' => $sites]);
            }

            return $html;
        });
    }
}
