# Aplia Content Query

A set of classes to help with running queries against Content Objects/Nodes.
It is divided in three main areas.

- pagination
- sorting
- filtering

All classes support the eZ template attribute system and can be passed directly
to templates.

## QuerySet

This class combines the functionality of all the other system into one easy
to use interface. Once instantiated there are several methods which can be
used to filter the set and return a new queryset instance.

### Iterator usage

The query-set acts as an iterator and can be passed to foreach statements
directly.

```
<?php
$set = new QuerySet();
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

The query-set is lazy and will only evaluate when needed. It will also
cache results so iterating a second time without changing any filters
will not access the database.

### Chaining and cloning

Most methods are designed to return a query-set instance upon completion
to allow for chaining multiple methods.

For instance:
```
<?php
$set = new QuerySet();
$newSet = $set->depth(1)->sortByField('a-z');
// $set and $newSet are the same instance
```

The query-set can also be instructed to create new clones for each
change that is made to it, this means that the chaining methods
will create new copies and that the original instance is kept as-is.

```
<?php
$set = new QuerySet(array('useClone' => true));
$newSet = $set->depth(1)->sortByField('a-z');
// $set and $newSet are different instances
```

To explicitly create a new clone call `copy()`:
```
<?php
$set = new QuerySet();
$newSet = $set->copy()->depth(1)->sortByField('a-z');
// $set and $newSet are different instances
```


### List all children

Example, list all children of the root node:

```
<?php
$set = new QuerySet();
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### List specific content class

List only articles of the root node:

```
<?php
$set = new QuerySet();
$set = $set->classes('article');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('classes' => array('article')));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### List tree structure

List entire tree of a given node (node id 42):

```
<?php
$set = new QuerySet();
$set = $set->depth(false)->parentNode(42);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('depth' => false, 'parentNodeId' => 42));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### List specific depth

List a specific depth using an operator:

```
<?php
$set = new QuerySet();
$set = $set->depth(2, '>=');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('depth' => false, 'depthOperator' => '>='));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Change sort order

Change sort order to alphabetical:

```
<?php
$set = new QuerySet();
$set = $set->sortByField('a-z');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Creating a new sort field and sorting on that:

```
<?php
$set = new QuerySet();
$set = $set->sortChoices(array('age' => 'created'))->sortByField('-age');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Use a regular sort array:

```
<?php
$set = new QuerySet();
$set = $set->sortByArray(array(
    array('published', 1),
));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Paginate results

Use pagination and fetch specific page:

```
<?php
$set = new QuerySet();
$set = $set->pageLimit(50)->page(5);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Filter on fields

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

Filters may also define modifiers on the field or value by
using the advanced syntax from NestedFilter.

e.g. to use a modifier called `first_letter` change the
attribute name to include a `colon` and then the modifier
followed by another `colon` and the operator. The modifier
must first be defined in `filter.ini` and point to a valid
static method, see `aplia/query` package for more details
on modifiers.

Example which finds all articles that starts with `M`

```
<?php
$set = new QuerySet();
$set = $set
  ->defineFilter('title', 'string', 'article/title:first_letter:=')
  ->filter('title', 'M');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```


### Define filters from database

Load filters from the content class:

```
<?php
$set = new QuerySet();
$set = $set
  ->classes('article')
  ->loadFilters()
  ->filter('article/title', 'My title');
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```


### Custom nested filters

If the default filter system is too limiting then the
custom filters may be used instead. They are nested
filters which are passed to the NestedFilter system
(`aplia/query` package) which supports arbitrary
nesting of AND/OR structures with filters.

Call the `addFilter` method on the query set and
pass the filters you want. Calling it multiple times
will AND the filters together.

Custom filters can be used together with normal filters.

```
<?php
$set = new QuerySet();
$set = $set
  ->classes('article')
  ->addFilter(
  array('article/title', 'My title', '=')
)
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Accessing result object

To explicitly get the result object (e.g. for templates) use `result()`.

```
<?php
$set = new QuerySet();
$result = $set->result();
```

The result object contains enough information to be used in a template,
for instance showing the total count, used filters and listing items.


### Accessing items explicitly

The query-set acts as an iterator but if you need the item list
directly call `items()`:

```
<?php
$set = new QuerySet();
foreach ($set->items() as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Control visibilty

Turn off default visibilty rules:

```
<?php
$set = new QuerySet();
$set = $set
  ->visibilty(false);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('useVisibility' => false));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}

```

Only list hidden items (this turns off default rules):

```
<?php
$set = new QuerySet();
$set = $set
  ->filter('visible', false);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Limit to main nodes

Limit result to only main nodes:

```
<?php
$set = new QuerySet();
$set = $set
  ->onlyMainNodes();
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('mainNodeOnly' => true));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

### Control roles/policies

Turn off all role-based policies:

```
<?php
$set = new QuerySet();
$set = $set
  ->policies(false);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$set = new QuerySet(array('useRoles' => false));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```

Filter based on a custom policy array:

```
<?php
$policies = array(/*...*/);
$set = new QuerySet();
$set = $set
  ->policies($policies);
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
// or
$policies = array(/*...*/);
$set = new QuerySet(array('policies' => $policies));
foreach ($set as $node) {
    echo $node->attribute('name'), "\n";
}
```


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
