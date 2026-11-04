# New DevArt Product Checklist

Use this checklist when creating a repository from `cursor_template`.

The pristine template intentionally does not pass full validation. Validation
requires exactly one `source/package/pkg_*.xml`, at least one real extension
source declared by that package, and the corresponding extension manifests.

## Bootstrap Order

1. Copy the template.
2. Replace the generic placeholders.
3. Add the package wrapper.
4. Add real extensions under `source/extensions/`.
5. Confirm the package inventory.
6. Run validation.
7. Run the build.
8. Complete Joomla QA.

## 1. Repository Identity

- [ ] Set `{{PRODUCT_NAME}}` to the public product name.
- [ ] Set `{{PRODUCT_DESCRIPTION}}` to one concise product description.
- [ ] Set `{{REPOSITORY_NAME}}` to the Git repository name, for example
      `devart-video`.
- [ ] Confirm the default branch.

## 2. Package and Extension Identity

- [ ] Set `{{PACKAGE_ID}}` to the complete Joomla package ID, including the
      `pkg_` prefix, for example `pkg_devartvideo`.
- [ ] Use package manifest filename `{{PACKAGE_ID}}.xml`.
- [ ] Set `<packagename>` to `{{PACKAGE_NAME}}` without the `pkg_` prefix, for
      example `devartvideo` when `{{PACKAGE_ID}}` is `pkg_devartvideo`.
- [ ] Do not set `<packagename>` to `{{PACKAGE_ID}}` unless the Joomla
      manifest convention for that product explicitly requires it.
- [ ] Expect generated outer package filename
      `{{PACKAGE_ID}}_v{{VERSION}}.zip`.
- [ ] The build script derives the outer filename prefix from the package
      manifest filename, not from `<packagename>`.
- [ ] Set `{{EXTENSION_IDS}}` to the complete package inventory.
- [ ] Set `{{SUPPORTED_LANGUAGES}}` to every packaged administrator and
      frontend language. Default (mandatory unless explicitly overridden for
      this product bootstrap): English (en-GB) plus Greek (el), French (fr),
      German (de), Spanish (es), Italian (it), Portuguese (pt).
- [ ] Use Joomla-native identifiers:
  - Components: `com_<name>`
  - Modules: `mod_<name>`
  - Plugins: `plg_<group>_<name>`

## 3. Source and Manifests

- [ ] Add exactly one `source/package/pkg_*.xml` package manifest.
- [ ] Add at least one real extension under the matching directory:
  - Components: `source/extensions/component/`
  - Modules: `source/extensions/modules/`
  - Plugins: `source/extensions/plugins/`
- [ ] Add the corresponding manifest to every extension source.
- [ ] Declare every extension archive in the package manifest.
- [ ] Confirm the package inventory exactly matches the extension directories.
- [ ] Set `{{VERSION}}` in the package and every extension manifest.
- [ ] Set `{{CREATION_DATE}}` in every manifest using `YYYY-MM-DD`.
- [ ] Keep all manifest versions identical.
- [ ] Require Joomla 6+ and PHP 8.3+.
- [ ] Verify extension IDs, plugin groups, file paths, language files, media,
      SQL, and schema references.
- [ ] Provide administrator and frontend language files for the default
      multi-language baseline (en-GB plus el/fr/de/es/it/pt), unless this
      product bootstrap explicitly overrides supported languages. Use careful
      auto-translation followed by manual review; do not translate technical
      identifiers or placeholder variables. Verify placeholders/format
      specifiers remain intact and missing/untranslated keys are not
      introduced.
- [ ] Do not develop from generated ZIP packages.

## 4. Documentation

- [ ] Replace `{{ARCHITECTURE_SUMMARY}}` with the product architecture.
- [ ] Replace `{{IMPLEMENTED_FEATURES}}` with verified source capabilities.
- [ ] Replace `{{KNOWN_LIMITATIONS}}` with explicit current limitations.
- [ ] Replace repository status fields in `PROJECT_STATUS.md` with verified
      facts only after Joomla QA.
- [ ] Set `{{CURRENT_MILESTONE}}` and define the next functional milestone.
- [ ] Replace `{{CHANGE_SUMMARY}}` with the initial changelog entry.
- [ ] Keep `AGENTS.md`, README, CHANGELOG, `PROJECT_STATUS.md`, `docs/`, and
      `qa/` consistent.

## 5. Repository Rules and QA

- [ ] Replace `{{PROJECT_SPECIFIC_RULES}}` with only indispensable product rules.
- [ ] Replace `{{PRODUCT_QA_CHECKS}}` with product-specific functional checks.
- [ ] Keep shared DevArt standards unchanged unless the infrastructure itself
      is intentionally being updated.
- [ ] Confirm `qa/checklist.md` covers installation, update, permissions,
      frontend behavior, caching, multilingual behavior, and package quality.

## 6. Placeholder Audit

- [ ] Run:

```shell
rg '\{\{[A-Z0-9_]+\}\}' .
```

- [ ] Replace every placeholder outside this checklist.
- [ ] Keep the placeholder examples in this checklist as the reusable reference.
- [ ] Confirm no inherited product name, extension ID, namespace, version,
      feature description, or QA scenario remains.

## 7. Validation and Build

- [ ] Confirm the package wrapper and real extension sources are present.
- [ ] Run `php -l scripts/validate.php`.
- [ ] Run `php -l scripts/build.php`.
- [ ] Run `php scripts/validate.php`.
- [ ] Review the complete diff.
- [ ] Run `php scripts/build.php`.
- [ ] Confirm the package and SHA-256 were generated without overwriting an
      existing build.

## 8. Joomla QA and Status

- [ ] Install the generated package in Joomla.
- [ ] Test a clean installation.
- [ ] Test an update installation.
- [ ] Complete `qa/checklist.md`.
- [ ] Update `PROJECT_STATUS.md` only after successful Joomla QA is explicitly
      confirmed.
- [ ] Start the next milestone only after the current milestone is closed.
