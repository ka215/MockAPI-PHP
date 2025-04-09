#!/bin/bash

# Output Directory
OUTPUT_DIR="$1" # docs
INPUT_DIR="$2" # sections
TEMPLATE="$3" # templates/page.html
LANG="${4:-en}" # Language (default: en)

if [[ -z "$INPUT_DIR" || -z "$OUTPUT_DIR" || -z "$TEMPLATE" ]]; then
    echo "Usage: $0 <input_section_dir> <output_html_dir> <template_file> [lang]"
    exit 1
fi

# Clear output dir.
rm -rf $OUTPUT_DIR
mkdir -p $OUTPUT_DIR

# Convert Markdown → HTML (use pandoc)
for file in "$INPUT_DIR"/*.md; do
    filename=$(basename "$file" .md)
    pandoc "$file" --template="$TEMPLATE" -o "$OUTPUT_DIR/$filename.html"
done

# Generate index.html (link collection)
cat <<EOF > $OUTPUT_DIR/index.html
<!DOCTYPE html>
<html lang="$LANG">
<head>
    <meta charset="UTF-8">
    <title>MockAPI-PHP Docs</title>
</head>
<body>
    <h1>MockAPI-PHP (${LANG^^})</h1>
    <p><strong>Documentations</strong></p>
    <ul>
EOF

for file in "$INPUT_DIR"/*.md; do
    filename=$(basename "$file" .md)
    title=$(head -n 1 "$file" | sed 's/^# *//')
    echo "        <li><a href=\"$filename.html\">$title</a></li>" >> "$OUTPUT_DIR/index.html"
done

cat <<EOF >> $OUTPUT_DIR/index.html
    </ul>
</body>
</html>
EOF

echo "Documentation built in $OUTPUT_DIR/"
