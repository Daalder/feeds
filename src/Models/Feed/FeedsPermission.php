<?php

namespace Daalder\Feeds\Models\Feed;

use Pionect\Daalder\Models\User\Permission;
use Pionect\Daalder\Traits\HasConstants;

/**
 * Class FeedsPermission
 */
class FeedsPermission extends Permission
{
    use HasConstants;

    const VIEW_FEEDS = 'view-feeds';
    const STORE_FEEDS = 'store-feeds';
}
