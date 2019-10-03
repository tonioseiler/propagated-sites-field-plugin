<?php
/**
 * Section Field plugin for Craft 3.0
 * @copyright Furbo GmbH
 */

namespace furbo\sitesfield\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;

use yii\db\Schema;

/**
 * This field allows a selection from a configured set of sites.
 */
class SitesField extends Field implements PreviewableFieldInterface
{

    /**
	 * @var bool Wether or not the entry should be propageted to the sites selected in the sites field.
	 */
	public $propagate = true;

	/**
	 * @var array What sites have been whitelisted as selectable for this field.
	 */
	public $whitelistedSites = [];

    /**
     * @var bool Whether or not the field allows multiple selections.
     * -> not used anymore but breaks backwards compatibility if removed
     */
    public $allowMultiple = true;

	/**
	 * @inheritdoc
	 * @see craft\base\ComponentInterface
	 */
	public static function displayName(): string
	{
		return \Craft::t('sites-field', 'Sites');
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public static function hasContentColumn(): bool
	{
		return true;
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function getContentColumnType(): string
	{
		return Schema::TYPE_STRING;
	}

	/**
	 * @inheritdoc
	 * @see craft\base\SavableComponentInterface
	 */
	public function getSettingsHtml(): string
	{
		return Craft::$app->getView()->renderTemplate(
			'sites-field/_settings',
			[
				'field' => $this,
				'sites' => $this->getSites()
			]
		);
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function rules(): array
	{
		$rules = parent::rules();

		$rules[] = [['whitelistedSites'], 'validateSitesWhitelist'];

		return $rules;
	}

	/**
	 * Ensures the site IDs selected for the whitelist are for valid sites.
	 * @param string $attribute The name of the attribute being validated.
	 * @return void
	 */
	public function validateSitesWhitelist(string $attribute) {

		$sites = $this->getSites();

		foreach ($this->whitelistedSites as $site) {
			if (!isset($sites[$site])) {
				$this->addError($attribute, Craft::t('sites-field', 'Invalid site selected.'));
			}
		}
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function getInputHtml($value, ElementInterface $element = null): string
	{

		$sites = $this->getSites(); // Get all sites available to the current user.
		$whitelist = array_flip($this->whitelistedSites); // Get all whitelisted sites.
		$whitelist[''] = true; // Add a blank entry in, in case the field's options allow a 'None' selection.

		$whitelist = array_intersect_key($sites, $whitelist); // Discard any sites not available within the whitelist.

        if(empty($element->id)) $value = [$element->siteId];

		return Craft::$app->getView()->renderTemplate(
			'sites-field/_input', [
				'field' => $this,
				'value' => $value,
				'sites' => $whitelist,
                'currentSiteId' => $element->siteId
			]
		);
	}

	/**
	 * @inheritdoc
	 * @see craft\base\Field
	 */
	public function getElementValidationRules(): array
	{
		return [
			['validateSites'],
		];
	}

	/**
	 * Ensures the site IDs selected are available to the current user.
	 * @param ElementInterface $element The element with the value being validated.
	 * @return void
	 */
	public function validateSites(ElementInterface $element)
	{
		$value = $element->getFieldValue($this->handle);
		$sites = $this->getSites();

        if (is_array($value)) {
            foreach ($value as $id) {
                if (!isset($sites[$id])) {
                    $element->addError($this->handle, Craft::t('sites-field', 'Invalid site selected.'));
                }
            }
        } else {
            if (!isset($sites[$value])) {
                $element->addError($this->handle, Craft::t('sites-field', 'Invalid site selected.'));
            }
        }

	}

	/**
	 * Retrieves all sites in an id, name pair, suitable for the underlying options display.
	 */
	private function getSites() {
		$sites = [];
		foreach (Craft::$app->getSites()->getAllSites() as $site) {
			$sites[$site->id] = Craft::t('site', $site->name);
		}
		return $sites;
    }

    /**
	 * @inheritdoc
	 */
	public function normalizeValue ($value, ElementInterface $element = null)
	{
		return (is_array($value)) ? $value : json_decode($value);
	}

}
