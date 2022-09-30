#! /bin/sh

#file = grep  $1
iput=$( echo "$1" | grep -o "[^\.\/]\+\(\+[^ ]\+\)*" )
iput=$1
vfile="$(pwd)/"$iput
ydr="\/Users\/wulin\/Documents\/yangdaorong\/"
dk="\/data\/webRoot\/"

news=$(echo $vfile | sed "s/$ydr/$dk/g")

echo "File is : docker exec php5.6fpm php -f ${news}\n"

docker exec php5.6fpm php -f $news


