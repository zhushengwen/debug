<?php

if(!DEBUG_AJAX && DEBUG_FB)
{
    debug_stop();
    echo debug_console();
}
?>