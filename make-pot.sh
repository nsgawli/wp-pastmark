#!/bin/bash
npm run build
mkdir -p languages
wp i18n make-pot . languages/pastmark.pot --domain=pastmark --allow-root \
    --include=build,includes,pastmark.php