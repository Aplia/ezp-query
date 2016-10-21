# Aplia Content Query

A set of classes to help with running queries against Content Objects/Nodes.
It is divided in three main areas.

- pagination
- sorting
- filtering

All classes support the eZ template attribute system and can be passed directly
to templates.

## Pagination

`BaseNumPagination` defines the main interface for all pagination concrete classes.

A concrete implementation is `PageNumPagination` which provides pagination based
on numeric page numbers (1 and up).

## Sorting

Sorting is handled by the `SortOrder` class, it provides a way to define possible
sort columns for an API and also which sort column and order has been chosen.

## Filtering

...

## Results

After running the query a result object is returned, it is of type `Result`
and contains all the results of the query, including items, total count,
page object, sort order and filters.
