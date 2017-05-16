# Changelog

Changelog for the `contentquery` package.

## 1.7.0
- Support for modifiers on FieldFilter classes, pass the `fieldModifiers` and `valueModifiers`
  parameters.
- Support for modifiers on the attribute filter string, e.g. `'company/name:first_letter='`,
  these are passed to the FieldFilter classes.
- Support for nested filter structures on QuerySet objects by calling `addFilter`.
  The filters are passed directly to the NestedFilter system.

## 1.6.0
- New static method `ContentHelper::getNode` for fetching a content node based on input value.
  Can fetch node from content node, content object or numeric ID.
- Support for sort arrays in `QuerySet`, use `sortByArray`.
- Added more default sort fields, taken from the supported fields by eZ publish.

## 1.5.1
- Support additional attribute entries. This makes the attributes
  `count`, `items` and `iterator` available to templates.

## 1.5.0
- Filter mode now defaults to 'nested'

## 1.4.1
- Default visibility rules can now be turned on/off with `visibility()`.
  Also setting a filter on `visibility`/`visible` will turn off default
  visibility rules.
- Result list can be limited to main node only with `onlyMainNodes()`
- Default policies based on database roles can be turned on/off with
  `policies()`. Using a boolean with turn on/off the defaults.
  Using an array will turn off roles and use a custom policy list.
- `result()` and `items()` can now return nodes as value arrays instead of
  objects by setting `$asObject`.

## 1.4.0
- New package name `aplia/query`

## 1.3.0
- New method `loadFilters` for adding filters from the content class attributes.

## 1.2.0
- Depth and page limit is now off by default. The query set will encompass the entire
  set according to the filters.
- New method `count()` to return the count for the current set.
- Sort mode is now off by default.
- New method `items()`  to return just the item array.
- New method `iterator()` to safely and easily iterate over all items in the set.
  The iterator will use paged loading internally to avoid loading too many items
  at a time.

## 1.1.0

- Introduced new class `QuerySet` which provides a simpler interface for working
  with content queries. It combines filtering, sorting and paging into one
  class.
- Added helper class `ContentHelper` which has static methods for extracting
  node IDs from various object types.
- Added exception classes `FilterError`, `FilterTypeError`, `QueryError`, `TypeError`.
- Result instances are now iterable, and can be passed to `foreach`.

## 1.0.1

- First official version for use with composer.
