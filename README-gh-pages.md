# MockAPI-PHP Documentation (GitHub Pages)

This branch (`gh-pages`) contains the built documentation for the [MockAPI-PHP](https://github.com/ka215/MockAPI-PHP) project, published via GitHub Pages.

**Published Site:**  
https://ka215.github.io/MockAPI-PHP/

---

## How to Build

Make sure `Pandoc` is installed (required for HTML generation).
  - [Installation guide](tools.md)

To update the documentation:

1. Switch to the `gh-pages` branch:
    ```bash
    git switch gh-pages
    ```

2. Get the latest README from the main branch:
    ```bash
    git fetch origin
    git checkout origin/main -- README.md
    git checkout origin/main -- README_JP.md
    ```

3. Run the provided scripts:
    ```bash
    ./split-readme.sh README.md sections # Split the main README into sections
    ./build.sh docs sections templates/page.html en # Convert sections into HTML in /docs
    ```
    then Japanese document
    ```bash
    ./split-readme.sh README_JP.md sections/ja
    ./build.sh docs/ja sections/ja templates/page_ja.html ja
    ```
    Or use the integrated build script
    ```bash
    ./build-all.sh
    ```

4. Stage and commit changes:
    ```bash
    git add docs/
    git commit -m "Update documentation from latest README"
    git push origin gh-pages
    ```

---

## Tips

- Only HTML output under `docs/` is used for GitHub Pages.
- Keep `sections/` and `templates/` updated if you wish to modify content or layout.
- Do **not** delete or edit this branch unless you are updating documentation.
