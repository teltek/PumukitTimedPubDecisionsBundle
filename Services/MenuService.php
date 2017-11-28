<?php

namespace Pumukit\TimedPubDecisionsBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MenuService implements ItemInterface
{
    public function getName()
    {
        return 'Timed Publishing Decisions Timeframes';
    }

    public function getUri()
    {
        return 'pumukit_newadmin_timeframes_index_default';
    }

    public function getAccessRole()
    {
        return 'ROLE_ACCESS_TAGS';
    }
}
