<?php

namespace Cstudios\CraftChat\web\assets\chat;

use craft\web\AssetBundle;
use craft\web\assets\alpineinfo\AlpineInfoAsset;

class ChatAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@Cstudios/CraftChat/web/assets/chat/dist';

        // Wait to load Alpine itself, or assume the project loads it. 
        // We will include our chat.js script here.
        $this->js = [
            'chat.js',
        ];

        $this->css = [
            'chat.css',
        ];

        parent::init();
    }
}
