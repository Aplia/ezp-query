<?php
namespace ApliaContentQuery;

abstract class FieldFilterBase
{
    public $selected = array();
    public $allowInput = true;
    //$items;
    //$selectedItems;

    protected $_items = null;
    protected $_selectedItems = null;

    public function __construct(array $params = null)
    {
        $this->allowInput = isset($params['allowInput']) ? $params['allowInput'] : true;
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
            if ($v === null) {
                continue;
            }
            if ( !is_array($v) ) {
                $v = array( $v );
            }
            if (isset($filters[$k])) {
                $filters[$k]->selected = $v;
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

    protected abstract function buildFilter();

    public abstract function resolveQuery($queryParams);

    public function getContentFilter()
    {
        // Return array with either 'extended' or 'attribute'.
        return null;
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
