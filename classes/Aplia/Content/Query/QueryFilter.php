<?php
namespace Aplia\Content\Query;

use Aplia\Content\Query\QueryFilterBase;
use Aplia\Support\Arr;

/**
 * Defines the filters for a content query. Filters either reference values on the
 * objects, node, class or attributes on the object. Filters may be nested with
 * 'and' and 'or' conditions.
 * 
 * Supported values in $params:
 * 
 * - condition - The condition between each filter item, either 'and', 'or'.
 *               A special value of 'merge' means to merge filters with the sibling filters.
 * - filters - Array of filter objects, these will take filterValues or other input and generate nested filter entries.
 * - filterValues - Values for filters, key is name of filter.
 * - objectFilterValues - Filter entries for nested filtering, array of filter items, 
 *                        item is either an array or an object that has 'nested' and 'condition' properties.
 * - useClone - Whether to use proper clones each time clone() is called, defaults to false.
 */
class QueryFilter extends QueryFilterBase
{
    public $parent;

    public function __construct(array $params=null)
    {
        parent::__construct($params);
        $this->parent = Arr::get($params, 'parent');
    }
}
