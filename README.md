# Aplia Content Query

A set of classes to help with running queries against Content Objects/Nodes.
It is divided in three main areas.

- queryset
- pagination
- sorting
- filtering

All classes support the eZ template attribute system and can be passed directly
to templates.

## QuerySet

This class combines the functionality of all the other system into one easy
to use interface. Once instantiated there are several methods which can be
used to filter the set and return a new queryset instance.

Example, list all children of the root node:

```
<?php
$set = new QuerySet();
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

List only articles of the root node:

```
<?php
$set = new QuerySet();
$set = $set->classes('article');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

List entire tree of a given node (node id 42):

```
<?php
$set = new QuerySet();
$set = $set->depth(false)->parentNode(42);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Change sort order to alphabetical:

```
<?php
$set = new QuerySet();
$set = $set->sortByField('a-z');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Use pagination and fetch specific page:

```
<?php
$set = new QuerySet();
$set = $set->pageLimit(50)->page(5);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Filter by fields:

```
<?php
$set = new QuerySet();
$set = $set
  ->defineFilter('title', 'string', 'article/title')
  ->filter('title', 'My title');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

To explicitly get the result object (e.g. for templates) use `result()`.

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
