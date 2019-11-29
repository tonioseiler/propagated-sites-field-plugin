# Sites Field for Craft 3

This plugin provides a field type for choosing craft sites. Entries using this field can then access the site ID in templates, for example for filtering.
The entries are  propagated to the selected sites only.
---

## Requirements

* Craft CMS 3.0.0-RC1 or above

## Example Usage

To display only events related to the current site you could use the following logic:

```twig
{% set events = craft.entries.site('main').section('blog').type('event').orderBy('date asc').all() %}
{% for event in events %}
  {% if craft.app.sites.currentSite.id in event.siteIds %}
    {# show this event... #}
  {% endif %}
{% endfor %}
```

> Note: `siteIds` returns an array of site ID's which you can use the twig [in](https://twig.symfony.com/doc/2.x/templates.html#containment-operator) operator.


## Installation

- Over Craft Plugin Store

OR

- Clone repo from here:
https://bitbucket.org/seilersteinbachgmbh/propagated-sites-field-plugin/src/master/
- add repo to composer json of your craft installation like this:
```
"repositories": [
    ...
    {
        "type": "path",
        "url": "/path-to-repo/propagated-sites-field"
    }
]
```

- Install plugin via composer
```
composer require furbo/propagated-sites-field
```
- Plugin über Backend aktivieren
- Create field from Type Sites (in the current release fieldhandle must be "sites")
- Add Field to entries and save

### Show field in frontend

If the entry is saved in frontend, the same propagtion logix apears

```
<label>Event Regions</label>
{% set currentsite = craft.app.fields.getFieldByHandle('sites') %}
{% set sites = craft.app.sites.getAllSites() %}
<input type="hidden" id="fields-sites" name="fields[sites][]" value="{{ currentSite.id }}">
	<br />
	{% for site in sites %}

        {% set checked = true ? 'checked=""' : '' %}
        <div>
            <input type="checkbox" value="{{site.id}}" class="checkbox" id="fields-sites-{{site.id}}" name="fields[sites][]" {{checked}}>
            <label for="fields-sites-{{site.id}}">{{site.name}}</label>
        </div>

    {% endfor %}
</div>
```
