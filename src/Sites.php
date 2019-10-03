<?php
/**
 * Sites Field plugin for Craft 3.0
 * @copyright Furbo GmbH
 */

namespace furbo\sitesfield;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\errors\ElementNotFoundException;

use furbo\sitesfield\fields\SitesField;

use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class Sites extends Plugin
{

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		parent::init();

		Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, [$this, 'registerFieldTypes']);
        /*Event::on(
            Element::class,
            Element::EVENT_BEFORE_SAVE,
            [$this, 'beforeElementSave']);*/

        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            [$this, 'afterElementSave']);

        //das geht so nicht
        /*Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            [$this, 'afterElementSave']);*/
	}

	/**
	 * Registers the field type provided by this plugin.
	 * @param RegisterComponentTypesEvent $event The event.
	 * @return void
	 */
	public function registerFieldTypes(RegisterComponentTypesEvent $event)
	{
		$event->types[] = SitesField::class;
	}

    public function beforeElementSave(Event $event)
	{
        //so gehts mit enabling / disabling
        if ($event->sender instanceof Entry) {
            $entry = $event->sender;
            $siteIds = $entry->getFieldValue('sites');
            /*dd(array_search($entry->siteId, $siteIds));*/
            if(array_search($entry->siteId, $siteIds) === false) {
                $entry->enabledForSite = false;
            } else {
                $entry->enabledForSite = true;
            }
        }
    }

    public function afterElementSave(Event $event) {
        if ($event->sender instanceof Entry) {
            $entry = $event->sender;
            $siteIds = $entry->getFieldValue('sites');
            /*dd(array_search($entry->siteId, $siteIds));*/
            foreach (Craft::$app->sites->allSiteIds as $siteId) {
                if(array_search($siteId, $siteIds) === false) {
                    try {
                        //hier den eintrag aus `content` (`elementId`, `siteId`) löschen und aus elements sites
                        Craft::$app->db->createCommand()->delete('content', 'elementId = :elementId AND siteId = :siteId', [':elementId' => $entry->id, ':siteId' => $siteId])->execute();
                        Craft::$app->db->createCommand()->delete('elements_sites', 'elementId = :elementId AND siteId = :siteId', [':elementId' => $entry->id, ':siteId' => $siteId])->execute();
                    } catch (ElementNotFoundException $e) {
                        dd($tmp);
                    }

                }
            }
        }
    }

    //funktioniert nicht, just a nice try
    /*public function afterElementSave(Event $event)
	{
        if ($event->sender instanceof Entry) {
            $entry = $event->sender;

            //den folgende code habe ich von hier übernommen
            //https://github.com/craftcms/cms/blob/master/src/services/Elements.php#L1808
            if (!$entry->propagating) {
                $siteIds = $entry->getFieldValue('sites');
                dd();
                dd(array_search($entry->id, $siteIds));

                foreach ($siteIds as $siteId) {

                    $siteInfo = ['enabledByDefault' => '1', 'siteId' => $siteId];
                    // Skip the master site
                    if ($siteInfo['siteId'] != $entry->siteId) {
                        //this is private so I copy it from here
                        // https://github.com/craftcms/cms/blob/master/src/services/Elements.php#L2056
                        $this->_propagateElement($entry, $event->isNew, $siteInfo);

                    }
                }
            }
        }
	}*/

    /**
     * Propagates an element to a different site
     *
     * @param ElementInterface $element
     * @param bool $isNewElement
     * @param array $siteInfo
     * @param ElementInterface|null $siteElement The element loaded for the propagated site
     * @throws Exception if the element couldn't be propagated
     */
    protected function _propagateElement(ElementInterface $element, bool $isNewElement, array $siteInfo, ElementInterface $siteElement = null)
    {
        /** @var Element $element */
        // Try to fetch the element in this site
        /** @var Element|null $siteElement */
        if ($siteElement === null && !$isNewElement) {
            $siteElement = Craft::$app->elements->getElementById($element->id, get_class($element), $siteInfo['siteId']);
        }
        // If it doesn't exist yet, just clone the master site
        if ($isNewSiteForElement = ($siteElement === null)) {
            $siteElement = clone $element;
            $siteElement->siteId = $siteInfo['siteId'];
            $siteElement->contentId = null;
            $siteElement->enabledForSite = $siteInfo['enabledByDefault'];
            // Keep track of this new site ID
            //$element->newSiteIds = [];
            //$element->newSiteIds[] = $siteInfo['siteId'];
        } else if ($element->propagateAll) {
            $oldSiteElement = $siteElement;
            $siteElement = clone $element;
            $siteElement->siteId = $oldSiteElement->siteId;
            $siteElement->contentId = $oldSiteElement->contentId;
            $siteElement->enabledForSite = $oldSiteElement->enabledForSite;
        } else {
            $siteElement->enabled = $element->enabled;
            $siteElement->resaving = $element->resaving;
        }
        // Copy any non-translatable field values
        if ($element::hasContent()) {
            if ($isNewSiteForElement) {
                // Copy all the field values
                $siteElement->setFieldValues($element->getFieldValues());
            } else if (($fieldLayout = $element->getFieldLayout()) !== null) {
                // Only copy the non-translatable field values
                foreach ($fieldLayout->getFields() as $field) {
                    /** @var Field $field */
                    // Does this field produce the same translation key as it did for the master element?
                    if ($field->getTranslationKey($siteElement) === $field->getTranslationKey($element)) {
                        // Copy the master element's value over
                        $siteElement->setFieldValue($field->handle, $element->getFieldValue($field->handle));
                    }
                }
            }
        }
        // Save it
        $siteElement->setScenario(Element::SCENARIO_ESSENTIALS);
        $siteElement->propagating = true;
        if (Craft::$app->elements->saveElement($siteElement, true, false) === false) {
            // Log the errors
            $error = 'Couldn’t propagate element to other site due to validation errors:';
            foreach ($siteElement->getFirstErrors() as $attributeError) {
                $error .= "\n- " . $attributeError;
            }
            Craft::error($error);
            throw new Exception('Couldn’t propagate element to other site.');
        }
    }
}
