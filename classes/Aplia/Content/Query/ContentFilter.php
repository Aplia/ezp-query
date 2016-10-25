<?php
namespace Aplia\Content\Query;

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
                return $this->extended;
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
                $this->extended = $extFilter;
            } else if ($this->extended['id'] == $extFilter['id']) {
                $this->extended['params'] = array_merge($this->extended['params'], $extFilter['params']);
            } else {
                throw new Exception('Cannot set extended attributes \'' . $extFilter['id'] . '\', it has already been defined with a different ID: \'' . $this->extended['id'] . '\'');
            }
        }
        if (isset($items['attribute'])) {
            $this->attributes = array_merge( $this->attributes, $items['attribute'] );
        }
        if (isset($items['nested'])) {
            $this->nestedAttributes = array_merge( $this->nestedAttributes, $items['nested'] );
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
