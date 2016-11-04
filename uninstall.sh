#!/bin/bash

#curl -s https://raw.githubusercontent.com/zhushengwen/debug/master/uninstall.sh | bash -s -

export PATH=$(dirname `find / -name php -executable -type f | head -1`):$PATH
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

[ ! -d $WEB_ROOT/debug ] && echo '未发现调试目录' && exit;

newini=$WEB_ROOT/debug/tmp/php.ini

[ -f $newini ] && \cp -f $newini $phpini

[ -f ./debug/tmp/.runkit ] && pecl uninstll runkit
[ -f ./debug/tmp/.xdebug ] && pecl uninstll xdebug

rm -f $WEB_ROOT/debug

service php-fpm restart