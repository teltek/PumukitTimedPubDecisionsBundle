<?php

namespace Pumukit\TimedPubDecisionsBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class MenuService implements ItemInterface
{
    public function getName(): string
    {
        return 'Timed Publishing Decisions Timeframes';
    }

    public function getUri(): string
    {
        return 'pumukit_newadmin_timeframes_index_default';
    }

    public function getAccessRole(): string
    {
        return 'ROLE_ACCESS_TIMEDPUBDECISIONS_TIMELINE';
    }
}
