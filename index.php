<?php
if(is_file(DEBUG_TEMP.'/xdebug-trace.html')){
echo file_get_contents(DEBUG_TEMP.'/xdebug-trace.html');
unlink(DEBUG_TEMP.'/xdebug-trace.html');
}