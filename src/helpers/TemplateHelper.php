<?php
namespace naboo\siteduplicate\helpers;

use Craft;

/**
 * Template helper class.
 *
 * @package     siteduplicate/helpers
 * @author      Johan Strömqvist
 * @version     1.0
 */
class TemplateHelper
{
	// Constants
	// =========================================================================
	
	// Public static methods
    // =========================================================================

	/**
	 * Get CP template
	 *
	 * @param str, array
	 * @return html
	 */
	public static function getCpTemplate($path, $data=[])
	{
		$view = Craft::$app->getView();

        $templateMode = $view->getTemplateMode();

        $view->setTemplateMode($view::TEMPLATE_MODE_CP);
        
		$template = $view->renderTemplate($path, $data);

        $view->setTemplateMode($templateMode);

        return $template;
	}

	/**
	 * Get SITE template
	 *
	 * @param str, array
	 * @return html
	 */
	public static function getSiteTemplate($path, $data=[])
	{
		$view = Craft::$app->getView();

        $templateMode = $view->getTemplateMode();

        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        
		$template = $view->renderTemplate($path, $data);

        $view->setTemplateMode($templateMode);

        return $template;
	}
}