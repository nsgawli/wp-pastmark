#!/bin/bash
npm run build
mkdir -p languages
wp i18n make-pot . languages/logtrail.pot --domain=logtrail --allow-root \
    --include=build,includes,logtrail.php