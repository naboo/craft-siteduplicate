<?php
namespace naboo\siteduplicate\controllers;

/* All from controllers/EntriesController in Craft core */
use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\InvalidElementException;
use craft\events\ElementEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\models\EntryDraft;
use craft\models\EntryVersion;
use craft\models\Section;
use craft\models\Site;
use craft\web\assets\editentry\EditEntryAsset;
use DateTime;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

use craft\controllers\BaseEntriesController;

use naboo\siteduplicate\SiteDuplicatePlugin;

/**
 * Duplicate controller class.
 *
 * @package     siteduplicate/controllers
 * @author      Johan Strömqvist
 * @version     1.0
 */
class DuplicateController extends BaseEntriesController
{
    // Constants
    // =========================================================================

    // Protected Properties
    // =========================================================================

    protected $allowAnonymous = ['index'];

    // Public Properties
    // =========================================================================

    // Inheritance
    // =========================================================================

    // Public methods
    // =========================================================================

    /**
     * Duplicate entry
     *
     * Duplicate entry between Site Section(s). This function is a cut-up version of the
     * controllers/EntriesController->actionSaveEntry function in Craft core.
     */
    public function actionIndex()
    {
        // Force duplication
        $duplicate = true;

        $this->requirePostRequest();

        $entry = $this->_getEntryModel();
        $entryVariable = $this->request->getValidatedBodyParam('entryVariable') ?? 'entry';
        $newSiteId = $this->request->getBodyParam('duplicateSiteId', $entry->siteId);

        // Permission enforcement
        $this->enforceSitePermission($entry->getSite());
        $this->enforceEditEntryPermissions($entry, $duplicate);
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Is this another user's entry (and it's not a Single)?
        if (
            $entry->id &&
            !$duplicate &&
            $entry->authorId != $currentUser->id &&
            $entry->getSection()->type !== Section::TYPE_SINGLE &&
            $entry->enabled
        ) {
            // Make sure they have permission to make live changes to those
            $this->requirePermission('publishPeerEntries:' . $entry->getSection()->uid);
        }

        // Keep track of whether the entry was disabled as a result of duplication
        $forceDisabled = false;

        // If we're duplicating the entry, swap $entry with the duplicate
        if ($duplicate) {
            try {
                $wasEnabled = $entry->enabled;
                $entry = Craft::$app->getElements()->duplicateElement($entry);
                if ($wasEnabled && !$entry->enabled) {
                    $forceDisabled = true;
                }
            } catch (InvalidElementException $e) {
                /** @var Entry $clone */
                $clone = $e->element;

                if ($this->request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'errors' => $clone->getErrors(),
                    ]);
                }

                $this->setFailFlash(Craft::t('app', 'Couldn’t duplicate entry.'));

                // Send the original entry back to the template, with any validation errors on the clone
                $entry->addErrors($clone->getErrors());
                Craft::$app->getUrlManager()->setRouteParams([
                    'entry' => $entry
                ]);

                return null;
            } catch (\Throwable $e) {
                throw new ServerErrorHttpException(Craft::t('app', 'An error occurred when duplicating the entry.'), 0, $e);
            }
        }

        // Set new site ID
        $entry->siteId = $newSiteId;

        // Populate the entry with post data
        $this->_populateEntryModel($entry);

        if ($forceDisabled) {
            $entry->enabled = false;
        }

        // Even more permission enforcement
        if ($entry->enabled) {
            if ($entry->id) {
                $this->requirePermission('publishEntries:' . $entry->getSection()->uid);
            } else if (!$currentUser->can('publishEntries:' . $entry->getSection()->uid)) {
                $entry->enabled = false;
            }
        }

        // Save the entry (finally!)
        if ($entry->enabled && $entry->getEnabledForSite()) {
            $entry->setScenario(Element::SCENARIO_LIVE);
        }

        try {
            $success = Craft::$app->getElements()->saveElement($entry);
        } catch (UnsupportedSiteException $e) {
            $entry->addError('siteId', $e->getMessage());
            $success = false;
        }

        if (!$success) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $entry->getErrors(),
                ]);
            }

            $this->setFailFlash(Craft::t('app', 'Couldn’t save entry.'));

            // Send the entry back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                $entryVariable => $entry
            ]);

            return null;
        }

        if ($this->request->getAcceptsJson()) {
            $return = [];

            $return['success'] = true;
            $return['id'] = $entry->id;
            $return['title'] = $entry->title;
            $return['slug'] = $entry->slug;

            if ($this->request->getIsCpRequest()) {
                $return['cpEditUrl'] = $entry->getCpEditUrl();
            }

            if (($author = $entry->getAuthor()) !== null) {
                $return['authorUsername'] = $author->username;
            }

            $return['dateCreated'] = DateTimeHelper::toIso8601($entry->dateCreated);
            $return['dateUpdated'] = DateTimeHelper::toIso8601($entry->dateUpdated);
            $return['postDate'] = ($entry->postDate ? DateTimeHelper::toIso8601($entry->postDate) : null);

            return $this->asJson($return);
        }

        $this->setSuccessFlash(Craft::t('app', 'Entry saved.'));
        return $this->redirectToPostedUrl($entry);
    }

    // Private functions
	// =========================================================================

    /**
     * Populates an Entry with post data.
     *
     * [This function is copied from controllers/EntriesController in Craft core]
     *
     * @param Entry $entry
     */
    private function _populateEntryModel(Entry $entry)
    {
        // Set the entry attributes, defaulting to the existing values for whatever is missing from the post data
        $entry->typeId = $this->request->getBodyParam('typeId', $entry->typeId);
        $entry->slug = $this->request->getBodyParam('slug', $entry->slug);
        if (($postDate = $this->request->getBodyParam('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = $this->request->getBodyParam('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }

        $enabledForSite = $this->enabledForSiteValue();
        if (is_array($enabledForSite)) {
            // Set the global status to true if it's enabled for *any* sites, or if already enabled.
            $entry->enabled = in_array(true, $enabledForSite, false) || $entry->enabled;
        } else {
            $entry->enabled = (bool)$this->request->getBodyParam('enabled', $entry->enabled);
        }
        $entry->setEnabledForSite($enabledForSite ?? $entry->getEnabledForSite());
        $entry->title = $this->request->getBodyParam('title', $entry->title);

        if (!$entry->typeId) {
            // Default to the section's first entry type
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }

        // Prevent the last entry type's field layout from being used
        $entry->fieldLayoutId = null;

        $fieldsLocation = $this->request->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        // Author
        $authorId = $this->request->getBodyParam('author', ($entry->authorId ?: Craft::$app->getUser()->getIdentity()->id));

        if (is_array($authorId)) {
            $authorId = $authorId[0] ?? null;
        }

        $entry->authorId = $authorId;

        // Parent
        if (($parentId = $this->request->getBodyParam('parentId')) !== null) {
            if (is_array($parentId)) {
                $parentId = reset($parentId) ?: '';
            }

            $entry->newParentId = $parentId ?: '';
        }

        // Revision notes
        $entry->setRevisionNotes($this->request->getBodyParam('revisionNotes'));
    }

    /**
     * Fetches or creates an Entry.
     *
     * [This function is copied from controllers/EntriesController in Craft core]
     *
     * @return Entry
     * @throws NotFoundHttpException if the requested entry cannot be found
     */
    private function _getEntryModel(): Entry
    {
        $entryId = $this->request->getBodyParam('draftId') ?? $this->request->getBodyParam('sourceId') ?? $this->request->getBodyParam('entryId');
        $siteId = $this->request->getBodyParam('siteId');

        if ($entryId) {
            $entry = Craft::$app->getEntries()->getEntryById($entryId, $siteId);

            if (!$entry) {
                throw new NotFoundHttpException('Entry not found');
            }
        } else {
            $entry = new Entry();
            $entry->sectionId = $this->request->getRequiredBodyParam('sectionId');

            if ($siteId) {
                $entry->siteId = $siteId;
            }
        }

        return $entry;
    }
}