<?php

namespace Cstudios\CraftChat\variables;

use Cstudios\CraftChat\Plugin;

class CraftChatVariable
{
    public function getSettings()
    {
        return Plugin::getInstance()->getSettings();
    }
}
