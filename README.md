# Site Duplicate plugin for Craft CMS 3.x

Site Duplicate let's you duplicate entries across Sites in Craft CMS. It even works when Section entries aren't set to *propagate across site sections* making it easy to duplicate entry data between Sites.

## Contents

- [License](#license)
- [Requirements](#installation)
- [Usage](#usage)
- [How It Works](#how-it-works)
- [Known limitations](#known-limitations)
- [Roadmap](#roadmap)
- [Credits](#credits)

## License

This plugin is licensed for free under the MIT License. Please see the LICENSE file for details.

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Usage

Install the plugin from the Plugin Store or using composer.

```
composer require naboo/craft-siteduplicate
```

## How It Works

The plugin enables you to add a sidebar widget to selected Sections for which you would like to be able to duplicate entries across Sites. The widget will display enabled Sites for the current section. Notice! Some limitations need to be kept in mind - please see the [Known limitations](#known-limitations) section for more info.

### Known limitations

There are some things to keep in mind when duplicating entries across Site sections. Here's a list of known limitations - and potential workarounds - to keep in mind before duplicating.

### Issues with element relations

If the site you are duplicating to is set to not have its entries propagated scross site sections the section might not be able to have the same type of relations as the Section you are duplicating from. For example you might want to duplicate a "page entry" from Site A which has a "Entries" relations field. The entry might has a relation to another "page entry" in Site A. When duplicating the entry to Site B this relation isn't available to Site B since the related entry exists only in Site A. This will cause Craft to throw a *validation error* when duplicating the entry, leaving the duplicated entry in Site A.

But there is a workaround. Before duplicating the entry from Site A you can remove/disable the relation since the plugin will actually duplicate what's on the screen - not what's in the database. So let's say you want to duplicate an entry that has a relation - before duplicating the entry you'll remove the relation (only on screen - you don't need to save the entry in Site A) like this:

----- insert screenshot

## Roadmap

### Version 1

- More functions
- Better documentation
- Drink more wine
- Plugin Store release

## Credits

Brought to you by [Johan Strömqvist](http://www.naboovalley.com)
