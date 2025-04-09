#!/bin/bash

INPUT="$1" # "README.md" etc.
OUTPUT_DIR="$2" # "sections" etc.

if [[ -z "$INPUT" || -z "$OUTPUT_DIR" ]]; then
    echo "Usage: $0 <input_markdown_file> <output_directory>"
    exit 1
fi

mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_DIR"/*.md

declare -A filename_count

current_file=""
current_title=""

slugify() {
    echo "$1" \
        | iconv -f UTF-8 -t ASCII//TRANSLIT 2>/dev/null \
        | sed -E 's/[^a-zA-Z0-9]+/-/g' \
        | sed -E 's/^-+|-+$//g' \
        | tr 'A-Z' 'a-z'
}

while IFS= read -r line; do
    if [[ $line =~ ^##\  ]]; then
        # Start new section splitting
        heading="${line#\#\# }"
        slug=$(slugify "$heading")

        # If empty, fallback
        if [[ -z "$slug" ]]; then
            slug="section"
        fi

        # If filename already exists, append a number
        count=${filename_count[$slug]:-0}
        ((count++))
        filename_count[$slug]=$count

        filename="$slug"
        if [[ $count -gt 1 ]]; then
            filename="${slug}-${count}"
        fi

        # current_title=$(echo "$line" | sed -E 's/^##\s+//; s/[^a-zA-Z0-9]+/-/g' | tr 'A-Z' 'a-z')
        # current_file="$OUTPUT_DIR/$current_title.md"
        current_file="$OUTPUT_DIR/$filename.md"
        echo "$line" > "$current_file"
    elif [[ -n "$current_file" ]]; then
        echo "$line" >> "$current_file"
    fi
done < "$INPUT"

echo "README.md has been split into $OUTPUT_DIR/"
