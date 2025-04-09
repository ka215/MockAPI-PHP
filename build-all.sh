#!/bin/bash

# Base documents (English)
bash split-readme.sh README.md sections
bash build.sh docs sections templates/page.html en

# Japanese documents
bash split-readme.sh README_JP.md sections/ja
bash build.sh docs/ja sections/ja templates/page_ja.html ja
