<?php

if(!DEBUG_AJAX && DEBUG_FB)
{
    fb_debug_stop();
    echo debug_console();
}
?>