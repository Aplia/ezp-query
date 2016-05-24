<?php
namespace ApliaContentQuery;

class PageNumPagination implements \ArrayAccess, BasePagination
{
    public $pageSize = 10;
    public $count = null;
    public $pageVariable = null;
    public $namedSizes = null;
    public $pageClass = '\\ApliaContentQuery\\PaginationPage';

    public function __construct($count = null, $pageSize = 10, array $params = null)
    {
        $this->pageSize = $pageSize;
        $this->count = $count;
        if (isset($params['pageVariable'])) {
            $this->pageVariable = $params['pageVariable'];
        }
        if (isset($params['namedSizes'])) {
            $this->namedSizes = $params['namedSizes'];
        }
        if (isset($params['pageClass'])) {
            $this->pageClass = $params['pageClass'];
        }
    }

    public static function resolvePage($query, $count = null, $pageSize = 10, array $params = null)
    {
        $paginator = new static($count, $pageSize, $params);
        return $paginator[$paginator->getQueryPage($query)];
    }

    public function getQueryPage($queryParams)
    {
        if (isset($queryParams['page']) && $queryParams['page']) {
            return (int)$queryParams['page'];
        } else if (isset($queryParams['offset']) && $queryParams['offset']) {
            $offset = (int)$queryParams['offset'];
            return $this->calcPageFromOffset($offset);
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
        $offset = $this->calcOffsetFromPage($num);
        $pageSize = $this->calcPageSize($num);
        return new $this->pageClass($offset, min(($this->count - $offset), $pageSize), $num, $this);
    }

    public function pageExists($num)
    {
        if ($num < 1 || ($this->count !== null ? $num > $this->pageCount : false)) {
            return false;
        }
        return true;
    }

    public function getFirstPage()
    {
        return $this[1];
    }

    public function getLastPage()
    {
        return $this[$this->pageCount];
    }

    public function getPreviousPage($page)
    {
        // This will return null if previous page number is invalid
        return $this->getPage($page->num - 1);
    }

    public function getNextPage($page)
    {
        // This will return null if next page number is invalid
        return $this->getPage($page->num + 1);
    }

    public function calcPreviousPageNumber($num)
    {
        if ($num > 1) {
            return $num - 1;
        }
    }

    public function calcNextPageNumber($num)
    {
        if ($num < $this->pageCount) {
            return $num + 1;
        }
    }

    public function calcPageFromOffset($offset)
    {
        return (int)floor($offset / $this->pageSize) + 1;
    }

    public function calcOffsetFromPage($page)
    {
        $idx = $page - 1;
        $offset = $idx * $this->pageSize;
        return $offset;
    }

    public function calcPageCount()
    {
        return max(1, (int)ceil($this->count/$this->pageSize));
    }

    public function calcPageSize($num)
    {
        return $this->pageSize;
    }

    // property access
    public function __get($name)
    {
        if ($name == 'pageCount') {
            return $this->pageCount = $this->calcPageCount();
        } elseif ($name == 'firstPage') {
            return $this->getFirstPage();
        } elseif ($name == 'lastPage') {
            return $this->getLastPage();
        }
        throw new \Exception("No such property '$name'");
    }

    public function __isset($name)
    {
        return $name == 'pageCount' || $name == 'firstPage' || $name == 'lastPage';
    }

    public function __slots()
    {
        return array('pageCount', 'firstPage', 'lastPage');
    }

    // ArrayAccess, we always return a Page instance even if outside range
    public function offsetExists($num)
    {
        return $this->pageExists($num);
    }

    public function offsetGet($num)
    {
        $page = $this->getPage($num);
        if ($page == null) {
            return $this->lastPage;
        }
        return $page;
    }

    public function offsetSet($num, $val)
    {
        throw new \Exception("Cannot set page entries on class '" . get_class($this) . "'");
    }

    public function offsetUnset($num)
    {
        throw new \Exception("Cannot unset page entries on class '" . get_class($this) . "'");
    }

    // eZ template access
    public function hasAttribute($key)
    {
        if (is_numeric($key)) {
            return isset($this[$key]);
        }
        return isset($this->$key);
    }

    public function attribute($key)
    {
        if (is_numeric($key)) {
            return $this[$key];
        }
        return $this->$key;
    }

    public function attributes($key)
    {
        return array_merge(array_keys(get_object_vars($this), $this->__slots()));
    }
}
