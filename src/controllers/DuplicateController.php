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
        $this->requirePostRequest();

        $entry = $this->_getEntryModel();
        $request = Craft::$app->getRequest();

        $newSiteId = $request->getBodyParam('duplicateSiteId', $entry->siteId); // SET SITE ID -------------------------------------------- * EDITED *

        if($newSiteId == null || $newSiteId == "" || $newSiteId == 0 || $newSiteId == "0") // DON'T HAVE A SITE ID? ----------------------- * EDITED *
        {
            Craft::$app->getSession()->setError(Craft::t('siteduplicate', 'No Site selected.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'entry' => $entry
            ]);

            return null;
        }

        // Are we duplicating the entry?
        $duplicate = true; // LET'S FORCE THE DUPLICATION --------------------------------------------------------------------------------- * EDITED *

        // Permission enforcement
        $this->enforceEditEntryPermissions($entry, $duplicate);
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Is this another user's entry (and it's not a Single)?
        if (
            $entry->id &&
            $entry->authorId != $currentUser->id &&
            $entry->getSection()->type !== Section::TYPE_SINGLE &&
            $entry->enabled
        ) {
            // Make sure they have permission to make live changes to those
            $this->requirePermission('publishPeerEntries:' . $entry->sectionId);
        }

        // If we're duplicating the entry, swap $entry with the duplicate
        if ($duplicate) {
            try {
                $entry = Craft::$app->getElements()->duplicateElement($entry);
            } catch (InvalidElementException $e) {
                /** @var Entry $clone */
                $clone = $e->element;

                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'errors' => $clone->getErrors(),
                    ]);
                }

                Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t duplicate entry.'));

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

        $entry->siteId = $newSiteId; // SET SITE ID --------------------------------------------------------------------------------------- * EDITED *

        // Populate the entry with post data
        $this->_populateEntryModel($entry);

        // Even more permission enforcement
        if ($entry->enabled) {
            if ($entry->id) {
                $this->requirePermission('publishEntries:' . $entry->sectionId);
            } else if (!$currentUser->can('publishEntries:' . $entry->sectionId)) {
                $entry->enabled = false;
            }
        }

        // WE WILL NEVER HAVE ANY VERSIONS SINCE THIS IF THE FIRST ENTRY ------------------------------------------------------------------ * EDITED *

        // Make sure the entry has at least one version if the section has versioning enabled
        /*$revisionsService = Craft::$app->getEntryRevisions();
        if ($entry->getSection()->enableVersioning && $entry->id && !$revisionsService->doesEntryHaveVersions($entry->id, $entry->siteId)) {
            $currentEntry = Craft::$app->getEntries()->getEntryById($entry->id, $entry->siteId);
            $currentEntry->revisionCreatorId = $entry->authorId;
            $currentEntry->revisionNotes = 'Revision from ' . Craft::$app->getFormatter()->asDatetime($entry->dateUpdated);
            $revisionsService->saveVersion($currentEntry);
        }*/

        // Save the entry (finally!)
        if ($entry->enabled && $entry->enabledForSite) {
            $entry->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $entry->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save entry.'));

            // Send the entry back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'entry' => $entry
            ]);

            return null;
        }

        // WE WILL NEVER HAVE ANY VERSIONS SINCE THIS IF THE FIRST ENTRY ------------------------------------------------------------------ * EDITED *

        // Should we save a new version?
        /*if ($entry->getSection()->enableVersioning) {
            $revisionsService->saveVersion($entry);
        }*/

        if ($request->getAcceptsJson()) {
            $return = [];

            $return['success'] = true;
            $return['id'] = $entry->id;
            $return['title'] = $entry->title;
            $return['slug'] = $entry->slug;

            if ($request->getIsCpRequest()) {
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

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Entry saved.'));

        return $this->redirectToPostedUrl($entry);
    }

    // Private functions
	// =========================================================================

    /**
     * Populates an Entry with post data.
     *
     * THIS FUNCTION IS A DIRECT COPY from controllers/EntriesController in Craft core
     *
     * @param Entry $entry
     */
    private function _populateEntryModel(Entry $entry)
    {
        $request = Craft::$app->getRequest();

        // Set the entry attributes, defaulting to the existing values for whatever is missing from the post data
        $entry->typeId = $request->getBodyParam('typeId', $entry->typeId);
        $entry->slug = $request->getBodyParam('slug', $entry->slug);
        if (($postDate = $request->getBodyParam('postDate')) !== null) {
            $entry->postDate = DateTimeHelper::toDateTime($postDate) ?: null;
        }
        if (($expiryDate = $request->getBodyParam('expiryDate')) !== null) {
            $entry->expiryDate = DateTimeHelper::toDateTime($expiryDate) ?: null;
        }
        $entry->enabled = (bool)$request->getBodyParam('enabled', $entry->enabled);
        $entry->enabledForSite = $entry->getSection()->getHasMultiSiteEntries()
            ? (bool)$request->getBodyParam('enabledForSite', $entry->enabledForSite)
            : true;
        $entry->title = $request->getBodyParam('title', $entry->title);

        if (!$entry->typeId) {
            // Default to the section's first entry type
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }

        // Prevent the last entry type's field layout from being used
        $entry->fieldLayoutId = null;

        $fieldsLocation = $request->getParam('fieldsLocation', 'fields');
        $entry->setFieldValuesFromRequest($fieldsLocation);

        // Author
        $authorId = $request->getBodyParam('author', ($entry->authorId ?: Craft::$app->getUser()->getIdentity()->id));

        if (is_array($authorId)) {
            $authorId = $authorId[0] ?? null;
        }

        $entry->authorId = $authorId;

        // WE WILL NEVER HAVE ANY PARENTS SINCE THIS IF THE FIRST ENTRY ---------------------------------------------------------------- * EDITED *

        // Parent
        /*if (($parentId = $request->getBodyParam('parentId')) !== null) {
            if (is_array($parentId)) {
                $parentId = reset($parentId) ?: '';
            }

            $entry->newParentId = $parentId ?: '';
        }*/

        // Revision notes
        $entry->revisionNotes = $request->getBodyParam('revisionNotes');
    }

    /**
     * Fetches or creates an Entry.
     *
     * THIS FUNCTION IS A DIRECT COPY from controllers/EntriesController in Craft core
     *
     * @return Entry
     * @throws NotFoundHttpException if the requested entry cannot be found
     */
    private function _getEntryModel(): Entry
    {
        $request = Craft::$app->getRequest();
        $entryId = $request->getBodyParam('entryId');
        $siteId = $request->getBodyParam('siteId');

        if ($entryId) {
            $entry = Craft::$app->getEntries()->getEntryById($entryId, $siteId);

            if (!$entry) {
                throw new NotFoundHttpException('Entry not found');
            }
        } else {
            $entry = new Entry();
            $entry->sectionId = $request->getRequiredBodyParam('sectionId');

            if ($siteId) {
                $entry->siteId = $siteId;
            }
        }

        return $entry;
    }
}