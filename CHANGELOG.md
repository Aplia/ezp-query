# Changelog

Changelog for the `contentquery` package.

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
