<?php
namespace Aplia\Content\Query;

class Result
{
    public $items;
    public $total;
    public $page;
    public $sortOrder;
    public $filters;
    public $contentFilter;

    public function __construct($items, $total, $page, $sortOrder, array $filters = null, $contentFilter = null)
    {
        $this->items = $items;
        $this->total = $total;
        $this->page = $page;
        $this->sortOrder = $sortOrder;
        $this->filters = $filters ? $filters : array();
        $this->contentFilter = $contentFilter;
    }

    // eZ template access

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
        return array_keys( get_object_vars($this) );
    }
}
