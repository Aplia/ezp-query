<?php
namespace Aplia\Content\Query;

class FilterValues
{
    public $names;
    public $items;

    public function __construct(array $items = null, array $names = null)
    {
        $this->names = $names !== null ? $names : array();
        $this->items = $items !== null ? $items : array();
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
        return array_merge( array_keys( get_object_vars($this) ), array() );
    }
}
