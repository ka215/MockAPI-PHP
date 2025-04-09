# Documentation Build Requirements

To build the GitHub Pages documentation:

- You must have [Pandoc](https://pandoc.org/) installed (version 2.0+ recommended).
- The documentation is built by converting Markdown files in `/sections/` to HTML files using `build.sh`.

## Installation

### Windows
See below for Windows installation instructions.

```bash
choco install pandoc
```

### MacOS
See below for MacOS installation instructions.

```bash
brew install pandoc
```

### Ubuntu
See below for Ubuntu installation instructions.

```bash
sudo apt install pandoc
```

## Workflow (Example)

```bash
git switch gh-pages
./split-readme.sh
./build.sh
git add docs/
git commit -m "Update documentation"
git push origin gh-pages
```
