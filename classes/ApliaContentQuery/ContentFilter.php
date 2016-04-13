<?php
namespace ApliaContentQuery;

/**
* Helper class to aid in converting FieldFilter classes into content class attribute and extended filters.
*/
class ContentFilter
{
    public $classes;
    public $attributes;
    public $extended;

    public function __construct($classes = array(), $attributes = array(), $extended = null)
    {
        $this->classes = $classes;
        $this->attributes = $attributes;
        $this->extended = $extended;
    }

    public function __get($name)
    {
        if ($name == 'hasClasses') {
            return (bool)$this->classes;
        } else if ($name == 'hasAttributes') {
            return (bool)$this->attributes;
        } else if ($name == 'hasExtended') {
            return $this->extended !== null && $this->extended['params'];
        }
    }

    public function merge($items)
    {
        if (isset($items['classes'])) {
            $this->classes = array_unique(array_merge($this->classes, $items['classes']));
        } else if (isset($items['extended'])) {
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
    }

    public function setFilter(FieldFilterBase $filter)
    {
        $attrFilter = $filter->getContentFilter();
        $this->merge($attrFilter);
    }

    public function setFilters(array $filters)
    {
        foreach ($filters as $filter)
        {
            $this->setFilter($filter);
        }
    }
}
