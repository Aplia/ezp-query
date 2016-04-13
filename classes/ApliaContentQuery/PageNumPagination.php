<?php
namespace ApliaContentQuery;

class PageNumPagination implements \ArrayAccess, BasePagination
{
    public $pageSize = 10;
    public $total = null;
    public $pageVariable = null;
    public $namedSizes = null;

    public function __construct($pageSize = 10, $pageVariable = null, $namedSizes = null, $total = null)
    {
        $this->pageSize = $pageSize;
        $this->total = $total;
        $this->pageVariable = $pageVariable;
        $this->namedSizes = $namedSizes;
    }

    public static function resolvePage($query, $pageSize = 10, $pageVariable = null, $namedSizes = null, $total = null)
    {
        $paginator = new PageNumPagination($pageSize, $pageVariable, $namedSizes, $total);
        return $paginator[$paginator->getQueryPage($query)];
    }

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

    public function getQueryPage($queryParams)
    {
        if ( isset( $queryParams['page'] ) ) {
            return (int)$queryParams['page'];
        } else if ( isset( $queryParams['offset'] ) ) {
            $offset = (int)$queryParams['offset'];
            return floor($offset / $this->pageSize) + 1;
        }
        return 1;
    }

    public function resolveQuery($queryParams)
    {
        if ( $this->pageVariable !== null && $this->namedSizes !== null )
        {
            if ( isset($queryParams[$this->pageVariable]) )
            {
                $pageIdentifier = $queryParams[$this->pageVariable];
                if (isset($this->namedSizes[$pageIdentifier])) {
                    $this->pageSize = $this->namedSizes[$pageIdentifier];
                }
            }
        }
    }

    public function getPage($num)
    {
        if (!isset($this[$num])) {
            return null;
        }
        $idx = $num - 1;
        $offset = $idx * $this->pageSize;
        return new PaginationPage($offset, $this->pageSize, $num);
    }

    // ArrayAccess, we always return a Page instance even if outside range
    public function offsetExists($num)
    {
        if ($num < 1 || ($this->total !== null ? $num > $this->total*$this->pageSize : false)) {
            return false;
        }
        return true;
    }

    public function offsetGet($num)
    {
        $page = $this->getPage($num);
        if ($page == null) {
            return new PaginationPage(0, $this->pageSize, 1);
        }
        return $page;
    }

    public function offsetSet($num, $val)
    {
        // Ignore
    }

    public function offsetUnset($num)
    {
        // Ignore
    }
}
