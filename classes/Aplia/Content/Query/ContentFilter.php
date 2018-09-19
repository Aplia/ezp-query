<?php
namespace Aplia\Content\Query;
use Exception;
use Aplia\Content\Exceptions\TypeError;
use Aplia\Content\Exceptions\QueryError;

/**
* Helper class to aid in converting FieldFilter classes into content class attribute and extended filters.
*/
class ContentFilter
{
    public $includeClasses = true;
    public $classes;
    public $attributes;
    public $nestedAttributes;

    protected $_extended;
    protected $_nested;

    public function __construct($classes = array(), $attributes = array(), $extended = null)
    {
        $this->classes = $classes;
        $this->attributes = $attributes;
        $this->_extended = $extended;
    }

    public function __get($name)
    {
        if ($name == 'hasClasses') {
            return (bool)$this->classes;
        } else if ($name == 'hasAttributes') {
            return (bool)$this->attributes;
        } else if ($name == 'hasExtended') {
            return (bool)$this->nestedAttributes || $this->extended !== null && $this->extended['params'];
        } else if ($name == 'extended') {
            if ($this->nestedAttributes) {
                if ($this->_nested === null) {
                    $this->_nested = $this->buildNested();
                }
                return $this->_nested;
            } else {
                return $this->_extended;
            }
        }
    }

    public function merge($items)
    {
        if (isset($items['classes'])) {
            $this->classes = array_unique(array_merge($this->classes, $items['classes']));
        }
        if (isset($items['extended'])) {
            $extFilter = $items['extended'];
            if ($this->extended === null) {
                $this->_extended = $extFilter;
            } else if ($this->extended['id'] == $extFilter['id']) {
                $this->_extended['params'] = array_merge($this->extended['params'], $extFilter['params']);
            } else {
                throw new Exception('Cannot set extended attributes \'' . $extFilter['id'] . '\', it has already been defined with a different ID: \'' . $this->extended['id'] . '\'');
            }
        }
        if (isset($items['attribute'])) {
            $this->attributes = array_merge( $this->attributes, $items['attribute'] );
        }
        if (isset($items['nested'])) {
            if ($this->nestedAttributes === null) {
                $this->nestedAttributes = array();
            }
            $this->processNested($this->nestedAttributes, $items['nested']);
        }
    }

    /**
     * Processes filter lists in a nested/recursive manner,
     * it turns any objects into proper array structures used by
     * NestedFilter and adds it to $filters.
     */
    protected function processNested(&$filters, $nested)
    {
        foreach ($nested as $item) {
            if (is_array($item)) {
                $filters[] = $item;
            } else if (is_object($item)) {
                $subItems = $item->nested;
                if (!$subItems) {
                    continue;
                }
                $subFilters = array();
                $this->processNested($subFilters, $subItems);
                if (!$subFilters) {
                    continue;
                }
                $condition = $item->condition;
                if (!in_array($condition, array('and', 'or', 'merge'))) {
                    throw new QueryError("Unknown filter condition '$condition'");
                }
                if ($condition === 'merge') {
                    $filters = array_merge($filters, $subFilters);
                } else {
                    $filters[] = array(
                        'cond' => $condition,
                        'attrs' => $subFilters,
                    );
                }
            } else {
                throw new TypeError("Unsupported filter type: " . var_export($item, true));
            }
        }
    }

    public function setFilter(FieldFilterBase $filter, $mode='attribute')
    {
        $attrFilter = $filter->getContentFilter($mode);
        $this->merge($attrFilter);
    }

    public function setFilters(array $filters, $mode='attribute')
    {
        foreach ($filters as $filter)
        {
            $this->setFilter($filter, $mode);
        }
    }

    protected function buildNested()
    {
        if (!$this->nestedAttributes) {
            return null;
        }
        return array(
            'id' => 'NestedFilterSet',
            'params' => array(
                'cond' => 'AND',
                'attrs' => $this->nestedAttributes,
            ),
        );
    }
}
