#!/bin/bash

ROOT_PATH=/var/www/html

# check if the file logtrail.zip exists in ROOT_PATH and delete it if it does
if [ -f "$ROOT_PATH/logtrail.zip" ]; then
	rm $ROOT_PATH/logtrail.zip
fi

# check if the directory logtrail exists in ROOT_PATH and delete it if it does
if [ -d "$ROOT_PATH/logtrail" ]; then
	rm -rf $ROOT_PATH/logtrail
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

# copy the necessary files to the logtrail directory in ROOT_PATH
mkdir $ROOT_PATH/logtrail
cp -r build vendor includes languages LICENSE uninstall.php logtrail.php readme.txt $ROOT_PATH/logtrail

# zip the logtrail directory
cd $ROOT_PATH
zip -r logtrail.zip logtrail
