# Changelog

Changelog for the `contentquery` package.

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
