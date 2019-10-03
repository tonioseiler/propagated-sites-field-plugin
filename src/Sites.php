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

        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            [$this, 'afterElementSave']);
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

    public function afterElementSave(Event $event) {
        if ($event->sender instanceof Entry) {
            $entry = $event->sender;

            $field = $this->getSiteField($entry);

            if ($field != null && $field->propagate) {
                $siteIds = $entry->getFieldValue($field->handle);
                foreach (Craft::$app->sites->allSiteIds as $siteId) {
                    if(array_search($siteId, $siteIds) === false) {
                        try {
                            //hier den eintrag aus `content` (`elementId`, `siteId`) löschen und aus elements sites
                            Craft::$app->db->createCommand()->delete('content', 'elementId = :elementId AND siteId = :siteId', [':elementId' => $entry->id, ':siteId' => $siteId])->execute();
                            Craft::$app->db->createCommand()->delete('elements_sites', 'elementId = :elementId AND siteId = :siteId', [':elementId' => $entry->id, ':siteId' => $siteId])->execute();
                        } catch (ElementNotFoundException $e) {
                            Craft::error('Error while propagting entry to sites.');
                            throw new Exception('Error while propagting entry to sites.');
                        }

                    }
                }
            }
        }
    }

    protected function getSiteField(Entry $entry) {
        //find sites field
        $fieldValues = $entry->getFieldValues();
        $siteFields = Craft::$app->fields->allFields;
        $siteFieldHandle = 'sites';

        foreach ($fieldValues as $handle => $value) {
            foreach ($siteFields as $field) {
                if(get_class($field) == 'furbo\sitesfield\fields\SitesField') {
                    return $field;
                }
            }
        }
        return null;
    }

}
