#!/bin/bash

#curl -s https://raw.githubusercontent.com/zhushengwen/debug/master/debug.sh | bash -s -

[ ! -z "${BASH_SOURCE[0]}" ] && cd $(dirname $( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd ))

export PATH=$(dirname `find / -maxdepth 5 -name php -executable -type f | head -1`):$PATH
which php>/dev/null 2>&1; [ $? == 1 ] && echo '未安装php' && exit;
phpini=`php --ini | grep "Loaded Configuration" | sed -e "s|.\+:\s\+||"`

echo $phpini;
#检查配置文件是否存在
[ ! -f $phpini ] && echo '未找到php.ini' && exit;

#移动调试目录
WEB_ROOT=`pwd`
#echo please input nginx web root:
#read  WEB_ROOT

WEB_ROOT=${WEB_ROOT%*/}
echo WEB_ROOT:$WEB_ROOT
cd $WEB_ROOT


#下载调试文件
curl -o ./debug.zip -L https://github.com/zhushengwen/debug/archive/master.zip
unzip -o ./debug.zip
rm -rf ./debug.zip
mkdir -p ./debug
\cp -rf ./debug-master/* ./debug/
rm -rf ./debug-master

newini=$WEB_ROOT/debug/tmp/php.ini
#保存原来的配置文件
[ ! -f $newini ] && cp $phpini $newini

curl -o ./debug/crudini https://raw.githubusercontent.com/pixelb/crudini/master/crudini
chmod +x ./debug/crudini

user=`ps aux | grep php-fpm | tail -2 | head -1 | awk '{print $1}'`
chown $user:$user -R ./debug
sed -i "/^auto_prepend_file.*/i\auto_prepend_file = $WEB_ROOT/debug/auto_prepend.php" $phpini
sed -i "/^auto_prepend_file.*/{ n; d;}" $phpini

[ -z "`php -m | grep runkit`" ] && pecl install runkit && touch ./debug/tmp/.runkit
[ -z "`php -m | grep runkit`" ] && (./debug/crudini --merge $phpini <<!
[runkit]
extension=runkit.so
runkit.internal_override = On
!
)

[ -z "`php -m | grep xdebug`" ] && pecl install xdebug && touch ./debug/tmp/.xdebug
[ -z "`php -m | grep xdebug`" ] && (./debug/crudini --merge $phpini <<!
[xdebug]
zend_extension=xdebug.so
xdebug.default_enable = 1
xdebug.remote_connect_back = 0
xdebug.idekey = "PHPSTORM"

xdebug.remote_enable = On
xdebug.remote_host="10.0.2.2"
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
xdebug.profiler_output_dir="$WEB_ROOT/tmp/profile"
!
)
rm -f ./debug/crudini

service php-fpm restart