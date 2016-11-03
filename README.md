# cd到网站根目录,然后一键安装



(cat $($(find / -name nginx -executable -type f | head -1) -t 2>&1 | head -1 | awk '{ print $5;}') | grep root | awk '{ print $2;}') | cut -d';' -f1 | cd

curl -s https://raw.githubusercontent.com/zhushengwen/debug/master/debug.sh | bash -s -
