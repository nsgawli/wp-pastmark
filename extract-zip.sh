#!/bin/bash

ROOT_PATH=/var/www/html

# check if the file pastmark.zip exists in ROOT_PATH and delete it if it does
if [ -f "$ROOT_PATH/pastmark.zip" ]; then
	rm $ROOT_PATH/pastmark.zip
fi

# check if the directory pastmark exists in ROOT_PATH and delete it if it does
if [ -d "$ROOT_PATH/pastmark" ]; then
	rm -rf $ROOT_PATH/pastmark
fi

# check if node_modules exists in current directory, npm install if it does not exists
if [ ! -d "node_modules" ]; then
	npm install
fi

# remove the build directory if it exists
if [ -d "build" ]; then
	rm -rf build
fi

# make pot file, it has command to build the project
bash make-pot.sh

# copy the necessary files to the pastmark directory in ROOT_PATH
mkdir $ROOT_PATH/pastmark
cp -r build vendor includes languages LICENSE uninstall.php pastmark.php readme.txt $ROOT_PATH/pastmark

# zip the pastmark directory
cd $ROOT_PATH
zip -r pastmark.zip pastmark
