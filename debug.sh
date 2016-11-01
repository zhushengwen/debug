#!/bin/bash

which php>/dev/null 2>&1; [ $? == 1 ] && echo '未安装php' && exit;

phpini=`php --ini | grep "Loaded Configuration" | sed -e "s|.\+:\s\+||"`

echo $phpini;
#移动调试目录
echo please input nginx web root:
read  WEB_ROOT

WEB_ROOT=${WEB_ROOT%*/}
cd $WEB_ROOT
curl -o debug.zip -L https://github.com/zhushengwen/debug/archive/master.zip
unzip -o debug.zip
rm -rf debug
mv -f debug-master debug
mkdir profile
sed -i "/^auto_prepend_file.*/i\auto_prepend_file = $WEB_ROOT/debug/auto_prepend.php" $phpini
sed -i "/^auto_prepend_file.*/{ n; d;}" $phpini

[ -z "`php -m | grep runkit`" ] && pecl install runkit && (cat <<! >> $phpini
[runkit]
extension=runkit.so
runkit.internal_override = On
!
)

[ -z "`php -m | grep xdebug`" ] && pecl install xdebug && (cat <<! >> $phpini
[xdebug]
zend_extension=xdebug.so
xdebug.default_enable = 1
xdebug.remote_connect_back = 0
xdebug.idekey = "PHPSTORM"

xdebug.remote_enable = On
;xdebug.remote_host="10.0.2.2"
xdebug.remote_port = 9001
xdebug.remote_handler = "dbgp"
xdebug.remote_autostart=1

xdebug.overload_var_dump = 0

xdebug.file_link_format = "notepad2://%f/?%l"
;slowdown
;xdebug.extended_info = Off
xdebug.collect_params = 4
xdebug.collect_vars = On
xdebug.collect_return = Off

;xdebug.profiler_enable=On
xdebug.profiler_output_name="cachegrind.out.%t.%R"
xdebug.profiler_enable_trigger=1
xdebug.profiler_output_dir="$WEB_ROOT/profile"
!
)