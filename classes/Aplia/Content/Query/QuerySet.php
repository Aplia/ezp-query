<?php
namespace Aplia\Content\Query;

use Aplia\Content\Query\SortOrder;
use Aplia\Content\Query\ContentFilter;
use Aplia\Content\Query\Result;
use Aplia\Content\Query\FieldFilterBase;
use Aplia\Content\Query\FilterValues;
use Aplia\Content\Query\QueryFilterBase;
use Aplia\Content\Exceptions\QueryError;
use Aplia\Support\Arr;

class QuerySet extends QueryFilterBase implements \IteratorAggregate
{
    const DEFAULT_PAGE_LIMIT = 10;

    public $parentNodeId;
    public $depth = null;
    public $depthOperator = 'le';
    public $paginate = false;
    public $pageNumber = null;
    public $pageParams = null;
    public $limitSetting = array();
    public $sortChoices = array();
    public $defaultSortOrder = 'newest';
    public $sortQueryName = 'sort';
    /**
    * Determines where the sort field is taken from by default.
    * Default is to sort by property.
    *
    * - query - Read sort value from a query parameter.
    * - array - Classic eZ publish sort array
    * - property - Read sort value from a property on the query-set.
    */
    public $sortMode = 'property';
    /**
     * How filters are interpreted, the default is 'nested'.
     *
     * - attribute - Sent as regular attribute filters.
     * - nested - Uses an extended attribute filter with the filters as the parameter.
     */
    public $filterMode = 'nested';
    /**
     * Whether to only use the default visibility rules, if true it will filter
     * out any invisible nodes.
     * Default is true.
     */
    public $useVisibility = true;
    /**
     * Whether to only include main nodes in the result.
     *
     * Default is false.
     */
    public $mainNodeOnly = false;
    /**
     * Whether to only use policiy rules from the database role setup or
     * to use policies from the query-set. True means to use the database
     * roles while false means to use query-set policies.
     *
     * Default is true.
     */
    public $useRoles = true;
    /**
     * Array of role policies to send to the queries, this will only be
     * used if $useRoles is false.
     */
    public $policies = array();
    /**
    * The query variables, defaults to $_GET.
    */
    public $query = array();

    public $sortOrder;
    public $sortField;
    public $sortArray;
    public $pageLimit;

    // Caches
    protected $_totalCount;
    protected $_contentFilter;

    protected $_result;

    public function __construct(array $params=null)
    {
        parent::__construct($params);
        $this->parentNodeId = Arr::get($params, 'parentNodeId');
        $this->limitSetting = Arr::get($params, 'limitSetting', array());
        $this->query = Arr::get($params, 'query', $_GET);
        $this->sortQueryName = Arr::get($params, 'sortQueryName', 'sort');
        $this->defaultSortOrder = Arr::get($params, 'defaultSortOrder', 'newest');
        $this->defaultPageLimit = Arr::get($params, 'defaultPageLimit');
        $this->filterMode = Arr::get($params, 'filterMode', 'nested');
        $this->useVisibility = Arr::get($params, 'useVisibility', true);
        $this->mainNodeOnly = Arr::get($params, 'mainNodeOnly', false);
        $useRoles = true;
        $policies = Arr::get($params, 'policies', null);
        if ($policies !== null) {
            if (!is_array($policies)) {
                throw new \Exception("Parameter must be an array of policies: \$policies");
            }
            $this->policies = $policies;
            $useRoles = false;
        }
        $this->useRoles = Arr::get($params, 'useRoles', $useRoles);
        $this->depth = Arr::get($params, 'depth', null);
        $depthOperator = Arr::get($params, 'depthOperator', 'le');
        $this->depthOperatorNames = array(
            '<' => 'lt',
            '>' => 'gt',
            '<=' => 'le',
            '>=' => 'ge',
            '=' => 'eq',
        );
        if (isset($depthOperatorNames[$depthOperator])) {
            $depthOperator = $depthOperatorNames[$depthOperator];
        }
        if (!in_array($this->depthOperator, array('le', 'ge', 'lt', 'gt', 'eq'))) {
            throw new \Exception("Depth operator not supported: " . Arr::get($params, 'depthOperator', 'le'));
        }
        $this->depthOperator = $depthOperator;
        $this->paginate = Arr::get($params, 'paginate', false);
        $this->pageNumber = Arr::get($params, 'pageNumber');
        if (isset($params['sortChoices'])) {
            $sortChoices = $params['sortChoices'];
        } else {
            $sortChoices = self::createDefaultSortChoices();
        }
        $this->sortChoices = $sortChoices;
        $this->sortChoiceNames = Arr::get($params, 'sortChoiceNames');

        $this->sortOrder = Arr::get($params, 'sortOrder');
        $this->paginatorClass = Arr::get($params, 'paginatorClass', 'Aplia\\Content\\Query\\PageNumPagination');
        $this->filterClass = Arr::get($params, 'filterClass', 'Aplia\\Content\\Query\\ContentFilter');
        $this->sortOrderClass = Arr::get($params, 'sortOrderClass', 'Aplia\\Content\\Query\\SortOrder');
    }

    /**
    * Creates the default sort choices and returns it.
    *
    * @return array
    */
    static public function createDefaultSortChoices()
    {
        return array(
            'oldest' => function ($id, $order) { return array(array('published', 1)); },
            'newest' => function ($id, $order) { return array(array('published', 0)); },
            'a-z' => function ($id, $order) { return array(array('name', 1)); },
            'z-a' => function ($id, $order) { return array(array('name', 0)); },
            'published' => function ($id, $order) { return array(array('published', $order)); },
            'path' => function ($id, $order) { return array(array('path', $order)); },
            'modified' => function ($id, $order) { return array(array('modified', $order)); },
            'section' => function ($id, $order) { return array(array('section', $order)); },
            'depth' => function ($id, $order) { return array(array('depth', $order)); },
            'class_identifier' => function ($id, $order) { return array(array('class_identifier', $order)); },
            'class_name' => function ($id, $order) { return array(array('class_name', $order)); },
            'priority' => function ($id, $order) { return array(array('priority', $order)); },
            'name' => function ($id, $order) { return array(array('name', $order)); },
            'modified_subnode' => function ($id, $order) { return array(array('modified_subnode', $order)); },
            'node_id' => function ($id, $order) { return array(array('node_id', $order)); },
            'content_object_id' => function ($id, $order) { return array(array('content_object_id', $order)); },
        );
    }

    /**
    * Sets the query depth.
    * If $operator is set it will also limit the depth using this operator.
    *
    * Supported depth operators are:
    * >,  gt
    * <,  lt
    * >=, ge
    * <=, le
    * =,  eq
    *
    * @param $value The depth of the query, false to fetch entire tree.
    * @param $operator Operator for depth filter.
    * @return Aplia\Content\Query\QuerySet
    */
    public function depth($value, $operator = null)
    {
        $clone = $this->makeClone(true);
        $clone->depth = $value;
        if ($operator !== null) {
            if (isset($depthOperatorNames[$depthOperator])) {
                $operator = $depthOperatorNames[$operator];
            }
            if (!in_array($this->depthOperator, array('le', 'ge', 'lt', 'gt', 'eq'))) {
                throw new \Exception("Depth operator not supported: " . $operator);
            }
            $this->depthOperator = $operator;
        }
        return $clone;
    }


    /**
    * Adds new classes to the filter, this limits the result to only
    * include classes of the given type.
    *
    * @param $value The depth of the query, false to fetch entire tree.
    * @return Aplia\Content\Query\QuerySet
    */
    public function classes($values)
    {
        $clone = $this->makeClone(true);
        if ($values === null) {
            $clone->classes = array();
        } else {
            if (!is_array($values)) {
                $values = array($values);
            }
            $clone->classes = array_unique(array_merge($clone->classes, $values));
        }
        return $clone;
    }

    /**
    * Sets the parent node to start the result from.
    * The node can be one of:
    * - null - The parent node is reset to the default.
    * - content node - The node id is used.
    * - content object - The main node id is used.
    * - numeric - The value is used as node id.
    *
    * @param $value The depth of the query, false to fetch entire tree.
    * @return Aplia\Content\Query\QuerySet
    */
    public function parentNode($node)
    {
        $clone = $this->makeClone(true);
        $clone->parentNodeId = ContentHelper::getNodeId($node);
        return $clone;
    }

    /**
    * Controls whether result list contains only main nodes or
    * all nodes. The default is to include all nodes.
    *
    * Only include main nodes:
    * @code
    * onlyMainNodes(true)
    * @endcode
    *
    * @param $enabled True to only include main nodes, false to include all.
    * @return Aplia\Content\Query\QuerySet
    */
    public function onlyMainNodes($enabled=true)
    {
        $clone = $this->makeClone(true);
        $clone->mainNodeOnly = $enabled;
        return $clone;
    }

    /**
    * Controls whether default visibility filters are used or not.
    *
    * Turning off default visibility filters:
    * @code
    * visibility(false)
    * @endcode
    *
    * @param $enabled True to turn on visibility filters, or false to turn off.
    * @return Aplia\Content\Query\QuerySet
    */
    public function visibility($enabled)
    {
        $clone = $this->makeClone(true);
        $clone->useVisibility = $enabled;
        return $clone;
    }

    /**
    * Controls whether policies are taken from the database roles
    * or from a custom list supplied to the query-set.
    *
    * Calling it without parameters (or true) will enable default roles:
    * @code
    * policies()
    * @endcode
    *
    * Calling it with false will disable all policies.
    * @code
    * policies(false)
    * @endcode
    *
    * Calling it with an array will disable role policies and set
    * a new set of policies.
    * @code
    * policies(array())
    * @endcode
    *
    * @param $policies Array of policies to set, or boolean to enable/disable.
    * @return Aplia\Content\Query\QuerySet
    */
    public function policies($policies=true)
    {
        $clone = $this->makeClone(true);
        if ($policies === true) {
            $clone->useRoles = true;
        } elseif ($policies === false) {
            $clone->useRoles = false;
        } elseif (is_array($policies)) {
            $clone->policies = $policies;
            $clone->useRoles = false;
        }
        return $clone;
    }

    /**
    * Executes the query and returns the result object.
    * The result object is cached so calling this method multiple
    * times is efficient.
    *
    * @return Aplia\Content\Query\Result
    */
    public function result($asObject=true)
    {
        if ($this->_result === null) {
            $this->_result = $this->createResult($asObject);
        }
        return $this->_result;
    }

    /**
    * Executes the query and returns the matching items.
    * The result object is cached so calling this method multiple
    * times is efficient.
    *
    * @return array
    */
    public function items($asObject=true)
    {
        if ($this->_result === null) {
            $this->_result = $this->createResult($asObject);
        }
        return $this->_result->items;
    }

    /**
    * Calculates the total number of items matching the current filters.
    * The count value is cached so calling this method multiple
    * times is efficient.
    * Note: The total amount excludes any paginator.
    *
    * @return int
    */
    public function count()
    {
        if ($this->_totalCount === null) {
            $this->_totalCount = $this->calculateTotalCount();
        }
        return $this->_totalCount;
    }

    /**
     * @copydoc
     */
    public function addFilter($filter)
    {
        if ($this->filterMode !== 'nested') {
            throw new QueryError("Can only used custom filters when the mode is nested, current mode: " . $this->filterMode);
        }
        return parent::addFilter($filter);
    }

    /**
    * Changes sort order to a specific sort field.
    * The sort fields are defined in the constructor, the default fields are:
    * - 'newest' - Sort by published date, newest entries first.
    * - 'oldest' - Sort by published date, oldest entries first.
    * - 'a-z' - Sort by name, ascending.
    * - 'z-a' - Sort by name, descending.
    *
    * A sort field may also be prefixed with a minus to change the direction,
    * note however that this requires that the field supports changing direction.
    * e.g. 'newest' and 'a-z' have fixed direction and does not support this.
    *
    * @param $field The name of the field or null to reset the sort order to default.
    * @return Aplia\Content\Query\QuerySet
    */
    public function sortByField($field=null)
    {
        $clone = $this->makeClone(true);
        $clone->sortMode = 'property';
        $clone->setSortByField($field);
        return $clone;
    }

    /**
    * Changes sort order to a sort array as used by eZ publish.
    *
    * @param $sortArray The array with sort items.
    * @return Aplia\Content\Query\QuerySet
    */
    public function sortByArray(array $sortArray)
    {
        $clone = $this->makeClone(true);
        $clone->sortMode = 'array';
        $clone->setSortByArray($sortArray);
        return $clone;
    }

    /**
    * Changes sort order based on a query parameter.
    *
    * @param $queryName The name of the query or null to use the name defined in constructor.
    * @return Aplia\Content\Query\QuerySet
    */
    public function sortByQuery($queryName=null)
    {
        $clone = $this->makeClone(true);
        $clone->sortMode = 'query';
        $clone->setSortByQuery($queryName);
        return $clone;
    }

    /**
    * Changes the available sort choices, or if null resets to the default ones.
    * Use an array of names to limit the existing choices to a sub-set.
    *
    * @param $choices An array of choice names to limit the choice, or null to reset.
    * @return Aplia\Content\Query\QuerySet
    */
    public function sortChoiceNames($names=null)
    {
        $clone = $this->makeClone(true);
        $clone->setSortChoiceNames($names);
        return $clone;
    }

    /**
    * Set or add sort choices, or if null resets to the default ones.
    * Use an associative array of sort choices to set the current sort choice,
    * where the key is the name of the sort choice, the value may be one of:
    * - string - Used as the sort field.
    * - callback - Function which is called to return the sort field and direction
    * - array - Contains sort field and direction.
    *
    * @param $choices An array of choice names to limit the choice, or an array with choice entries.
    * @return Aplia\Content\Query\QuerySet
    */
    public function sortChoices($choices=null)
    {
        $clone = $this->makeClone(true);
        $clone->setSortChoices($choices);
        return $clone;
    }

    /**
    * Changes result to be paginated, the chosen page is taken from a query parameter.
    * The name of the page parameter is set in the constructor.
    *
    * If no page limit has been set it switches to use the default.
    *
    * @param $params Extra parameters sent to the paginator.
    * @return Aplia\Content\Query\QuerySet
    */
    public function pageFromQuery($params=null)
    {
        $clone = $this->makeClone(true);
        $clone->paginate = true;
        if ($params !== null) {
            $this->pageParams = $params;
        }
        if ($this->pageLimit === null) {
            $this->pageLimit = 'default';
        }
        return $clone;
    }

    /**
    * Changes result to be paginated and sets the page to use.
    *
    * If no page limit has been set it switches to use the default.
    *
    * @param $num The page number to use for the result.
    * @return Aplia\Content\Query\QuerySet
    */
    public function page($num=1)
    {
        $clone = $this->makeClone(true);
        $clone->paginate = true;
        $clone->pageNumber = $num;
        if ($this->pageLimit === null) {
            $this->pageLimit = 'default';
        }
        return $clone;
    }

    /**
    * Changes page limit to $limit, this overrides any default limit or
    * settings based limit.
    * A special value of 'default' can be used to use the default
    * value as the default.
    *
    * @param $limit The page limit, 'default' to reset to default or null to disable.
    * @return Aplia\Content\Query\QuerySet
    */
    public function pageLimit($limit)
    {
        $clone = $this->makeClone(true);
        $clone->pageLimit = $limit;
        return $clone;
    }

    /**
     * Creates an iterator which will walk over all items in based
     * the current filters.
     * The iterator will only fetch n items at a time based on the
     * page limit.
     *
     * This can be used to easily and safely traverse over an entire
     * set without worrying about page sizes and optimal fetches.
     *
     * @return Aplia\Content\Query\QuerySetIterator
     */
    public function iterator()
    {
        if (!$this->paginate) {
            $this->paginate = true;
        }
        $this->pageParams = null;
        // Make sure a page limit exists, if not use defaults
        if ($this->pageLimit === null) {
            $this->pageLimit = 'default';
        }

        $totalCount = null;
        if ($this->_totalCount === null) {
            $totalCount = $this->calculateTotalCount();
            $this->_totalCount = $totalCount;
        } else {
            $totalCount = $this->_totalCount;
        }
        $paginator = $this->createPaginator($totalCount, $this->pageLimit);
        return new QuerySetIterator($this, $paginator);
    }

    /**
    * Sets the current sort by field.
    */
    protected function setSortByField($field=null)
    {
        $this->sortField = $field;
    }

    /**
    * Sets the current array sort.
    */
    protected function setSortByArray(array $sortArray=null)
    {
        $this->sortArray = $sortArray;
    }

    /**
    * Sets the current query name to use for sort field.
    */
    protected function setSortByQuery($queryName=null)
    {
        $this->sortQueryName = $queryName;
    }

    /**
    * Limits the sort choices to defined names, or reset choices
    */
    protected function setSortChoiceNames($names=null)
    {
        if ($names !== null) {
            $this->sortChoiceNames = array_unique(array_merge($this->sortChoiceNames ? $this->sortChoiceNames : array(), $names));
        } else {
            $this->sortChoiceNames = null;
        }
    }

    /**
    * Sets the available sort choices, or resets to default.
    */
    protected function setSortChoices($choices=null)
    {
        if ($choices !== null) {
            $this->sortChoices = array_merge($this->sortChoices ? $this->sortChoices : array(), $choices);
        } else {
            $this->sortChoices = self::createDefaultSortChoices();
        }
    }

    /**
    * Calculates the total count for the current query-set and returns it.
    *
    * @return Aplia\Content\Query\Result
    */
    protected function calculateTotalCount()
    {
        if ($this->_isDirty) {
            $this->result = null;
            $this->_totalCount = null;
            $this->_contentFilter = null;
            $this->_isDirty = false;
        }

        if ($this->_contentFilter === null) {
            $contentFilter = $this->createFilter();
            $this->_contentFilter = $contentFilter;
        } else {
            $contentFilter = $this->_contentFilter;
        }
        $extendedFilter = null;
        if ($contentFilter && $contentFilter->hasExtended) {
            $extendedFilter = $contentFilter->extended;
        }

        $attributeFilter = ($contentFilter && $contentFilter->hasAttributes) ? $contentFilter->attributes : false;
        $classFilter = $contentFilter->includeClasses ? 'include' : 'exclude';
        $parentNodeId = $this->parentNodeId !== null ? $this->parentNodeId : 2;
        $totalCount = (int)\eZContentObjectTreeNode::subTreeCountByNodeID(array(
            'ClassFilterType' => $classFilter,
            'ClassFilterArray' => $contentFilter->classes,
            'AttributeFilter' => $attributeFilter,
            'ExtendedAttributeFilter' => $extendedFilter,
            'IgnoreVisibility' => !$this->useVisibility,
            'MainNodeOnly' => $this->mainNodeOnly,
            'Limitation' => $this->useRoles ? null : $this->policies,
            'Depth' => $this->depth,
            'DepthOperator' => $this->depthOperator,
         ), $parentNodeId);
        return $totalCount;
    }

    /**
    * Creates a new result object based on the current filters,
    * pagination and sorting and returns it.
    *
    * @return Aplia\Content\Query\Result
    */
    protected function createResult($asObject=true)
    {
        if ($this->_isDirty) {
            $this->result = null;
            $this->_totalCount = null;
            $this->_contentFilter = null;
            $this->_isDirty = false;
        }

        if ($this->_contentFilter === null) {
            $contentFilter = $this->createFilter();
            $this->_contentFilter = $contentFilter;
        } else {
            $contentFilter = $this->_contentFilter;
        }
        $this->_contentFilter = $contentFilter;
        $extendedFilter = null;
        if ($contentFilter && $contentFilter->hasExtended) {
            $extendedFilter = $contentFilter->extended;
        }

        $attributeFilter = ($contentFilter && $contentFilter->hasAttributes) ? $contentFilter->attributes : false;
        $classFilter = $contentFilter->includeClasses ? 'include' : 'exclude';
        $parentNodeId = $this->parentNodeId !== null ? $this->parentNodeId : 2;

        $totalCount = null;
        if ($this->paginate) {
            if ($this->_totalCount === null) {
                $totalCount = (int)\eZContentObjectTreeNode::subTreeCountByNodeID(array(
                    'ClassFilterType' => $classFilter,
                    'ClassFilterArray' => $contentFilter->classes,
                    'AttributeFilter' => $attributeFilter,
                    'ExtendedAttributeFilter' => $extendedFilter,
                    'IgnoreVisibility' => !$this->useVisibility,
                    'MainNodeOnly' => $this->mainNodeOnly,
                    'Limitation' => $this->useRoles ? null : $this->policies,
                    'Depth' => $this->depth,
                    'DepthOperator' => $this->depthOperator,
                 ), $parentNodeId);
                $this->_totalCount = $totalCount;
            } else {
                $totalCount = $this->_totalCount;
            }
        }

        $page = null;
        if ($totalCount !== null) {
            $paginator = $this->createPaginator($totalCount, $this->pageLimit);
            $page = $this->createPage($paginator);
        }
        $sortOrder = $this->sortOrder;
        if ($sortOrder === null) {
            $sortOrder = $this->createSortHandler();
            $this->sortOrder = $sortOrder;
        }
        if ($this->sortMode === 'property') {
            if ($this->sortField !== null) {
                $sortOrder->resolveQuery($this->sortField);
            } else {
                // Use default sort
                $sortOrder->resolveQuery();
            }
        } elseif ($this->sortMode === 'array') {
            $sortOrder->resolveArray($this->sortArray);
        } else if ($this->sortMode === 'query') {
            if ($this->sortQueryName !== null) {
                $query = $this->query;
                $queryName = $this->sortQueryName;
                $sortOrder->resolveQuery(isset($query[$queryName]) ? $query[$queryName] : null);
            } else {
                // Use default sort
                $sortOrder->resolveQuery();
            }
        } else {
            throw new QueryError("Unsupported sort-mode: " . $this->sortMode);
        }

        $items = \eZContentObjectTreeNode::subTreeByNodeId( array(
            'ClassFilterType' => $classFilter,
            'ClassFilterArray' => $contentFilter->classes,
            'AttributeFilter' => $attributeFilter,
            'ExtendedAttributeFilter' => $extendedFilter,
            'IgnoreVisibility' => !$this->useVisibility,
            'MainNodeOnly' => $this->mainNodeOnly,
            'AsObject' => $asObject,
            'Limitation' => $this->useRoles ? null : $this->policies,
            'Depth' => $this->depth,
            'DepthOperator' => $this->depthOperator,
            'Offset' => $page ? $page->offset : 0,
            'Limit' => $page ? $page->size : $this->getDefaultPageLimit(),
            'SortBy' => ($sortOrder && $sortOrder->sortArray) ? $sortOrder->sortArray : null,
        ), $parentNodeId );
        if ($items === null) {
            throw new QueryError("No item list returned from sub-tree query");
        }

        $filters = null;
        return new Result($items, $totalCount, $page, $sortOrder, $filters, $contentFilter);
    }

    /**
    * Creates a new sort handler and returns it.
    * The default implementation creates a SortOrder instance with the current
    * choices and default sort order.
    *
    * Re-implement this method to change the sort order algorithm
    *
    * @return Aplia\Content\Query\SortOrder
    */
    protected function createSortHandler()
    {
        $class = $this->sortOrderClass;
        $sortChoices = $this->sortChoices;
        if ($this->sortChoiceNames !== null) {
            $sortChoices = Arr::only($sortChoices, $this->sortChoiceNames);
        }
        return new $class($sortChoices, $this->defaultSortOrder);
    }

    /**
    * Creates a new paginator and returns it, if pagination is disabled it
    * will return null.
    * The default implementation creates a PageNumPagination instance with the
    * current page parameters. Set 'paginatorClass' in the constructor (or
    * instance) to change the class used.
    *
    * Re-implement this method to change the pagination behaviour.
    *
    * @return Aplia\Content\Query\BasePagination
    */
    protected function createPaginator($totalCount, $pageLimit = null)
    {
        if (!$this->paginate || $pageLimit === null) {
            return null;
        }
        $pageLimit = $pageLimit === 'default' ? $this->getDefaultPageLimit() : $pageLimit;
        $class = $this->paginatorClass;
        $paginator = new $class($totalCount, $pageLimit, $this->pageParams);
        return $paginator;
    }

    protected function createPage($paginator)
    {
        if (!$this->paginate || $paginator === null) {
            return null;
        }
        $pageNumber = $this->pageNumber;
        if ($pageNumber === null) {
            $pageNumber = $paginator->getQueryPage($this->query);
        }
        $page = $paginator[$pageNumber];
        return $page;
    }

    /**
    * Creates a new content filter and returns it, if no filters are
    * used it returns null.
    * The default implementation creates a ContentFilter instance with the
    * filter types and values, and also filtered class names.
    *
    * @return Aplia\Content\Query\ContentFilter
    */
    protected function createFilter()
    {
        $allowUserFilter = false;
        $filterTypes = $this->filterTypes;
        $filterValues = $this->filters;
        $objectFilters = $this->objectFilters;

        if ($filterTypes) {
            if ($filterValues) {
                FieldFilterBase::setFilterValues($filterTypes, $filterValues);
            }
            if ($allowUserFilter) {
                FieldFilterBase::resolveFilters($filterTypes, $this->query);
            }
        }

        $class = $this->filterClass;
        $contentFilter = new $class($this->classes);
        if ($objectFilters) {
            $contentFilter->merge(array(
                'nested' => $objectFilters,
            ));
        }
        if ($filterTypes) {
            $contentFilter->setFilters($filterTypes, $this->filterMode);
        }

        return $contentFilter;
    }

    /**
    * Returns the default page limit value.
    * The value is either set on the query set or can be taken from an ini file.
    */
    public function getDefaultPageLimit()
    {
        if ($this->limitSetting) {
            $settings = \eZINI::instance(isset($this->limitSetting[2]) ? $this->limitSetting[2] : 'project.ini');
            $defaultPageLimit = $settings->variable($this->limitSetting[0], $this->limitSetting[1]);
        } else {
            $defaultPageLimit = $this->defaultPageLimit !== null ? $this->defaultPageLimit : self::DEFAULT_PAGE_LIMIT;
        }
        return $defaultPageLimit;
    }

    public function __clone() {
        parent::__clone();
        if ($this->sortOrder !== null) {
            $this->sortOrder = clone $sortOrder;
        }
        if ($this->_result !== null) {
            $this->_result = clone $result;
        }
    }

    // IteratorAggregate
    public function getIterator()
    {
        return $this->iterator();
    }

    // eZ template access
    public function hasAttribute($key)
    {
        if (isset($this->$key) || in_array($key, array('result', 'count', 'items', 'iterator'))) {
            return true;
        }
        return parent::hasAttribute($key);
    }

    public function attribute($key)
    {
        if (in_array($key, array('result', 'count', 'items', 'iterator'))) {
            return $this->$key();
        }
        if (parent::hasAttribute($key)) {
            return parent::attribute($key);
        }
        return $this->$key;
    }

    public function attributes()
    {
        return array_unique(array_merge(array_keys( get_object_vars($this) ), array('result', 'count', 'items', 'iterator'), parent::attributes()));
    }
}
