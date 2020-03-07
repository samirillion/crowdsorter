<?php

namespace IFM;

use WordPress\ORM\Model\Post;

class Message extends Post
{
    protected $post_type = 'message';

    /**
     * Start a query to find models matching specific criteria.
     *
     * @return ModelQuery
     */
    public static function query()
    {
        $query = parent::query();
        $query->where('post_type', 'message');

        return $query;
    }
}