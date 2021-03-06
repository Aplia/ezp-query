<?php
namespace Aplia\Content\Query;

class StringFieldFilter extends FieldFilterBase
{
    public $queryParam = null;

    public function resolveQuery($queryParams)
    {
        if ( !isset($queryParams[$this->queryParam]) ) {
            return;
        }

        $value = $queryParams[$this->queryParam];
        $selected = array();
        if ( is_array($value) ) {
            foreach ( $value as $v ) {
                $v = trim($v);
                if ( $v ) {
                    $selected[] = $v;
                }
            }
        } else {
            $value = trim( $value );
            if ( $value ) {
                $selected = array( $value );
            }
        }
        if ( $selected )
        {
            $this->selected = $selected;
        }
    }
}
