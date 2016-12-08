<?php
namespace Aplia\Content\Query;

use Aplia\Content\Exceptions\TypeError;

class ContentHelper
{
    /**
     * Extracts content-object ids from the $items.
     * $items can be one of:
     * - eZObjectRelation attribute - The relation ID is extracted.
     * - eZObjectRelationList attribute - The object IDs of the related items are extracted.
     * - An array - Each item in the array can be one of:
     *   - numeric - A numeric identifier, used as content-object id.
     *   - eZContentObject - The ID is extracted;
     *   - eZContentObjectTreeNode - The content-object ID is extracted.
     *
     * @return An array with content-object IDs
     */
    public static function makeContentIdFilter($items, $identifierCallback = null)
    {
        if ($items === null) {
            return null;
        }
        if ($items instanceof FilterValues) {
            return $items;
        }

        $names = array();
        if ($items instanceof \eZContentObjectAttribute) {
            $attr = $items;
            $items = array();
            if ($attr->attribute('data_type_string') == 'ezobjectrelationlist') {
                $relationContent = $attr->content();
                $relationList = $relationContent['relation_list'];
                $nodeIds = array();
                foreach ($relationList as $relation) {
                    if ($relation['node_id']) {
                        $nodeIds[] = $relation['node_id'];
                    }
                }
                $nodes = \eZContentObjectTreeNode::fetch($nodeIds);
                if (!is_array($nodes)) {
                    $nodes = array($nodes);
                }
                foreach ($nodes as $node) {
                    $items[] = $node->attribute('contentobject_id');
                    $names[$node->attribute('contentobject_id')] = $node->getName();
                }
            } else if ($attr->attribute('data_type_string') == 'ezobjectrelation') {
                $contentObject = $attr->content();
                if ($contentObject) {
                    $items[] = $contentObject->attribute('id');
                    $names[$contentObject->attribute('id')] = $contentObject->name();
                }
            } else {
                throw new \Exception("Unsupported content-object data-type '" . $attr->attribute('data_type_string') . "'");
            }
        } else {
            $lookupIds = array();
            $namedIds = array();
            foreach ($items as $idx => $item) {
                if ($item instanceof \eZContentObject) {
                    $items[$idx] = $item->attribute('id');
                    $names[$item->attribute('id')] = $item->name();
                } elseif ($item instanceof \eZContentObjectTreeNode) {
                    $items[$idx] = $item->attribute('contentobject_id');
                    $names[$item->attribute('contentobject_id')] = $item->getName();
                } elseif (is_numeric($item)) {
                    if (!isset($names[$item])) {
                        $lookupIds[] = $item;
                    }
                } else {
                    $namedIds[] = $item;
                    unset($items[$idx]);
                }
            }
            if ($identifierCallback !== null && $namedIds) {
                $result = $identifierCallback($namedIds);
                if ($result) {
                    $items = array_merge($items, $result['items']);
                    $names = array_merge($names, $result['names']);
                }
            }
            if ($lookupIds) {
                $objects = \eZContentObject::fetchIDArray($lookupIds);
                foreach ($objects as $object) {
                    $names[$object->attribute('id')] = $object->name();
                }
            }
        }
        return new FilterValues($items, $names);
    }

    public static function makeNodeIdList($nodes)
    {
        if ($nodes === null) {
            return null;
        }
        if ($nodes instanceof FilterValues) {
            return $nodes->items;
        }

        $ids = array();
        foreach ($nodes as $item) {
            $ids[] = self::getNodeId($item);
        }
        return $ids;
    }

    /**
    * Get a node ID from a content node, content object or numeric.
    *
    * @throws TypeError if the type or class is not supported.
    * @return int
    */
    public static function getNodeId($node)
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof \eZContentObjectTreeNode) {
            return $node->attribute('node_id');
        } else if ($node instanceof \eZContentObject) {
            return $node->attribute('main_node_id');
        } else if (is_numeric($node)) {
            return $node;
        } else {
            throw new TypeError("The node type is supported: " . is_object($node) ? get_class($node) : gettype($node));
        }
    }

    /**
    * Get a content node from a content node, content object or numeric.
    *
    * @throws TypeError if the type or class is not supported.
    * @return eZContentObjectTreeNode
    */
    public static function getNode($node)
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof \eZContentObjectTreeNode) {
            return $node;
        } else if ($node instanceof \eZContentObject) {
            return $node->attribute('main_node');
        } else if (is_numeric($node)) {
            $nodeId = $node;
            $node = \eZContentObjectTreeNode::fetch($nodeId);
            if (!$node) {
                throw new TypeError("The node does not exist: $nodeId");
            }
            return $node;
        } else {
            throw new TypeError("The node type is supported: " . is_object($node) ? get_class($node) : gettype($node));
        }
    }
}
