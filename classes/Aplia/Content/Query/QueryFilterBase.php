<?php
namespace Aplia\Content\Query;

use Aplia\Content\Query\FieldFilterBase;
use Aplia\Content\Query\IntegerFieldFilter;
use Aplia\Content\Query\BoolFieldFilter;
use Aplia\Content\Query\StringFieldFilter;
use Aplia\Content\Exceptions\FilterTypeError;
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
 * 
 * Adding filters can be performed in many ways, the simplest is to use filter()
 * which filters on existing fields on the node, object, class or content attributes.
 * For more advanced filters use defineFilter() which creates a filter with a given
 * name and supports filtering on content attributes by loading values from GET parameters.
 * 
 * e.g. to filter on priority on node and a content attribute do:
 * @code
 * $query
 *   ->filter('priority:<', 1000)
 *   ->filter('folder/menu', true);
 * @endcode
 * 
 * This will generate SQL that looks like:
 * @code
 * contentobject_tree.priority < 1000 AND aplia_naf_17.sort_key_int = '1'
 * @endcode
 * 
 * Using a subquery to change condition between expression
 * @code
 * $query
 *   ->subQuery('or')
 *     ->filter('priority:<', 1000)
 *     ->filter('folder/menu', true);
 * @endcode
 * 
 * This will generate SQL that looks like:
 * @code
 * (contentobject_tree.priority < 1000 OR aplia_naf_17.sort_key_int = '1')
 * @endcode
 */
class QueryFilterBase
{
    public $condition = "and";
    public $classes = array();
    public $filters = array();
    public $objectFilters = array();
    public $filterTypes = array();
    public $useClone = false;

    protected $_nested;
    protected $_isDirty = false;

    public function __construct(array $params=null)
    {
        if (isset($params['condition'])) {
            $this->condition = $params['condition'];
        }
        $this->classes = Arr::get($params, 'classes', array());
        $this->filters = Arr::get($params, 'filterValues', array());
        $this->objectFilters = Arr::get($params, 'objectFilterValues', array());
        $filterTypes = Arr::get($params, 'filters', null);
        if ($filterTypes) {
            foreach ($filterTypes as $name => $filterType) {
                if ($filterType instanceof FieldFilterBase) {
                    $this->addDefinedFilter($name, $filterType);
                } elseif (is_array($filterType)) {
                    if (isset($filterType['type'])) {
                        $this->addDefinedFilter($name, $filterType['type'], Arr::get($filterType, 'attribute'));
                    } else {
                        $this->addDefinedFilter($name, $filterType[0], isset($filterType[1]) ? $filterType[1] : null);
                    }
                } else {
                    $this->addDefinedFilter($name, $filterType);
                }
            }
        }
        $this->useClone = Arr::get($params, 'useClone', false);
    }

    /**
    * Creates a copy of the query set and returns it.
    *
    * @return Aplia\Content\Query\QuerySet
    */
    public function copy()
    {
        $set = clone $this;
        return $set;
    }

    /**
    * Returns a potential clone of the current query-set.
    * If $useClone is true then the query-set is cloned, otherwise
    * it returns the same instance.
    *
    * @param $markDirty If true then it will reset the current result instance.
    * @return Aplia\Content\Query\QuerySet
    */
    protected function makeClone($markDirty)
    {
        if ($this->useClone) {
            $clone = clone $this;
        } else {
            $clone = $this;
        }
        if ($markDirty) {
            $clone->_isDirty = true;
        }
        return $clone;
    }

    public function __clone() {
        foreach ($this->filterTypes as $key => $filter) {
            if (is_object($filter)) {
                $this->filterTypes[$key] = clone $filter;
            }
        }
    }

    /**
     * Defines a new QueryFilter object and adds it to the filter list.
     * Returns the QueryFilter object.
     * 
     * This method may be called with just one parameter, if the first
     * parameter is an array then it is used as $objectFilters and condition
     * becomes 'and'.
     * 
     * @param $condition The filter condition to use between each filter, 'and', 'or' or 'merge'.
     * @param $objectFilters array of existing filters to add to sub-query.
     * @return QueryFilter
     */
    public function subQuery($condition="and", array $objectFilters=null)
    {
        $argc = func_num_args();
        if ($argc === 1) {
            // If only one parameter is used then check if it is in array,
            // if so it is actually the object filters.
            if (is_array($condition)) {
                $objectFilters = $condition;
                $condition = "and";
            }
        }
        $query = new QueryFilter(array(
            'parent' => $this,
            'objectFilterValues' => $objectFilters,
            'condition' => $condition,
        ));
        $this->objectFilters[] = $query;
        return $query;
    }

    /**
    * Defines a new field filter.
    * If $type is a string it can contain one of these types:
    * - 'int' - Integer field
    * - 'bool' - Boolean field
    * - 'string' - String field
    *
    * @param $name Name of the filter to be used when setting the filter value later on.
    * @param $type The type of filter, either a string or an instance of FieldFilterBase.
    * @param $attributeIdentifier The identifier of the class attribute to filter on, or null to use $name.
    * @return Aplia\Content\Query\QuerySet
    */
    public function defineFilter($name, $type, $attributeIdentifier=null)
    {
        $clone = $this->makeClone(true);
        $clone->addDefinedFilter($name, $type, $attributeIdentifier);
        return $clone;
    }

    /**
     * Loads all specified content classes and adds filters for each of the
     * attributes in them. The type of filter is based on the data-type.
     *
     * The handler for each data-type is taken from contentquery.ini
     * in the group AttributeFilters and the variable Handlers.
     * This is an associative array where the key is the data-type string
     * and the value is the handler class to instantiate.
     *
     * If no handler matches it uses one of the builtin types:
     * - ezinteger - Integer filter
     * - ezboolean - Boolean filter
     * All else is defined as a string filter.
     *
     * @param $classes if null then it uses classes defined on the query-set.
     */
    public function loadFilters(array $classes=null)
    {
        $clone = $this->makeClone(true);
        $clone->loadClassFilters($classes);
        return $clone;
    }

    /**
    * Sets a single filter value with $name and $value,
    * or multiple values by passing an array in $name.
    * When passing multiple filters the array must associative with
    * the keys being the name and the item the value.
    *
    * @param $name Name of filter to set (from defineFilter)
    * @param $value Value of the filter
    * @return Aplia\Content\Query\QuerySet
    */
    public function filter($name, $value=null)
    {
        $clone = $this->makeClone(true);
        if (is_array($name)) {
            $filters = $name;
            foreach ($filters as $name => $value) {
                $clone->setFilter($name, $value);
            }
        } else {
            $clone->setFilter($name, $value);
        }
        return $clone;
    }

    /**
     * Adds the custom filter to the nested filters. The filter parameter is
     * either an array with NestedFilter filter structure, or an object that
     * supports 'nested' and 'condition' properties (e.g. QueryFilter).
     * 
     * @param $filter array or object containing the filters to add.
     * @return self
     */
    public function addFilter($filter)
    {
        $clone = $this->makeClone(true);
        if (is_array($filter)) {
            $clone->objectFilters[] = $filter;
        } else if (is_object($filter)) {
            $clone->objectFilter[] = $filter;
        } else {
            throw new TypeError("Unsupported filter type: " . var_export($filter, true));
        }
        $clone->_nested = null;
        return $clone;
    }

    /**
    * Sets the filter $name to value $value.
    */
    protected function setFilter($name, $value)
    {
        if (isset($this->filterTypes[$name])) {
            $this->filters[$name] = $value;
            $this->_nested = null;
            return;
        }
        $key = $name;
        $elements = explode(":", $name, 2);
        $operator = null;
        if (count($elements) > 1) {
            list($name, $operator) = $elements;
        } else {
            $name = $elements[0];
        }
        if ($name == 'visible' || $name == 'visibility') {
            $this->useVisibility = false;
            if ($name == 'visible') {
                $this->objectFilters[] = array('visibility', $value);
            } else {
                $this->objectFilters[] = array($key, $value);
            }
        } elseif ($name == 'path' || $name == 'section' || $name == 'state' || $name == 'depth' || $name == 'class_identifier' || $name == 'class_name' || $name == 'priority' || $name == 'name') {
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'published' || $name == 'modified' || $name == 'modified_subnode') {
            if ($value instanceof \DateTime) {
                $value = $value->getTimestamp();
            }
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'node_id') {
            if (is_object($value)) {
                if ($value instanceof \eZContentObjectTreeNode) {
                    $value = $value->attribute('node_id');
                } elseif ($value instanceof \eZContentObject) {
                    $value = $value->attribute('main_node_id');
                }
            }
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'contentobject_id') {
            if (is_object($value)) {
                if ($value instanceof \eZContentObjectTreeNode) {
                    $value = $value->attribute('contentobject_id');
                } elseif ($value instanceof \eZContentObject) {
                    $value = $value->attribute('id');
                }
            }
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'path_element') {
            if (is_object($value)) {
                if ($value instanceof \eZContentObjectTreeNode) {
                    $value = $value->attribute('contentobject_id');
                } elseif ($value instanceof \eZContentObject) {
                    $value = $value->attribute('id');
                }
            }
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'class_identifier') {
            if (is_object($value)) {
                if ($value instanceof \eZContentClass) {
                    $value = $value->attribute('identifier');
                } elseif ($value instanceof \eZContentObjectTreeNode || $value instanceof \eZContentObject) {
                    $value = $value->attribute('class_identifier');
                }
            }
            $this->objectFilters[] = array($key, $value);
        } elseif ($name == 'class_name') {
            if (is_object($value)) {
                if ($value instanceof \eZContentClass) {
                    $value = $value->attribute('name');
                } elseif ($value instanceof \eZContentObjectTreeNode || $value instanceof \eZContentObject) {
                    $value = $value->attribute('class_name');
                }
            }
            $this->objectFilters[] = array($key, $value);
        } else {
            // Detect class/attribute strings and use them as filters
            // If not they are placed as object filters
            if (strpos($name, "/") !== false) {
                $this->objectFilters[] = array($key, $value);
            } else {
                $this->filters[$name] = $value;
            }
        }
        $this->_nested = null;
    }

    /**
     * Loads all specified content classes and adds filters for each of the
     * attributes in them. The type of filter is based on the data-type.
     *
     * The handler for each data-type is taken from contentquery.ini
     * in the group AttributeFilters and the variable Handlers.
     * This is an associative array where the key is the data-type string
     * and the value is the handler class to instantiate.
     *
     * If no handler matches it uses one of the builtin types:
     * - ezinteger - Integer filter
     * - ezboolean - Boolean filter
     * All else is defined as a string filter.
     *
     * @param $classes if null then it uses classes defined on the query-set.
     */
    protected function loadClassFilters(array $classes=null)
    {
        if ($classes === null) {
            $classes = $this->classes;
        }
        if (!$classes) {
            return;
        }
        $settings = \eZINI::instance('contentquery.ini');
        $handlers = array();
        if ($settings->hasVariable('AttributeFilters', 'Handlers')) {
            $handlers = $settings->variable('AttributeFilters', 'Handlers');
        }
        foreach ($classes as $identifier) {
            $contentClass = \eZContentClass::fetchByIdentifier($identifier);
            if (!$contentClass) {
                throw new FilterTypeError("Failed to fetch filters for class: $identifier");
            }
            $dataMap = $contentClass->dataMap();
            foreach ($dataMap as $attributeIdentifier => $attribute) {
                $filterIdentifier = "$identifier/$attributeIdentifier";
                $typeString = $attribute->attribute('data_type_string');
                if (isset($handlers[$typeString])) {
                    $handlerClass = $handlers[$typeString];
                    $handler = new $handlerClass(array(
                        'attribute' => $attribute,
                    ));
                    $this->defineFilter($filterIdentifier, $handler);
                } elseif ($typeString == 'ezinteger' || $typeString == 'ezselection') {
                    $this->defineFilter($filterIdentifier, 'int', $filterIdentifier);
                } elseif ($typeString == 'ezboolean') {
                    $this->defineFilter($filterIdentifier, 'bool', $filterIdentifier);
                } else{
                    $this->defineFilter($filterIdentifier, 'string', $filterIdentifier);
                }
            }
        }
    }

    /**
    * Adds/replaces a filter definition.
    * If $type is a string it can contain one of these types:
    * - 'int' - Integer field
    * - 'bool' - Boolean field
    * - 'string' - String field
    * The implementation will then pick an appropriate filter class.
    *
    * The attributeIdentifier may have filter operators attached to it by
    * using a colon followed by the filter operator name, multiple operators
    * are supported by using additional colons.
    * eg. 'title:first_letter:='
    *
    * @param $name Name of the filter to be used when setting the filter value later on.
    * @param $type The type of filter, either a string or an instance of FieldFilterBase.
    * @param $attributeIdentifier The identifier of the class attribute to filter on, or null to use $name.
    */
    protected function addDefinedFilter($name, $type, $attributeIdentifier=null)
    {
        if (is_array($type)) {
            if (isset($type['type'])) {
                $attributeIdentifier = Arr::get($type, 'attribute');
                $type = Arr::get($type, 'type');
            } else{
                if (isset($type[1])) {
                    $attributeIdentifier = $type[1];
                }
                $type = $type[0];
            }
        }
        if (!$attributeIdentifier) {
            $attributeIdentifier = $name;
        }
        if (!is_object($type)) {
            $operator = '=';
            $pre = array();
            $post = array();
            if (preg_match("|^([^:]+)((:([^:]+))+)?$|", $attributeIdentifier, $matches)) {
                if (isset($matches[2])) {
                    $ops = explode(":", substr($matches[2], 1));
                    $attributeIdentifier = $matches[1];
                    $inPre = true;
                    foreach ($ops as $modifier) {
                        if (in_array($modifier, \Aplia\Content\Filter\NestedFilter::$ops)) {
                            $operator = $modifier;
                            $inPre = false;
                        } else if ($inPre) {
                            $pre[] = $modifier;
                        } else {
                            $post[] = $modifier;
                        }
                    }
                    if (!strlen($operator)) {
                        $operator = '=';
                    }
                }
            }

            if (preg_match("/^(.+):(.+)$/", $attributeIdentifier, $matches)) {
                $attributeIdentifier = $matches[1];
                $operator = $matches[2];
            }
            $filterParams = array(
                'contentAttribute' => $attributeIdentifier,
                'operator' => $operator
            );
            if ($pre) {
                $filterParams['fieldModifiers'] = $pre;
            }
            if ($post) {
                $filterParams['valueModifiers'] = $post;
            }
            if ($type == 'int') {
                $filter = new IntegerFieldFilter($filterParams);
            } elseif ($type == 'bool') {
                $filter = new BoolFieldFilter($filterParams);
            } elseif ($type == 'string') {
                $filter = new StringFieldFilter($filterParams);
            } else {
                throw new FilterTypeError("Unsupported filter type: $type");
            }
        } else {
            $filter = $type;
        }
        $this->filterTypes[$name] = $filter;
        $this->_nested = null;
    }

    protected function makeNestedFilter()
    {
        return $this->objectFilters;
    }

    public function __isset($name)
    {
        return $name === 'nested';
    }

    public function __get($name)
    {
        if ($name === 'nested') {
            if ($this->_nested === null) {
                $this->_nested = $this->makeNestedFilter();
            }
            return $this->_nested;
        } else {
            throw new \Exception("Unknown property '$name' on " . get_class($this));
        }
    }

    // eZ template access
    public function hasAttribute($key)
    {
        return isset($this->$key) || in_array($key, array('nested'));
    }

    public function attribute($key)
    {
        if (in_array($key, array('nested'))) {
            return $this->$key();
        }
        return $this->$key;
    }

    public function attributes()
    {
        return array_merge(array_keys( get_object_vars($this) ), array('nested'));
    }
}
