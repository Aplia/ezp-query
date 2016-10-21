<?php
namespace Aplia\Content\Query;

interface BasePagination
{
    public function getQueryPage($queryParams);
    public function resolveQuery($queryParams);
    public function getPage($num);
}
