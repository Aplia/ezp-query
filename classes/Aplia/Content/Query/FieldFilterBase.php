<?php
namespace Aplia\Content\Query;

abstract class FieldFilterBase
{
    public $selected = array();
    public $allowInput = true;
    public $operator = '=';
    public $fieldModifiers;
    public $valueModifiers;
    public $contentAttribute;
    //$items;
    //$selectedItems;

    protected $_items = null;
    protected $_selectedItems = null;

    public function __construct(array $params = null)
    {
        $this->allowInput = isset($params['allowInput']) ? $params['allowInput'] : true;
        $this->operator = isset($params['operator']) ? $params['operator'] : '=';
        $this->fieldModifiers = isset($params['fieldModifiers']) ? $params['fieldModifiers'] : null;
        $this->valueModifiers = isset($params['valueModifiers']) ? $params['valueModifiers'] : null;
        $this->contentAttribute = isset($params['contentAttribute']) ? $params['contentAttribute'] : null;
    }

    // Attribute access

    public function __isset($name)
    {
        return ($name == 'items' || $name == 'selectedItems');
    }

    public function __get($name)
    {
        if ($name == 'items') {
            return $this->getFilterItems();
        } else if ($name == 'selectedItems') {
            return $this->getSelectedItems();
        }
    }

    // eZTemplate access

    public function hasAttribute($key)
    {
        return isset($this->$key);
    }

    public function attribute($key)
    {
        return $this->$key;
    }

    public function attributes($key)
    {
        return array_merge( array_keys( get_object_vars($this) ), array( 'items', 'selectedItems' ) );
    }

    // Filter access

    public static function setFilterValues($filters, $values)
    {
        foreach ( $values as $k => $v) {
            if ($v === null || (is_array($v) && empty($v))) {
                continue;
            }
            if (is_numeric($v) && !is_array($v) ) {
                $v = array( $v );
            }
            if (isset($filters[$k])) {
                $filters[$k]->setSelected($v);
            }
        }
    }

    public static function resolveFilters($filters, $queryParams)
    {
        foreach ($filters as $filter) {
            if ($filter->allowInput) {
                $filter->resolveQuery($queryParams);
            }
        }
    }

    protected function buildFilter()
    {
        return null;
    }

    public abstract function resolveQuery($queryParams);

    public function setSelected($values)
    {
        $this->selected = $values;
    }

    public function hasSelected()
    {
        return $this->selected;
    }

    public function getContentFilter($mode='attribute')
    {
        if ($this->contentAttribute && $this->hasSelected()) {
            $lookups = array();
            if ($this->fieldModifiers) {
                $lookups['pre'] = $this->fieldModifiers;
            }
            if ($this->valueModifiers) {
                $lookups['post'] = $this->valueModifiers;
            }
            if ($mode == 'attribute') {
                return array('attribute' =>
                    array(
                        array($this->contentAttribute, $this->operator, $this->selected, $lookups ? $lookups : null),
                    ),
                );
            } elseif ($mode == 'nested') {
                return array('nested' =>
                    array(
                        array($this->contentAttribute, $this->selected, $this->operator, $lookups ? $lookups : null),
                    ),
                );
            }
        }
    }

    public function getNestedFilter()
    {
        // Return a NestedFilter instance if it should filter the query
        return null;
    }

    protected function getFilterItems()
    {
        if ($this->_items === null)
            $this->_items = $this->buildFilter();
        return $this->_items;
    }

    protected function getSelectedItems()
    {
        if ($this->_selectedItems === null)
        {
            $items = $this->items;
            $this->_selectedItems = array();
            foreach ( $items as $key => $item )
            {
                if ( in_array( $key, $this->selected ) )
                {
                    $this->_selectedItems[] = $item;
                }
            }
        }
        return $this->_selectedItems;
    }
}
